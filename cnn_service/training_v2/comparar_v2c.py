"""Etapa 7: ResNet50 vs EfficientNetB0 (v2c) con la MISMA partición de datos.

Para cada modelo mide, sobre los conjuntos que ninguno vio al entrenar:
  * accuracy / F1 macro por conjunto y matriz de confusión (PNG);
  * prueba de McNemar (con corrección de continuidad y exacta binomial si hay pocos discordantes)
    sobre las predicciones pareadas de ResNet50 y EfficientNetB0;
  * tamaño del archivo, parámetros y tiempo de inferencia en CPU (1 imagen, mediana de N repeticiones).

NO cambia el modelo activo del sistema: solo lee modelos y escribe en artifacts_v2c/.

Uso:
  python training_v2/comparar_v2c.py \
     --modelo resnet50=model_v2c_resnet50/potato_resnet50_v2.keras,model_v2c_resnet50/model_metadata.json \
     --modelo efficientnetb0=model_v2c_effnet/potato_efficientnetb0_v2.keras,model_v2c_effnet/model_metadata.json
"""

from __future__ import annotations

import argparse
import json
import math
import os
import sys
import time
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import tensorflow as tf  # noqa: E402

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import BASE_DIR  # noqa: E402
from evaluate_v2 import NOMBRES, POTATO, cargar_imagen, listar, metricas_papa, predecir  # noqa: E402

DATA = BASE_DIR / "dataset_v2c"


def conjuntos() -> dict[str, list[tuple[Path, str]]]:
    c = {
        "tanzania_test": listar(DATA / "test", POTATO),
        "etiopia_holeta": listar(DATA / "external" / "etiopia_holeta", ["healthy", "late_blight"]),
        "peru_reserva": listar(DATA / "external" / "peru_cajamarca", ["healthy", "late_blight"]),
        "plantdoc_papa": listar(DATA / "external" / "plantdoc_potato", ["early_blight", "late_blight"]),
        "otros_cultivos": listar(DATA / "test", ["other"]),
    }
    return {k: v for k, v in c.items() if v}


def mcnemar(a_ok: np.ndarray, b_ok: np.ndarray) -> dict:
    """a_ok/b_ok: aciertos (bool) por muestra de los modelos A y B."""
    b = int((a_ok & ~b_ok).sum())  # A acierta, B falla
    c = int((~a_ok & b_ok).sum())  # A falla, B acierta
    n = b + c
    if n == 0:
        return {"solo_A_acierta": b, "solo_B_acierta": c, "chi2": 0.0, "p_valor": 1.0, "metodo": "sin discordantes"}
    # exacta binomial bilateral (válida siempre; es la recomendada cuando n < 25)
    k = min(b, c)
    cola = sum(math.comb(n, i) for i in range(0, k + 1)) / 2**n
    p_exacto = min(1.0, 2 * cola)
    chi2 = (abs(b - c) - 1) ** 2 / n
    # p de chi-cuadrado con 1 g.l.: erfc(sqrt(chi2/2))
    p_chi = math.erfc(math.sqrt(chi2 / 2))
    return {
        "solo_A_acierta": b, "solo_B_acierta": c, "discordantes": n,
        "chi2_con_correccion": round(chi2, 4), "p_chi2": round(p_chi, 6), "p_exacto": round(p_exacto, 6),
        "p_valor": round(p_exacto if n < 25 else p_chi, 6),
        "metodo": "exacta binomial" if n < 25 else "chi-cuadrado con corrección de continuidad",
    }


def tiempo_cpu(modelo, repeticiones: int = 30) -> dict:
    x = np.expand_dims(cargar_imagen(next((DATA / "test" / "healthy").glob("*.jpg"))), 0)
    for _ in range(3):  # calentamiento
        modelo.predict(x, verbose=0)
    t = []
    for _ in range(repeticiones):
        i = time.perf_counter()
        modelo.predict(x, verbose=0)
        t.append((time.perf_counter() - i) * 1000)
    t = np.array(t)
    return {"mediana_ms": round(float(np.median(t)), 1), "p95_ms": round(float(np.percentile(t, 95)), 1),
            "repeticiones": repeticiones}


def matriz_png(matriz: dict, titulo: str, destino: Path) -> None:
    v = np.array(matriz["valores"])
    fig, ax = plt.subplots(figsize=(5.2, 4.2))
    ax.imshow(v, cmap="Greens")
    ax.set_xticks(range(len(matriz["columnas_pred"])))
    ax.set_xticklabels([NOMBRES.get(c, c) for c in matriz["columnas_pred"]], rotation=30, ha="right", fontsize=8)
    ax.set_yticks(range(len(matriz["filas_reales"])))
    ax.set_yticklabels([NOMBRES.get(c, c) for c in matriz["filas_reales"]], fontsize=8)
    for i in range(v.shape[0]):
        for j in range(v.shape[1]):
            ax.text(j, i, str(v[i, j]), ha="center", va="center", fontsize=9,
                    color="white" if v[i, j] > v.max() / 2 else "black")
    ax.set_xlabel("Predicho")
    ax.set_ylabel("Real")
    ax.set_title(titulo, fontsize=10)
    fig.tight_layout()
    fig.savefig(destino, dpi=140)
    plt.close(fig)


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--modelo", action="append", required=True, help="nombre=ruta_modelo,ruta_metadatos")
    p.add_argument("--out", type=Path, default=BASE_DIR / "artifacts_v2c")
    args = p.parse_args()
    args.out.mkdir(parents=True, exist_ok=True)
    conj = conjuntos()
    print({k: len(v) for k, v in conj.items()}, flush=True)

    res: dict = {}
    aciertos: dict[str, dict[str, np.ndarray]] = {}
    for spec in args.modelo:
        nombre, resto = spec.split("=", 1)
        ruta, meta = resto.split(",")
        modelo = tf.keras.models.load_model(ruta)
        clases = json.loads(Path(meta).read_text(encoding="utf-8"))["class_names"]
        r: dict = {
            "archivo_mb": round(Path(ruta).stat().st_size / 1e6, 1),
            "parametros_totales": int(modelo.count_params()),
            "cpu": tiempo_cpu(modelo),
            "conjuntos": {},
        }
        aciertos[nombre] = {}
        for k, items in conj.items():
            prob = predecir(modelo, [f for f, _ in items])
            pred = prob.argmax(axis=1)
            if k == "otros_cultivos":
                ok = pred == clases.index("other")
                r["conjuntos"][k] = {"n": len(items), "rechazadas_como_no_papa": round(float(ok.mean()), 4)}
            else:
                y = np.array([clases.index(c) for _, c in items])
                ok = pred == y
                m = metricas_papa(prob, [c for _, c in items], clases)
                r["conjuntos"][k] = {"n": m["n"], "accuracy": m["accuracy"], "f1_macro": m["f1_macro"],
                                     "por_clase": m["por_clase"], "ece": m["ece"], "matriz": m["matriz"]}
                matriz_png(m["matriz"], f"{nombre} · {k}", args.out / f"matriz_{nombre}_{k}.png")
            aciertos[nombre][k] = ok
            print(f"  {nombre} {k}: {ok.mean():.1%}", flush=True)
        res[nombre] = r
        # guarda predicciones por muestra (auditoría)
        del modelo
        tf.keras.backend.clear_session()

    nombres = list(res)
    mc: dict = {}
    for i in range(len(nombres)):
        for j in range(i + 1, len(nombres)):
            x, y = nombres[i], nombres[j]
            par = {k: mcnemar(aciertos[x][k], aciertos[y][k]) for k in conj}
            par["todos_los_conjuntos"] = mcnemar(
                np.concatenate([aciertos[x][k] for k in conj]), np.concatenate([aciertos[y][k] for k in conj]))
            mc[f"{x} vs {y}"] = par

    salida = {"modelos": res, "mcnemar": mc}
    (args.out / "comparacion_modelos.json").write_text(
        json.dumps(salida, ensure_ascii=False, indent=2), encoding="utf-8")

    L = ["# Comparación de modelos: v2b (activo), v2c ResNet50 y v2c EfficientNetB0\n",
         "## Tamaño, parámetros y velocidad (CPU, 1 imagen)\n",
         "| Modelo | Archivo (MB) | Parámetros | Mediana (ms) | P95 (ms) |", "|---|---|---|---|---|"]
    for n in nombres:
        r = res[n]
        L.append(f"| {n} | {r['archivo_mb']} | {r['parametros_totales']:,} | {r['cpu']['mediana_ms']} | {r['cpu']['p95_ms']} |")
    L += ["\n## Accuracy por conjunto (no vistos al entrenar)\n", "| Conjunto | n | " + " | ".join(nombres) + " |",
          "|---|---|" + "---|" * len(nombres)]
    for k in conj:
        def celda(n):
            c = res[n]["conjuntos"][k]
            return f"{c['accuracy']:.1%}" if "accuracy" in c else f"{c['rechazadas_como_no_papa']:.1%} rechazadas"
        L.append(f"| {k} | {len(conj[k])} | " + " | ".join(celda(n) for n in nombres) + " |")
    L += ["\n## F1 macro\n", "| Conjunto | " + " | ".join(nombres) + " |", "|---|" + "---|" * len(nombres)]
    for k in conj:
        if k == "otros_cultivos":
            continue
        L.append(f"| {k} | " + " | ".join(f"{res[n]['conjuntos'][k]['f1_macro']:.3f}" for n in nombres) + " |")
    for par, pruebas in mc.items():
        x, y = par.split(" vs ")
        L += [f"\n## Prueba de McNemar: {x} (A) vs {y} (B)\n",
              "H0: ambos modelos tienen la misma tasa de error. Diferencia significativa si p < 0,05.\n",
              "| Conjunto | Solo A acierta | Solo B acierta | p-valor | Método | ¿Significativa? |", "|---|---|---|---|---|---|"]
        for k, v in pruebas.items():
            L.append(f"| {k} | {v['solo_A_acierta']} | {v['solo_B_acierta']} | {v['p_valor']} | {v['metodo']} | "
                     f"{'sí' if v['p_valor'] < 0.05 else 'no'} |")
    (args.out / "comparacion_modelos.md").write_text("\n".join(L) + "\n", encoding="utf-8")
    print("\n".join(L))


if __name__ == "__main__":
    main()
