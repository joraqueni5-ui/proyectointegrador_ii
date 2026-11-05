from __future__ import annotations

import argparse
import json
from pathlib import Path

import matplotlib.pyplot as plt
import numpy as np
import tensorflow as tf
from sklearn.metrics import classification_report, confusion_matrix


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Evalúa el modelo CNN sobre el 15% de prueba (fase Evaluación de CRISP-DM)."
    )
    base_dir = Path(__file__).resolve().parents[1]
    parser.add_argument("--data", type=Path, default=base_dir / "dataset" / "processed" / "test")
    parser.add_argument("--model", type=Path, default=base_dir / "model" / "potato_resnet50.keras")
    parser.add_argument("--metadata", type=Path, default=base_dir / "model" / "model_metadata.json")
    parser.add_argument("--output", type=Path, default=base_dir / "artifacts")
    parser.add_argument("--batch-size", type=int, default=32)
    args = parser.parse_args()

    if not args.model.exists() or not args.metadata.exists():
        raise SystemExit("No existe el modelo entrenado o sus metadatos.")

    with args.metadata.open("r", encoding="utf-8") as fh:
        metadata = json.load(fh)

    image_size = metadata.get("image_size", [224, 224])
    class_names = metadata["class_names"]

    test_ds = tf.keras.utils.image_dataset_from_directory(
        args.data,
        image_size=(int(image_size[0]), int(image_size[1])),
        batch_size=args.batch_size,
        label_mode="int",
        shuffle=False,
    )

    model = tf.keras.models.load_model(args.model)

    y_true: list[int] = []
    y_pred: list[int] = []

    for images, labels in test_ds:
        # El modelo guardado ya contiene el preprocesamiento de ResNet50.
        probabilities = model.predict(images, verbose=0)
        predictions = np.argmax(probabilities, axis=1)
        y_true.extend(labels.numpy().tolist())
        y_pred.extend(predictions.tolist())

    report = classification_report(
        y_true,
        y_pred,
        labels=list(range(len(class_names))),
        target_names=class_names,
        output_dict=True,
        zero_division=0,
    )
    matrix = confusion_matrix(
        y_true,
        y_pred,
        labels=list(range(len(class_names))),
    )

    args.output.mkdir(parents=True, exist_ok=True)

    with (args.output / "metrics.json").open("w", encoding="utf-8") as fh:
        json.dump(report, fh, ensure_ascii=False, indent=2)

    with (args.output / "confusion_matrix.json").open("w", encoding="utf-8") as fh:
        json.dump(
            {
                "class_names": class_names,
                "matrix": matrix.tolist(),
            },
            fh,
            ensure_ascii=False,
            indent=2,
        )

    fig, ax = plt.subplots(figsize=(7, 6))
    image = ax.imshow(matrix)
    fig.colorbar(image, ax=ax)
    ax.set_xticks(range(len(class_names)), labels=class_names, rotation=45, ha="right")
    ax.set_yticks(range(len(class_names)), labels=class_names)
    ax.set_xlabel("Predicción")
    ax.set_ylabel("Clase real")
    ax.set_title("Matriz de confusión")

    threshold = matrix.max() / 2 if matrix.size else 0
    for i in range(matrix.shape[0]):
        for j in range(matrix.shape[1]):
            ax.text(
                j,
                i,
                str(matrix[i, j]),
                ha="center",
                va="center",
                color="white" if matrix[i, j] > threshold else "black",
            )

    fig.tight_layout()
    fig.savefig(args.output / "confusion_matrix.png", dpi=160)
    plt.close(fig)

    print(json.dumps(report, ensure_ascii=False, indent=2))
    print(f"Resultados guardados en: {args.output}")


if __name__ == "__main__":
    main()
