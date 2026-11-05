from __future__ import annotations

import argparse
import json
from pathlib import Path

import matplotlib.pyplot as plt
import tensorflow as tf


def load_mapping(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as fh:
        return json.load(fh)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Entrena la CNN ResNet50 usando Transfer Learning (fase de Modelado de CRISP-DM)."
    )
    base_dir = Path(__file__).resolve().parents[1]
    parser.add_argument("--data", type=Path, default=base_dir / "dataset" / "processed")
    parser.add_argument("--model-dir", type=Path, default=base_dir / "model")
    parser.add_argument("--artifacts-dir", type=Path, default=base_dir / "artifacts")
    parser.add_argument("--mapping", type=Path, default=base_dir / "class_mapping.json")
    parser.add_argument("--epochs", type=int, default=20)
    parser.add_argument("--batch-size", type=int, default=32)
    parser.add_argument("--image-size", type=int, default=224)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--model-version", default="PROINPA-ResNet50-v1.0")
    args = parser.parse_args()

    tf.keras.utils.set_random_seed(args.seed)

    train_dir = args.data / "train"
    val_dir = args.data / "val"

    if not train_dir.exists() or not val_dir.exists():
        raise SystemExit(
            "No existe el dataset procesado. Ejecute primero split_dataset.py."
        )

    train_ds = tf.keras.utils.image_dataset_from_directory(
        train_dir,
        image_size=(args.image_size, args.image_size),
        batch_size=args.batch_size,
        label_mode="int",
        shuffle=True,
        seed=args.seed,
    )

    val_ds = tf.keras.utils.image_dataset_from_directory(
        val_dir,
        image_size=(args.image_size, args.image_size),
        batch_size=args.batch_size,
        label_mode="int",
        shuffle=False,
    )

    class_names = list(train_ds.class_names)
    mapping = load_mapping(args.mapping)

    # PlantVillage está desbalanceado en papa (la clase healthy tiene menos imágenes).
    # Se calculan pesos solo con el conjunto de entrenamiento para reducir el sesgo.
    image_extensions = {".jpg", ".jpeg", ".png", ".webp"}
    class_counts = {}
    for class_name in class_names:
        class_dir = train_dir / class_name
        class_counts[class_name] = sum(
            1
            for path in class_dir.rglob("*")
            if path.is_file() and path.suffix.lower() in image_extensions
        )

    total_train = sum(class_counts.values())
    class_weights = {
        index: total_train / (len(class_names) * class_counts[class_name])
        for index, class_name in enumerate(class_names)
        if class_counts[class_name] > 0
    }

    missing = [name for name in class_names if name not in mapping]
    if missing:
        raise SystemExit(
            "Faltan clases en class_mapping.json: " + ", ".join(missing)
        )

    autotune = tf.data.AUTOTUNE
    train_ds = train_ds.prefetch(autotune)
    val_ds = val_ds.prefetch(autotune)

    data_augmentation = tf.keras.Sequential(
        [
            tf.keras.layers.RandomFlip("horizontal"),
            tf.keras.layers.RandomRotation(0.08),
            tf.keras.layers.RandomZoom(0.15),
            tf.keras.layers.RandomContrast(0.15),
        ],
        name="data_augmentation",
    )

    base_model = tf.keras.applications.ResNet50(
        weights="imagenet",
        include_top=False,
        input_shape=(args.image_size, args.image_size, 3),
    )
    base_model.trainable = False

    inputs = tf.keras.Input(shape=(args.image_size, args.image_size, 3), name="imagen")
    x = data_augmentation(inputs)
    x = tf.keras.applications.resnet50.preprocess_input(x)
    x = base_model(x, training=False)
    x = tf.keras.layers.GlobalAveragePooling2D()(x)
    x = tf.keras.layers.Dense(256, activation="relu")(x)
    x = tf.keras.layers.Dropout(0.4)(x)
    outputs = tf.keras.layers.Dense(
        len(class_names), activation="softmax", name="clasificacion"
    )(x)

    model = tf.keras.Model(inputs, outputs, name="PROINPA_ResNet50")
    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-3),
        loss="sparse_categorical_crossentropy",
        metrics=["accuracy"],
    )

    args.model_dir.mkdir(parents=True, exist_ok=True)
    args.artifacts_dir.mkdir(parents=True, exist_ok=True)

    model_path = args.model_dir / "potato_resnet50.keras"

    callbacks = [
        tf.keras.callbacks.EarlyStopping(
            monitor="val_loss", patience=5, restore_best_weights=True
        ),
        tf.keras.callbacks.ReduceLROnPlateau(
            monitor="val_loss", factor=0.3, patience=2, min_lr=1e-6
        ),
        tf.keras.callbacks.ModelCheckpoint(
            model_path, monitor="val_accuracy", save_best_only=True
        ),
    ]

    history = model.fit(
        train_ds,
        validation_data=val_ds,
        epochs=args.epochs,
        callbacks=callbacks,
        class_weight=class_weights,
    )

    # Guarda explícitamente el mejor modelo restaurado.
    model.save(model_path)

    selected_mapping = {name: mapping[name] for name in class_names}
    metadata = {
        "model_version": args.model_version,
        "architecture": "ResNet50",
        "strategy": "Transfer Learning",
        "image_size": [args.image_size, args.image_size],
        "class_names": class_names,
        "class_mapping": selected_mapping,
        "dataset_split": {"train": 0.70, "validation": 0.15, "test": 0.15},
        "class_counts_train": class_counts,
        "class_weights": {class_names[index]: weight for index, weight in class_weights.items()},
        "preprocessing": "tensorflow.keras.applications.resnet50.preprocess_input",
        "seed": args.seed,
    }

    with (args.model_dir / "model_metadata.json").open("w", encoding="utf-8") as fh:
        json.dump(metadata, fh, ensure_ascii=False, indent=2)

    with (args.artifacts_dir / "training_history.json").open("w", encoding="utf-8") as fh:
        json.dump(history.history, fh, indent=2)

    plt.figure(figsize=(8, 5))
    plt.plot(history.history.get("accuracy", []), label="accuracy")
    plt.plot(history.history.get("val_accuracy", []), label="val_accuracy")
    plt.xlabel("Época")
    plt.ylabel("Accuracy")
    plt.legend()
    plt.tight_layout()
    plt.savefig(args.artifacts_dir / "training_accuracy.png", dpi=150)
    plt.close()

    plt.figure(figsize=(8, 5))
    plt.plot(history.history.get("loss", []), label="loss")
    plt.plot(history.history.get("val_loss", []), label="val_loss")
    plt.xlabel("Época")
    plt.ylabel("Loss")
    plt.legend()
    plt.tight_layout()
    plt.savefig(args.artifacts_dir / "training_loss.png", dpi=150)
    plt.close()

    print(f"Modelo guardado en: {model_path}")
    print(f"Metadatos: {args.model_dir / 'model_metadata.json'}")


if __name__ == "__main__":
    main()
