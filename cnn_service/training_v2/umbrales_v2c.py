"""A2: barrido de umbrales (confianza mínima y margen mínimo) para v2c-ResNet50.

REGLA ANTI-FUGA: los umbrales se eligen SOLO con el conjunto de validación (dataset_v2c/val, que incluye
la clase "other" = otros cultivos). Los conjuntos de prueba se predicen y se evalúan UNA sola vez, después
de fijar los umbrales; ninguna cifra de prueba interviene en la elección.

Definiciones (iguales a las del servicio: app/inference.py):
  * una foto se ENTREGA como diagnóstico si confianza_top1 >= C, (top1 - top2) >= M y top1 != "other";
  * foto de papa: cobertura = % entregadas; accuracy entregada = % de entregadas con la clase correcta;
    F1 macro = F1 macro de las 3 clases de papa sobre las entregadas;
  * hoja de otro cultivo: rechazada = NO se entrega (top1 == other, o confianza/margen insuficientes);
  * "acierto del sistema" (para McNemar): papa -> entregada Y con la clase correcta; otro cultivo -> rechazada.

Criterio: mayor F1 macro (papa, en validación) con >= 93 % de otros cultivos rechazados en validación.
Desempate (diferencia de F1 <= 0,002): mayor cobertura. Salida en artifacts_v2c/umbrales/.
"""

from __future__ import annotations

import csv
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

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import BASE_DIR  # noqa: E402
from comparar_v2c import conjuntos, mcnemar  # noqa: E402
from evaluate_v2 import POTATO, listar, predecir  # noqa: E402

DATA = BASE_DIR / "dataset_v2c"
OUT = BASE_DIR / "artifacts_v2c" / "umbrales"
CONFS = list(range(40, 81, 5))
MARGENES = list(range(0, 31, 2))
MIN_RECHAZO = 0.93
MODELOS = {
    "v2c_resnet50": (BASE_DIR / "model_v2c_resnet50" / "potato_resnet50_v2.keras", BASE_DIR / "model_v2c_resnet50" / "model_metadata.json"),
    "v2b_activo": (BASE_DIR / "model_v2b" / "potato_resnet50_v2.keras", BASE_DIR / "model_v2b" / "model_metadata.json"),
    "v2c_efficientnetb0": (BASE_DIR / "model_v2c_effnet" / "potato_efficientnetb0_v2.keras", BASE_DIR / "model_v2c_effnet" / "model_metadata.json"),
}


def top(prob: np.ndarray):
    o = np.sort(prob, axis=1)
    return o[:, -1] * 100, (o[:, -1] - o[:, -2]) * 100


def entregada(prob, clases, c, m):
    t1, mg = top(prob)
    return (t1 >= c) & (mg >= m) & (prob.argmax(axis=1) != clases.index("other"))


def f1_macro(y, pred, idx):
    f = []
    for i in idx:
        tp = ((pred == i) & (y == i)).sum()
        fp = ((pred == i) & (y != i)).sum()
        fn = ((pred != i) & (y == i)).sum()
        p = tp / (tp + fp) if tp + fp else 0.0
        r = tp / (tp + fn) if tp + fn else 0.0
        f.append(2 * p * r / (p + r) if p + r else 0.0)
    return float(np.mean(f))


def evalua_papa(prob, y, clases, c, m):
    ent = entregada(prob, clases, c, m)
    pred = prob.argmax(axis=1)
    idx = [clases.index(k) for k in POTATO]
    ok = ent & (pred == y)
    return {
        "cobertura": float(ent.mean()),
        "accuracy_entregada": float(ok.sum() / ent.sum()) if ent.any() else float("nan"),
        "f1_macro": f1_macro(y[ent], pred[ent], idx) if ent.any() else 0.0,
        "errores_entregados": int((ent & (pred != y)).sum()),
        "acierto": ok,
    }


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    val_papa = listar(DATA / "val", POTATO)
    val_otro = listar(DATA / "val", ["other"])
    print("validación:", len(val_papa), "papa;", len(val_otro), "otros cultivos", flush=True)

    # ---------- FASE 1: solo validación
    modelo = tf.keras.models.load_model(MODELOS["v2c_resnet50"][0])
    clases = json.loads(MODELOS["v2c_resnet50"][1].read_text(encoding="utf-8"))["class_names"]
    pv = predecir(modelo, [f for f, _ in val_papa])
    yv = np.array([clases.index(c) for _, c in val_papa])
    po = predecir(modelo, [f for f, _ in val_otro])
    del modelo
    tf.keras.backend.clear_session()

    filas = []
    for c in CONFS:
        for m in MARGENES:
            r = evalua_papa(pv, yv, clases, c, m)
            rech = float((~entregada(po, clases, c, m)).mean())
            filas.append({"confianza_min": c, "margen_min": m, "cobertura": round(r["cobertura"], 4),
                          "accuracy_entregada": round(r["accuracy_entregada"], 4), "f1_macro": round(r["f1_macro"], 4),
                          "errores_entregados": r["errores_entregados"], "otros_rechazados": round(rech, 4)})
    with open(OUT / "barrido_v2c_resnet50_validacion.csv", "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=list(filas[0]))
        w.writeheader()
        w.writerows(filas)

    validas = [f for f in filas if f["otros_rechazados"] >= MIN_RECHAZO]
    if not validas:
        raise SystemExit("Ninguna combinación rechaza >= 93 % de otros cultivos en validación.")
    mejor_f1 = max(f["f1_macro"] for f in validas)
    cand = [f for f in validas if mejor_f1 - f["f1_macro"] <= 0.002]
    elegido = max(cand, key=lambda f: (f["cobertura"], f["f1_macro"]))
    C, M = elegido["confianza_min"], elegido["margen_min"]
    print("ELEGIDO (validación):", elegido, flush=True)
    ref = next(f for f in filas if f["confianza_min"] == 55 and f["margen_min"] == 8)

    # curva cobertura vs accuracy (validación)
    fig, ax = plt.subplots(figsize=(7.2, 5))
    sc = ax.scatter([f["cobertura"] * 100 for f in filas], [f["accuracy_entregada"] * 100 for f in filas],
                    c=[f["otros_rechazados"] * 100 for f in filas], cmap="viridis", s=18)
    fig.colorbar(sc, label="Otros cultivos rechazados (%)")
    ax.scatter(ref["cobertura"] * 100, ref["accuracy_entregada"] * 100, marker="s", s=90, c="none", edgecolors="red",
               label="Umbrales actuales (55 % / 8)")
    ax.scatter(elegido["cobertura"] * 100, elegido["accuracy_entregada"] * 100, marker="*", s=220, c="orange",
               edgecolors="black", label=f"Elegido ({C} % / {M})")
    ax.set_xlabel("Cobertura: fotos de papa que se entregan (%)")
    ax.set_ylabel("Accuracy de las fotos entregadas (%)")
    ax.set_title("v2c-ResNet50 · validación: cobertura vs accuracy (144 combinaciones)")
    ax.grid(alpha=.3)
    ax.legend(loc="lower left", fontsize=8)
    fig.tight_layout()
    fig.savefig(OUT / "curva_cobertura_accuracy_validacion.png", dpi=140)
    plt.close(fig)

    # ---------- FASE 2: pruebas, una sola vez, con los umbrales ya fijados
    conj = conjuntos()
    config = {"v2c_resnet50 (umbrales nuevos)": ("v2c_resnet50", C, M),
              "v2c_resnet50 (sin umbrales)": ("v2c_resnet50", 0, 0),
              "v2b_activo (55 % / 8)": ("v2b_activo", 55, 8),
              "v2c_efficientnetb0 (55 % / 8)": ("v2c_efficientnetb0", 55, 8)}
    probs, claseslist = {}, {}
    for nombre, (ruta, meta) in MODELOS.items():
        mod = tf.keras.models.load_model(ruta)
        cl = json.loads(meta.read_text(encoding="utf-8"))["class_names"]
        claseslist[nombre] = cl
        probs[nombre] = {k: predecir(mod, [f for f, _ in items]) for k, items in conj.items()}
        del mod
        tf.keras.backend.clear_session()
        print("predicho", nombre, flush=True)

    res, aciertos = {}, {}
    for etiqueta, (nom, c, m) in config.items():
        cl = claseslist[nom]
        res[etiqueta], aciertos[etiqueta] = {}, {}
        for k, items in conj.items():
            p = probs[nom][k]
            if k == "otros_cultivos":
                ok = ~entregada(p, cl, c, m)
                res[etiqueta][k] = {"n": len(items), "rechazadas": round(float(ok.mean()), 4)}
            else:
                y = np.array([cl.index(x) for _, x in items])
                r = evalua_papa(p, y, cl, c, m)
                ok = r["acierto"]
                res[etiqueta][k] = {"n": len(items), "acierto_sistema": round(float(ok.mean()), 4),
                                    "cobertura": round(r["cobertura"], 4),
                                    "accuracy_entregada": round(r["accuracy_entregada"], 4),
                                    "f1_macro_entregadas": round(r["f1_macro"], 4), "errores_entregados": r["errores_entregados"]}
            aciertos[etiqueta][k] = ok

    nuevo = "v2c_resnet50 (umbrales nuevos)"
    mc = {}
    for otro in ("v2b_activo (55 % / 8)", "v2c_efficientnetb0 (55 % / 8)"):
        par = {k: mcnemar(aciertos[nuevo][k], aciertos[otro][k]) for k in conj}
        par["todos_los_conjuntos"] = mcnemar(np.concatenate([aciertos[nuevo][k] for k in conj]),
                                             np.concatenate([aciertos[otro][k] for k in conj]))
        mc[f"{nuevo} vs {otro}"] = par
    (OUT / "evaluacion_prueba_umbrales.json").write_text(json.dumps(
        {"elegido": elegido, "referencia_actual_validacion": ref, "prueba": res, "mcnemar": mc},
        ensure_ascii=False, indent=2), encoding="utf-8")

    L = ["# Umbrales de v2c-ResNet50: barrido en validación y evaluación única en prueba\n",
         f"Validación: {len(val_papa)} fotos de papa y {len(val_otro)} de otros cultivos (`dataset_v2c/val`). "
         "Criterio: mayor F1 macro (3 clases de papa) con ≥ 93 % de otros cultivos rechazados; desempate (≤ 0,002 de F1): mayor cobertura. "
         "Los conjuntos de prueba **no** intervinieron en la elección.\n",
         "## Elección (solo validación)\n", "| Umbrales | Confianza | Margen | Cobertura | Accuracy entregada | F1 macro | Errores entregados | Otros rechazados |", "|---|---|---|---|---|---|---|---|"]
    for nom, f in (("Actuales del servicio", ref), ("**Elegidos**", elegido)):
        L.append(f"| {nom} | {f['confianza_min']} % | {f['margen_min']} | {f['cobertura']:.1%} | {f['accuracy_entregada']:.1%} | {f['f1_macro']:.3f} | {f['errores_entregados']} | {f['otros_rechazados']:.1%} |")
    L += ["\n## Prueba (una sola evaluación)\n",
          "Acierto del sistema = papa: entregada y con la clase correcta · otro cultivo: rechazada.\n",
          "| Conjunto | n | " + " | ".join(config) + " |", "|---|---|" + "---|" * len(config)]
    for k, items in conj.items():
        def cel(e):
            r = res[e][k]
            if k == "otros_cultivos":
                return f"{r['rechazadas']:.1%} rechazadas"
            return f"{r['acierto_sistema']:.1%} (cob. {r['cobertura']:.0%}, acc. entreg. {r['accuracy_entregada']:.1%})"
        L.append(f"| {k} | {len(items)} | " + " | ".join(cel(e) for e in config) + " |")
    for par, pr in mc.items():
        a, b = par.split(" vs ")
        L += [f"\n## McNemar: {a} (A) vs {b} (B)\n", "| Conjunto | Solo A acierta | Solo B acierta | p-valor | ¿Significativa (p<0,05)? |", "|---|---|---|---|---|"]
        for k, v in pr.items():
            L.append(f"| {k} | {v['solo_A_acierta']} | {v['solo_B_acierta']} | {v['p_valor']} | {'sí' if v['p_valor'] < 0.05 else 'no'} |")
    (OUT / "tabla_final_umbrales.md").write_text("\n".join(L) + "\n", encoding="utf-8")
    print("\n".join(L))


if __name__ == "__main__":
    main()
