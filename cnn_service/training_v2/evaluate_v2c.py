"""Compara varios modelos sobre los conjuntos de prueba de dataset_v2c.

Conjuntos (ninguno entra al entrenamiento de v2c):
  * tanzania_test     : bloques de Tanzania reservados (misma fuente que el entrenamiento).
  * etiopia_holeta    : FUENTE COMPLETA reservada (Etiopía). Mide generalización real a una
                        parcela y cámara nuevas, con hojas sanas y con tizón tardío.
  * peru_reserva      : parte reservada de Cajamarca (bloques; posible parecido con el entrenamiento).
  * plantdoc_papa     : papa de PlantDoc reservada (fotos de internet, etiquetas con ruido).
  * inat_papa         : papa de iNaturalist reservada, SIN etiqueta de enfermedad. Se mide solo
                        si el modelo la acepta como papa (no "other"). Mide el error
                        "rechaza una hoja de papa sana de internet".
  * otros_cultivos    : hojas que no son de papa (PlantDoc + iNaturalist): no deben diagnosticarse.

Un modelo solo se evalúa en conjuntos que no vio al entrenar (--vio lista los que sí vio).
Uso:
  python training_v2/evaluate_v2c.py --out artifacts_v2c \
     --modelo v1=model/potato_resnet50.keras,model/model_metadata.json \
     --modelo v2b=model_v2b/potato_resnet50_v2.keras,model_v2b/model_metadata.json,vio=tanzania_test \
     --modelo v2c_resnet=model_v2c/potato_resnet50_v2.keras,model_v2c/model_metadata.json
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parent))
from evaluate_v2 import (  # noqa: E402
    POTATO, aceptadas, listar, metricas_otros, metricas_papa, predecir,
)
import tensorflow as tf  # noqa: E402
from common import BASE_DIR  # noqa: E402

DATA = BASE_DIR / "dataset_v2c"


def pct(x) -> str:
    return "—" if x is None else f"{x:.1%}"


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--modelo", action="append", required=True,
                   help="nombre=ruta_modelo,ruta_metadatos[,vio=conjunto1+conjunto2]")
    p.add_argument("--out", type=Path, default=BASE_DIR / "artifacts_v2c")
    args = p.parse_args()
    args.out.mkdir(parents=True, exist_ok=True)

    papa = {
        "tanzania_test": listar(DATA / "test", POTATO),
        "etiopia_holeta": listar(DATA / "external" / "etiopia_holeta", ["healthy", "late_blight"]),
        "peru_reserva": listar(DATA / "external" / "peru_cajamarca", ["healthy", "late_blight"]),
        "plantdoc_papa": listar(DATA / "external" / "plantdoc_potato", ["early_blight", "late_blight"]),
    }
    papa = {k: v for k, v in papa.items() if v}
    inat = sorted((DATA / "external" / "inat_papa" / "papa").glob("*.jpg"))
    otros = listar(DATA / "test", ["other"])
    for k, v in papa.items():
        print(f"{k}: {len(v)}", flush=True)
    print(f"inat_papa: {len(inat)} | otros_cultivos: {len(otros)}", flush=True)

    res: dict = {}
    for spec in args.modelo:
        nombre, resto = spec.split("=", 1)
        partes = resto.split(",")
        vio = set(partes[2][4:].split("+")) if len(partes) > 2 and partes[2].startswith("vio=") else set()
        modelo = tf.keras.models.load_model(partes[0])
        clases = json.loads(Path(partes[1]).read_text(encoding="utf-8"))["class_names"]
        print(f"\n== {nombre}: {clases}", flush=True)
        r: dict = {"vio": sorted(vio)}
        for conj, items in papa.items():
            if conj in vio:
                r[conj] = None
                continue
            prob = predecir(modelo, [f for f, _ in items])
            r[conj] = metricas_papa(prob, [c for _, c in items], clases)
            print(f"  {conj}: acc {r[conj]['accuracy']:.1%}", flush=True)
        if inat and "inat_papa" not in vio:
            prob = predecir(modelo, inat)
            pred = prob.argmax(axis=1)
            como_otro = (pred == clases.index("other")) if "other" in clases else np.zeros(len(pred), bool)
            r["inat_papa"] = {
                "n": len(inat),
                "aceptada_como_papa": round(float(1 - como_otro.mean()), 4),
                "entregada_por_servicio": round(float((aceptadas(prob) & ~como_otro).mean()), 4),
            }
            print(f"  inat_papa: aceptada como papa {r['inat_papa']['aceptada_como_papa']:.1%}", flush=True)
        if otros:
            prob = predecir(modelo, [f for f, _ in otros])
            r["otros_cultivos"] = metricas_otros(prob, clases)
        res[nombre] = r

    nombres = list(res)
    L = ["# Evaluación v2c: comparación de modelos\n",
         "Solo el modelo (sin filtros de imagen). \"—\" = el modelo vio ese conjunto al entrenar (no se evalúa).\n",
         "## Accuracy (hojas de papa)\n",
         "| Conjunto | n | " + " | ".join(nombres) + " |", "|---|---|" + "---|" * len(nombres)]
    for conj, items in papa.items():
        L.append(f"| {conj} | {len(items)} | " + " | ".join(pct(res[m][conj]["accuracy"]) if res[m].get(conj) else "—" for m in nombres) + " |")
    L += ["\n## F1 macro\n", "| Conjunto | " + " | ".join(nombres) + " |", "|---|" + "---|" * len(nombres)]
    for conj in papa:
        L.append(f"| {conj} | " + " | ".join(f"{res[m][conj]['f1_macro']:.3f}" if res[m].get(conj) else "—" for m in nombres) + " |")
    L += ["\n## Recall por clase\n", "| Conjunto | Clase | " + " | ".join(nombres) + " |", "|---|---|" + "---|" * len(nombres)]
    for conj, items in papa.items():
        for clase in sorted({c for _, c in items}, key=POTATO.index):
            L.append(f"| {conj} | {clase} | " + " | ".join(
                pct(res[m][conj]["por_clase"].get(clase, {}).get("recall")) if res[m].get(conj) else "—" for m in nombres) + " |")
    L += ["\n## Hoja de papa rechazada como \"no es papa\"\n",
          "| Conjunto | " + " | ".join(nombres) + " |", "|---|" + "---|" * len(nombres)]
    for conj in papa:
        L.append(f"| {conj} | " + " | ".join(pct(res[m][conj]["pred_como_no_papa"]) if res[m].get(conj) and res[m][conj]["pred_como_no_papa"] is not None else "—" for m in nombres) + " |")
    if inat:
        L.append(f"| inat_papa (n={len(inat)}) | " + " | ".join(pct(1 - res[m]["inat_papa"]["aceptada_como_papa"]) if res[m].get("inat_papa") else "—" for m in nombres) + " |")
    L += ["\n## Calibración (ECE, menor es mejor) y errores entregados por el servicio\n",
          "| Conjunto | " + " | ".join(f"{m} ECE / errores entregados" for m in nombres) + " |", "|---|" + "---|" * len(nombres)]
    for conj in papa:
        L.append(f"| {conj} | " + " | ".join(
            f"{res[m][conj]['ece']:.3f} / {res[m][conj]['servicio']['diagnosticos_erroneos_entregados']}" if res[m].get(conj) else "—" for m in nombres) + " |")
    if otros:
        L += [f"\n## Hojas de otros cultivos (n={len(otros)}): falso diagnóstico de papa entregado\n",
              "| " + " | ".join(nombres) + " |", "|" + "---|" * len(nombres),
              "| " + " | ".join(pct(res[m]["otros_cultivos"]["falso_diagnostico_entregado"]) for m in nombres) + " |"]
    texto = "\n".join(L) + "\n"
    (args.out / "evaluation_v2c.md").write_text(texto, encoding="utf-8")
    (args.out / "evaluation_v2c.json").write_text(json.dumps(res, ensure_ascii=False, indent=2), encoding="utf-8")
    print("\n" + texto)


if __name__ == "__main__":
    main()
