"""Compara el modelo actual (v1) con el prototipo v2 sobre los mismos conjuntos.

Conjuntos:
  * tanzania_test      : fotos de campo (Tanzania) reservadas, nunca vistas por v2.
  * peru_cajamarca     : EXTERNO. Ambiente andino (Perú). v2 nunca lo vio.
  * plantdoc_papa      : EXTERNO. Fotos reales de papa de PlantDoc. v2 nunca las vio.
  * otros_cultivos     : hojas de otros cultivos (PlantDoc test): no deben diagnosticarse como papa.

Los modelos reciben la imagen igual que el servicio: PIL -> RGB -> 224x224 bilinear.
Los umbrales del servicio (confianza >= 55 % y margen >= 8 puntos) se aplican aparte.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import tensorflow as tf  # noqa: E402
from PIL import Image  # noqa: E402

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import ARTIFACTS_DIR, BASE_DIR, DATA_DIR, EXTERNAL_DIR, MODEL_DIR  # noqa: E402

POTATO = ["early_blight", "healthy", "late_blight"]
UMBRAL_CONFIANZA = 55.0
UMBRAL_MARGEN = 8.0
NOMBRES = {"early_blight": "Tizón temprano", "healthy": "Sana", "late_blight": "Tizón tardío", "other": "No es papa"}


def listar(directorio: Path, clases: list[str]) -> list[tuple[Path, str]]:
    return [(f, c) for c in clases for f in sorted((directorio / c).glob("*.jpg"))]


def cargar_imagen(ruta: Path, size: int = 224) -> np.ndarray:
    imagen = Image.open(ruta).convert("RGB").resize((size, size), Image.Resampling.BILINEAR)
    return np.asarray(imagen, dtype=np.float32)


def predecir(modelo, archivos: list[Path], lote: int = 64) -> np.ndarray:
    salidas = []
    for i in range(0, len(archivos), lote):
        lote_img = np.stack([cargar_imagen(f) for f in archivos[i : i + lote]])
        salidas.append(modelo.predict(lote_img, verbose=0))
    return np.concatenate(salidas)


def ece(prob: np.ndarray, correcto: np.ndarray, bins: int = 10) -> float:
    conf = prob.max(axis=1)
    total, error = len(conf), 0.0
    for i in range(bins):
        m = (conf > i / bins) & (conf <= (i + 1) / bins)
        if m.any():
            error += m.sum() / total * abs(correcto[m].mean() - conf[m].mean())
    return float(error)


def aceptadas(prob: np.ndarray) -> np.ndarray:
    """Predicciones que el servicio entregaría (confianza y margen suficientes)."""
    orden = np.sort(prob, axis=1)
    mejor, segundo = orden[:, -1] * 100, orden[:, -2] * 100
    return (mejor >= UMBRAL_CONFIANZA) & ((mejor - segundo) >= UMBRAL_MARGEN)


def metricas_papa(prob: np.ndarray, verdad: list[str], nombres_modelo: list[str]) -> dict:
    """Conjuntos con hojas de papa: la clase correcta es una de las 3 de papa."""
    y = np.array([nombres_modelo.index(v) for v in verdad])
    pred = prob.argmax(axis=1)
    correcto = pred == y
    acepta = aceptadas(prob)
    clases_presentes = sorted(set(verdad), key=POTATO.index)
    por_clase = {}
    for c in clases_presentes:
        i = nombres_modelo.index(c)
        tp = int(((pred == i) & (y == i)).sum())
        fp = int(((pred == i) & (y != i)).sum())
        fn = int(((pred != i) & (y == i)).sum())
        p = tp / (tp + fp) if tp + fp else 0.0
        r = tp / (tp + fn) if tp + fn else 0.0
        por_clase[c] = {
            "n": int((y == i).sum()), "precision": round(p, 4), "recall": round(r, 4),
            "f1": round(2 * p * r / (p + r), 4) if p + r else 0.0,
        }
    matriz = np.zeros((len(clases_presentes), len(nombres_modelo)), dtype=int)
    for fila, c in enumerate(clases_presentes):
        for j in range(len(nombres_modelo)):
            matriz[fila, j] = int(((y == nombres_modelo.index(c)) & (pred == j)).sum())
    return {
        "n": int(len(y)),
        "accuracy": round(float(correcto.mean()), 4),
        "f1_macro": round(float(np.mean([v["f1"] for v in por_clase.values()])), 4),
        "por_clase": por_clase,
        "pred_como_no_papa": round(float((pred == nombres_modelo.index("other")).mean()), 4)
        if "other" in nombres_modelo else None,
        "confianza_media_si_acierta": round(float(prob.max(axis=1)[correcto].mean()), 4) if correcto.any() else None,
        "confianza_media_si_falla": round(float(prob.max(axis=1)[~correcto].mean()), 4) if (~correcto).any() else None,
        "ece": round(ece(prob, correcto), 4),
        "servicio": {
            "cobertura": round(float(acepta.mean()), 4),
            "accuracy_si_se_entrega": round(float(correcto[acepta].mean()), 4) if acepta.any() else None,
            "diagnosticos_erroneos_entregados": int((acepta & ~correcto).sum()),
        },
        "matriz": {"filas_reales": clases_presentes, "columnas_pred": nombres_modelo, "valores": matriz.tolist()},
    }


def metricas_otros(prob: np.ndarray, nombres_modelo: list[str]) -> dict:
    """Hojas que NO son de papa: lo correcto es rechazarlas."""
    pred = prob.argmax(axis=1)
    acepta = aceptadas(prob)
    if "other" in nombres_modelo:
        como_otro = pred == nombres_modelo.index("other")
        falso_diagnostico = acepta & ~como_otro
    else:
        como_otro = np.zeros(len(pred), dtype=bool)
        falso_diagnostico = acepta
    return {
        "n": int(len(pred)),
        "clasificadas_como_no_papa": round(float(como_otro.mean()), 4),
        "falso_diagnostico_entregado": round(float(falso_diagnostico.mean()), 4),
        "confianza_media": round(float(prob.max(axis=1).mean()), 4),
    }


def graficar(resultados: dict, destino: Path) -> None:
    conjuntos = [c for c in ("tanzania_test", "peru_cajamarca", "plantdoc_papa") if c in resultados["v2"]]
    fig, ejes = plt.subplots(2, len(conjuntos), figsize=(5.2 * len(conjuntos), 8.4), squeeze=False)
    for fila, version in enumerate(("v1", "v2")):
        for col, conjunto in enumerate(conjuntos):
            m = resultados[version][conjunto]["matriz"]
            valores = np.array(m["valores"])
            eje = ejes[fila][col]
            eje.imshow(valores, cmap="Blues")
            eje.set_xticks(range(len(m["columnas_pred"])), [NOMBRES[c] for c in m["columnas_pred"]], rotation=30, ha="right")
            eje.set_yticks(range(len(m["filas_reales"])), [NOMBRES[c] for c in m["filas_reales"]])
            for i in range(valores.shape[0]):
                for j in range(valores.shape[1]):
                    eje.text(j, i, valores[i, j], ha="center", va="center",
                             color="white" if valores[i, j] > valores.max() / 2 else "black")
            acc = resultados[version][conjunto]["accuracy"]
            eje.set_title(f"{version} - {conjunto}\naccuracy {acc:.1%}")
            eje.set_xlabel("Predicción")
            eje.set_ylabel("Real")
    fig.tight_layout()
    fig.savefig(destino, dpi=130)
    plt.close(fig)


def pct(x) -> str:
    return "—" if x is None else f"{x:.1%}"


def informe(resultados: dict) -> str:
    L = ["# Evaluación comparativa: CNN v1 (actual) vs v2 (prototipo)\n"]
    L.append("Todas las cifras se calculan con el modelo solo (sin los filtros de calidad de imagen del servicio).\n")
    L.append("| Conjunto | n | v1 accuracy | v2 accuracy | v1 F1 macro | v2 F1 macro | v1 ECE | v2 ECE |")
    L.append("|---|---|---|---|---|---|---|---|")
    for c in ("tanzania_test", "peru_cajamarca", "plantdoc_papa"):
        if c not in resultados["v2"]:
            continue
        a, b = resultados["v1"][c], resultados["v2"][c]
        L.append(f"| {c} | {b['n']} | {pct(a['accuracy'])} | {pct(b['accuracy'])} | {a['f1_macro']:.3f} | {b['f1_macro']:.3f} | {a['ece']:.3f} | {b['ece']:.3f} |")
    L.append("\nECE = error de calibración (menor es mejor): qué tan lejos está la confianza mostrada de la probabilidad real de acertar.\n")
    L.append("## Recall por clase\n")
    L.append("| Conjunto | Clase | v1 recall | v2 recall | n |")
    L.append("|---|---|---|---|---|")
    for c in ("tanzania_test", "peru_cajamarca", "plantdoc_papa"):
        if c not in resultados["v2"]:
            continue
        for clase, datos in resultados["v2"][c]["por_clase"].items():
            r1 = resultados["v1"][c]["por_clase"].get(clase, {}).get("recall")
            L.append(f"| {c} | {NOMBRES[clase]} | {pct(r1)} | {pct(datos['recall'])} | {datos['n']} |")
    L.append("\n## Confianza cuando el modelo se equivoca\n")
    L.append("| Conjunto | v1 | v2 |")
    L.append("|---|---|---|")
    for c in ("tanzania_test", "peru_cajamarca", "plantdoc_papa"):
        if c in resultados["v2"]:
            L.append(f"| {c} | {pct(resultados['v1'][c]['confianza_media_si_falla'])} | {pct(resultados['v2'][c]['confianza_media_si_falla'])} |")
    L.append("\n## Con los umbrales del servicio (confianza >= 55 % y margen >= 8 puntos)\n")
    L.append("| Conjunto | v1 se entrega | v1 acierta si entrega | v1 errores entregados | v2 se entrega | v2 acierta si entrega | v2 errores entregados |")
    L.append("|---|---|---|---|---|---|---|")
    for c in ("tanzania_test", "peru_cajamarca", "plantdoc_papa"):
        if c not in resultados["v2"]:
            continue
        a, b = resultados["v1"][c]["servicio"], resultados["v2"][c]["servicio"]
        L.append(f"| {c} | {pct(a['cobertura'])} | {pct(a['accuracy_si_se_entrega'])} | {a['diagnosticos_erroneos_entregados']} | {pct(b['cobertura'])} | {pct(b['accuracy_si_se_entrega'])} | {b['diagnosticos_erroneos_entregados']} |")
    if "otros_cultivos" in resultados["v2"]:
        a, b = resultados["v1"]["otros_cultivos"], resultados["v2"]["otros_cultivos"]
        L.append("\n## Hojas de otros cultivos (no deben diagnosticarse como papa)\n")
        L.append(f"n = {b['n']}\n")
        L.append("| Métrica | v1 | v2 |")
        L.append("|---|---|---|")
        L.append(f"| Rechazadas como \"no es papa\" | {pct(a['clasificadas_como_no_papa'])} (v1 no tiene esa clase) | {pct(b['clasificadas_como_no_papa'])} |")
        L.append(f"| **Falso diagnóstico de papa entregado** | {pct(a['falso_diagnostico_entregado'])} | {pct(b['falso_diagnostico_entregado'])} |")
    if "filtros_servicio" in resultados:
        L.append("\n## Filtros de calidad del servicio (antes de la red)\n")
        L.append("| Conjunto | n | rechazadas por los filtros |")
        L.append("|---|---|---|")
        for c, d in resultados["filtros_servicio"].items():
            L.append(f"| {c} | {d['n']} | {pct(d['rechazadas'])} |")
    return "\n".join(L) + "\n"


def filtros_servicio(archivos: dict[str, list[Path]]) -> dict:
    """Aplica solo los filtros de calidad/vegetación del servicio (sin la red)."""
    sys.path.insert(0, str(BASE_DIR))
    from app.inference import InvalidImageError, ModelRuntime  # noqa: E402

    rt = ModelRuntime()
    salida = {}
    for nombre, lista in archivos.items():
        rechazadas, motivos = 0, {}
        for f in lista:
            try:
                imagen = rt._decode_image(f.read_bytes())
                calidad = rt._validate_basic_quality(imagen)
                rt._validate_probable_vegetation(imagen)
                rt._validate_sharpness(calidad)
            except InvalidImageError as exc:
                rechazadas += 1
                clave = str(exc)[:45]
                motivos[clave] = motivos.get(clave, 0) + 1
        salida[nombre] = {"n": len(lista), "rechazadas": rechazadas / max(1, len(lista)), "motivos": motivos}
    return salida


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--v1-model", type=Path, default=BASE_DIR / "model" / "potato_resnet50.keras")
    p.add_argument("--v1-meta", type=Path, default=BASE_DIR / "model" / "model_metadata.json")
    p.add_argument("--v2-model", type=Path, default=MODEL_DIR / "potato_resnet50_v2.keras")
    p.add_argument("--v2-meta", type=Path, default=MODEL_DIR / "model_metadata.json")
    p.add_argument("--out", type=Path, default=ARTIFACTS_DIR)
    p.add_argument("--data-dir", type=Path, default=DATA_DIR, help="carpeta con test/ y external/ (por defecto dataset_v2; v2b usa dataset_v2b)")
    p.add_argument("--con-filtros", action="store_true", help="medir también el rechazo de los filtros de calidad")
    args = p.parse_args()
    args.out.mkdir(parents=True, exist_ok=True)

    conjuntos = {
        "tanzania_test": listar(args.data_dir / "test", POTATO),
        "peru_cajamarca": listar(args.data_dir / "external" / "peru_cajamarca", ["healthy", "late_blight"]),
        "plantdoc_papa": listar(args.data_dir / "external" / "plantdoc_potato", ["early_blight", "late_blight"]),
    }
    conjuntos = {n: items for n, items in conjuntos.items() if items}  # omite conjuntos aún vacíos
    otros = listar(args.data_dir / "test", ["other"])
    for nombre, items in conjuntos.items():
        print(f"{nombre}: {len(items)} imágenes", flush=True)
    print(f"otros_cultivos: {len(otros)} imágenes", flush=True)

    resultados: dict = {"v1": {}, "v2": {}}
    for version, ruta_modelo, ruta_meta in (("v1", args.v1_model, args.v1_meta), ("v2", args.v2_model, args.v2_meta)):
        modelo = tf.keras.models.load_model(ruta_modelo)
        nombres = json.loads(ruta_meta.read_text(encoding="utf-8"))["class_names"]
        print(f"\n== {version}: clases {nombres}", flush=True)
        for nombre, items in conjuntos.items():
            prob = predecir(modelo, [f for f, _ in items])
            resultados[version][nombre] = metricas_papa(prob, [c for _, c in items], nombres)
            print(f"  {nombre}: accuracy {resultados[version][nombre]['accuracy']:.1%}", flush=True)
        if otros:
            prob = predecir(modelo, [f for f, _ in otros])
            resultados[version]["otros_cultivos"] = metricas_otros(prob, nombres)

    if args.con_filtros:
        print("\nMidiendo filtros de calidad del servicio...", flush=True)
        muestras = {n: [f for f, _ in items] for n, items in conjuntos.items()}
        muestras["otros_cultivos"] = [f for f, _ in otros]
        resultados["filtros_servicio"] = filtros_servicio(muestras)

    (args.out / "evaluation_v1_vs_v2.json").write_text(json.dumps(resultados, ensure_ascii=False, indent=2), encoding="utf-8")
    texto = informe(resultados)
    (args.out / "evaluation_v1_vs_v2.md").write_text(texto, encoding="utf-8")
    graficar(resultados, args.out / "confusion_matrices_v1_vs_v2.png")
    print("\n" + texto)


if __name__ == "__main__":
    main()
