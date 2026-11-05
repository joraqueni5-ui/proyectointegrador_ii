"""Entrena el prototipo CNN v2 (ResNet50 + Transfer Learning con fine-tuning).

Diferencias frente a v1 (training/train.py):
  * Datos de campo (Tanzania) + clase "other" (hojas de otros cultivos, PlantDoc).
  * Aumento de datos más fuerte: giros de 90°, volteo vertical, brillo, traslación.
  * Dos etapas: (1) solo la cabeza con la base congelada, (2) fine-tuning del
    último bloque (conv5) con tasa de aprendizaje baja.
  * Label smoothing y calibración por temperatura: la temperatura se incorpora
    al modelo guardado, de modo que el servicio no necesita cambios para usarla.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import tensorflow as tf  # noqa: E402
from scipy.optimize import minimize_scalar  # noqa: E402

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import ARTIFACTS_DIR, CLASS_MAPPING, CLASS_NAMES, DATA_DIR, MODEL_DIR  # noqa: E402

MODEL_VERSION = "PROINPA-ResNet50-v2.0-prototipo"  # v2b se etiqueta con --version


def cargar(directorio: Path, size: int, batch: int, shuffle: bool, seed: int):
    return tf.keras.utils.image_dataset_from_directory(
        directorio,
        class_names=CLASS_NAMES,
        image_size=(size, size),
        batch_size=batch,
        label_mode="categorical",
        shuffle=shuffle,
        seed=seed,
    )


def contar(directorio: Path) -> dict[str, int]:
    return {c: sum(1 for _ in (directorio / c).glob("*.jpg")) for c in CLASS_NAMES}


# Capas que se descongelan en el fine-tuning (prefijos de nombre) por arquitectura.
BACKBONES = {
    "resnet50": {
        "arquitectura": "ResNet50",
        "fine_tune": ("conv5_",),
        "preproceso": "tensorflow.keras.applications.resnet50.preprocess_input (incluido en el modelo)",
    },
    "efficientnetb0": {
        "arquitectura": "EfficientNetB0",
        "fine_tune": ("block6", "block7", "top_conv"),
        "preproceso": "Rescaling/Normalization propios de EfficientNetB0 (incluidos en el modelo; entrada 0-255)",
    },
}


def construir(size: int, zoom: float = 0.2, backbone: str = "resnet50", color: bool = True):
    L = tf.keras.layers
    capas_aumento = [
        L.RandomFlip("horizontal_and_vertical"),
        L.RandomRotation(0.25, fill_mode="reflect"),  # ±90°
        L.RandomZoom(zoom),
        L.RandomTranslation(0.1, 0.1),
        L.RandomContrast(0.25),
        L.RandomBrightness(0.25, value_range=(0, 255)),
    ]
    if color:
        # Hojas amarillas, secas o con otra iluminación: cambia tono y saturación
        # para que una hoja muy enferma (poco verde) no se confunda con "no es papa".
        capas_aumento.append(
            L.RandomColorJitter(
                value_range=(0, 255), brightness_factor=0.0, contrast_factor=0.0,
                saturation_factor=(0.35, 0.65), hue_factor=0.06,
            )
        )
    aumento = tf.keras.Sequential(capas_aumento, name="data_augmentation")
    if backbone == "resnet50":
        base = tf.keras.applications.ResNet50(
            weights="imagenet", include_top=False, input_shape=(size, size, 3)
        )
    elif backbone == "efficientnetb0":
        base = tf.keras.applications.EfficientNetB0(
            weights="imagenet", include_top=False, input_shape=(size, size, 3)
        )
    else:
        raise SystemExit(f"Arquitectura desconocida: {backbone}")
    base.trainable = False

    entrada = tf.keras.Input(shape=(size, size, 3), name="imagen")
    x = aumento(entrada)
    if backbone == "resnet50":
        x = tf.keras.applications.resnet50.preprocess_input(x)
    x = base(x, training=False)
    x = L.GlobalAveragePooling2D()(x)
    x = L.Dense(256, activation="relu")(x)
    x = L.Dropout(0.4)(x)
    logits = L.Dense(len(CLASS_NAMES), name="logits")(x)
    salida = L.Softmax(name="clasificacion")(logits)
    return tf.keras.Model(entrada, salida, name=f"PROINPA_{BACKBONES[backbone]['arquitectura']}_v2"), base


def compilar(modelo, lr: float) -> None:
    modelo.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=lr),
        loss=tf.keras.losses.CategoricalCrossentropy(label_smoothing=0.05),
        metrics=["accuracy"],
    )


def ece(probabilidades: np.ndarray, etiquetas: np.ndarray, bins: int = 10) -> float:
    confianza = probabilidades.max(axis=1)
    acierto = probabilidades.argmax(axis=1) == etiquetas
    total, error = len(etiquetas), 0.0
    for i in range(bins):
        m = (confianza > i / bins) & (confianza <= (i + 1) / bins)
        if m.any():
            error += m.sum() / total * abs(acierto[m].mean() - confianza[m].mean())
    return float(error)


def nll(logits: np.ndarray, etiquetas: np.ndarray, temperatura: float) -> float:
    z = logits / temperatura
    z = z - z.max(axis=1, keepdims=True)
    logp = z - np.log(np.exp(z).sum(axis=1, keepdims=True))
    return float(-logp[np.arange(len(etiquetas)), etiquetas].mean())


def softmax(logits: np.ndarray, temperatura: float = 1.0) -> np.ndarray:
    z = logits / temperatura
    z = z - z.max(axis=1, keepdims=True)
    e = np.exp(z)
    return e / e.sum(axis=1, keepdims=True)


def graficar(historiales: list[dict], destino: Path) -> None:
    acc, val_acc, loss, val_loss = [], [], [], []
    for h in historiales:
        acc += h.get("accuracy", [])
        val_acc += h.get("val_accuracy", [])
        loss += h.get("loss", [])
        val_loss += h.get("val_loss", [])
    fig, ejes = plt.subplots(1, 2, figsize=(11, 4))
    ejes[0].plot(acc, label="train")
    ejes[0].plot(val_acc, label="val")
    ejes[0].set_title("Accuracy")
    ejes[1].plot(loss, label="train")
    ejes[1].plot(val_loss, label="val")
    ejes[1].set_title("Loss")
    for eje in ejes:
        eje.set_xlabel("Época (etapa 1 + etapa 2)")
        eje.legend()
    fig.tight_layout()
    fig.savefig(destino, dpi=140)
    plt.close(fig)


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--data", type=Path, default=DATA_DIR)
    p.add_argument("--out", type=Path, default=MODEL_DIR)
    p.add_argument("--artifacts", type=Path, default=ARTIFACTS_DIR)
    p.add_argument("--image-size", type=int, default=224)
    p.add_argument("--batch-size", type=int, default=32)
    p.add_argument("--epochs-head", type=int, default=8)
    p.add_argument("--epochs-finetune", type=int, default=4)
    p.add_argument("--lr-head", type=float, default=1e-3)
    p.add_argument("--lr-finetune", type=float, default=2e-5)
    p.add_argument("--zoom", type=float, default=0.2, help="rango del zoom aleatorio (±)")
    p.add_argument("--backbone", choices=sorted(BACKBONES), default="resnet50")
    p.add_argument("--no-color-aug", action="store_true", help="desactiva el aumento de tono/saturación")
    p.add_argument("--seed", type=int, default=42)
    p.add_argument("--version", default=MODEL_VERSION)
    p.add_argument("--smoke", action="store_true", help="prueba rápida (2 lotes, 1 época)")
    args = p.parse_args()

    tf.keras.utils.set_random_seed(args.seed)
    args.out.mkdir(parents=True, exist_ok=True)
    args.artifacts.mkdir(parents=True, exist_ok=True)

    train = cargar(args.data / "train", args.image_size, args.batch_size, True, args.seed)
    val = cargar(args.data / "val", args.image_size, args.batch_size, False, args.seed)
    if train.class_names != CLASS_NAMES:
        raise SystemExit(f"Clases inesperadas: {train.class_names}")

    conteos = contar(args.data / "train")
    total = sum(conteos.values())
    pesos = {i: total / (len(CLASS_NAMES) * conteos[c]) for i, c in enumerate(CLASS_NAMES)}
    print("train:", conteos, "| pesos:", {c: round(pesos[i], 3) for i, c in enumerate(CLASS_NAMES)}, flush=True)

    if args.smoke:
        train, val = train.take(2), val.take(1)
        args.epochs_head = args.epochs_finetune = 1

    train = train.prefetch(tf.data.AUTOTUNE)
    val = val.prefetch(tf.data.AUTOTUNE)

    modelo, base = construir(args.image_size, args.zoom, args.backbone, not args.no_color_aug)
    historiales: list[dict] = []
    inicio = time.time()

    # ---------------- Etapa 1: cabeza con la base congelada
    compilar(modelo, args.lr_head)
    print("\n=== Etapa 1: entrenando la cabeza (base congelada) ===", flush=True)
    h1 = modelo.fit(
        train, validation_data=val, epochs=args.epochs_head, class_weight=pesos, verbose=2,
        callbacks=[
            tf.keras.callbacks.EarlyStopping(monitor="val_loss", patience=3, restore_best_weights=True),
            tf.keras.callbacks.ReduceLROnPlateau(monitor="val_loss", factor=0.3, patience=1, min_lr=1e-5),
        ],
    )
    historiales.append(h1.history)
    t_etapa1 = time.time() - inicio

    # ---------------- Etapa 2: fine-tuning del último bloque (conv5)
    base.trainable = True
    entrenables = 0
    for capa in base.layers:
        capa.trainable = capa.name.startswith(BACKBONES[args.backbone]["fine_tune"]) and not isinstance(
            capa, tf.keras.layers.BatchNormalization
        )
        entrenables += int(capa.trainable)
    print(f"\n=== Etapa 2: fine-tuning ({entrenables} capas entrenables) ===", flush=True)
    compilar(modelo, args.lr_finetune)
    h2 = modelo.fit(
        train, validation_data=val, epochs=args.epochs_finetune, class_weight=pesos, verbose=2,
        callbacks=[
            tf.keras.callbacks.EarlyStopping(monitor="val_loss", patience=2, restore_best_weights=True),
        ],
    )
    historiales.append(h2.history)
    duracion = time.time() - inicio

    # ---------------- Calibración por temperatura (sobre validación)
    logits_modelo = tf.keras.Model(modelo.input, modelo.get_layer("logits").output)
    val_eval = cargar(args.data / "val", args.image_size, args.batch_size, False, args.seed)
    if args.smoke:
        val_eval = val_eval.take(1)
    etiquetas = np.concatenate([y.numpy().argmax(axis=1) for _, y in val_eval])
    logits = logits_modelo.predict(val_eval, verbose=0)
    resultado = minimize_scalar(lambda t: nll(logits, etiquetas, t), bounds=(0.3, 10.0), method="bounded")
    temperatura = float(resultado.x)
    calibracion = {
        "temperatura": round(temperatura, 4),
        "nll_antes": round(nll(logits, etiquetas, 1.0), 4),
        "nll_despues": round(nll(logits, etiquetas, temperatura), 4),
        "ece_antes": round(ece(softmax(logits), etiquetas), 4),
        "ece_despues": round(ece(softmax(logits, temperatura), etiquetas), 4),
        "muestras_validacion": int(len(etiquetas)),
    }
    print("\nCalibración:", calibracion, flush=True)

    # La temperatura queda dentro del modelo: logits / T -> softmax.
    escalado = tf.keras.layers.Rescaling(1.0 / temperatura, name="temperatura")(modelo.get_layer("logits").output)
    probabilidades = tf.keras.layers.Softmax(name="clasificacion_calibrada")(escalado)
    final = tf.keras.Model(modelo.input, probabilidades, name=f"PROINPA_{BACKBONES[args.backbone]['arquitectura']}_v2_calibrado")

    ruta_modelo = args.out / f"potato_{args.backbone}_v2.keras"
    final.save(ruta_modelo)

    # Verificación: el modelo guardado debe dar las mismas probabilidades calibradas.
    recargado = tf.keras.models.load_model(ruta_modelo)
    lote = next(iter(val_eval.take(1)))[0]
    esperado = softmax(logits_modelo.predict(lote, verbose=0), temperatura)
    obtenido = recargado.predict(lote, verbose=0)
    diferencia = float(np.abs(esperado - obtenido).max())
    print(f"Verificación del modelo guardado: diferencia máxima {diferencia:.2e}", flush=True)
    if diferencia > 1e-3:
        raise SystemExit("El modelo guardado no reproduce la calibración esperada.")

    metadata = {
        "model_version": args.version,
        "architecture": BACKBONES[args.backbone]["arquitectura"],
        "strategy": "Transfer Learning + fine-tuning (último bloque)",
        "image_size": [args.image_size, args.image_size],
        "class_names": CLASS_NAMES,
        "class_mapping": CLASS_MAPPING,
        "preprocessing": BACKBONES[args.backbone]["preproceso"],
        "color_augmentation": not args.no_color_aug,
        "calibration": calibracion,
        "class_counts_train": conteos,
        "class_weights": {c: round(pesos[i], 4) for i, c in enumerate(CLASS_NAMES)},
        "datasets": json.loads((args.data / "DATASETS.json").read_text(encoding="utf-8"))
        if (args.data / "DATASETS.json").exists()
        else {
            "train_val_test": "Irish Potato Imagery Dataset, Tanzania (Zenodo 8286529, CC BY 4.0), muestra por bloques",
            "other": "PlantDoc (CC BY 4.0), hojas de otros cultivos",
            "external_test": "Cajamarca-Perú (Mendeley kh8xjh2zyh) y PlantDoc papa",
        },
        "training": {
            "epochs_head": len(h1.history["loss"]),
            "epochs_finetune": len(h2.history["loss"]),
            "seconds_total": round(duracion),
            "seconds_stage1": round(t_etapa1),
            "lr_head": args.lr_head,
            "lr_finetune": args.lr_finetune,
            "label_smoothing": 0.05,
            "seed": args.seed,
        },
        "nota": "Prototipo entrenado sin fotos de Bolivia. Validar con fotos reales antes de uso productivo.",
    }
    (args.out / "model_metadata.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2), encoding="utf-8")
    (args.artifacts / "training_history_v2.json").write_text(
        json.dumps([{k: [float(v) for v in vals] for k, vals in h.items()} for h in historiales], indent=2),
        encoding="utf-8",
    )
    graficar(historiales, args.artifacts / "training_curves_v2.png")
    print(f"\nModelo guardado en {ruta_modelo} (entrenamiento: {duracion/60:.1f} min)", flush=True)


if __name__ == "__main__":
    main()
