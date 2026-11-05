from __future__ import annotations

import argparse
import json
import os
from pathlib import Path

import numpy as np
from PIL import Image


os.environ.setdefault(
    "TF_CPP_MIN_LOG_LEVEL",
    "2",
)


IMAGE_EXTENSIONS = {
    ".jpg",
    ".jpeg",
    ".png",
    ".webp",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Genera una referencia OOD a partir de los embeddings "
            "del conjunto de entrenamiento."
        )
    )

    parser.add_argument(
        "--dataset-root",
        type=str,
        default=None,
        help="Ruta a la carpeta dataset.",
    )

    parser.add_argument(
        "--split",
        type=str,
        default="train",
        help="Split que se usará como referencia.",
    )

    parser.add_argument(
        "--batch-size",
        type=int,
        default=32,
    )

    parser.add_argument(
        "--percentile",
        type=float,
        default=99.5,
    )

    parser.add_argument(
        "--margin",
        type=float,
        default=0.03,
    )

    return parser.parse_args()


def find_split_dir(
    dataset_root: Path,
    split: str,
    class_names: list[str],
) -> Path:

    direct = dataset_root / split

    if direct.exists():
        return direct

    candidates = [
        path
        for path in dataset_root.rglob(split)
        if path.is_dir()
    ]

    if not candidates:
        raise RuntimeError(
            f"No se encontró una carpeta '{split}' "
            f"dentro de {dataset_root}"
        )

    best_path = None
    best_score = -1

    for candidate in candidates:

        score = 0

        for class_name in class_names:

            if (
                candidate
                / class_name
            ).exists():

                score += 1

        if score > best_score:
            best_score = score
            best_path = candidate

    if best_path is None:
        raise RuntimeError(
            "No se pudo identificar el conjunto de entrenamiento."
        )

    return best_path


def find_class_dir(
    split_dir: Path,
    class_name: str,
) -> Path:

    direct = (
        split_dir
        / class_name
    )

    if direct.exists():
        return direct

    matches = [
        path
        for path in split_dir.rglob(
            class_name
        )
        if path.is_dir()
    ]

    if not matches:
        raise RuntimeError(
            f"No se encontró la clase "
            f"'{class_name}' en {split_dir}"
        )

    return matches[0]


def find_embedding_layer(
    tf,
    model,
    class_count: int,
):

    dense_layers = [
        layer
        for layer in model.layers
        if isinstance(
            layer,
            tf.keras.layers.Dense,
        )
    ]

    # Normalmente:
    #
    # Dense 256
    # Dropout
    # Dense 3 Softmax
    #
    # Por eso utilizamos la Dense anterior
    # a la capa de clasificación.

    if len(dense_layers) >= 2:

        final_dense = (
            dense_layers[-1]
        )

        for layer in reversed(
            dense_layers[:-1]
        ):

            if (
                getattr(
                    layer,
                    "units",
                    None,
                )
                != class_count
            ):

                return layer

        return dense_layers[-2]

    # Fallback

    if len(model.layers) < 2:

        raise RuntimeError(
            "No fue posible encontrar "
            "una capa adecuada para embeddings."
        )

    return model.layers[-2]


def load_image(
    image_path: Path,
    image_size: tuple[int, int],
) -> np.ndarray:

    with Image.open(
        image_path
    ) as source:

        image = source.convert(
            "RGB"
        )

        image = image.resize(
            image_size,
            Image.Resampling.BILINEAR,
        )

        array = np.asarray(
            image,
            dtype=np.float32,
        )

    return array


def normalize_embeddings(
    embeddings: np.ndarray,
) -> np.ndarray:

    norms = np.linalg.norm(
        embeddings,
        axis=1,
        keepdims=True,
    )

    norms = np.maximum(
        norms,
        1e-8,
    )

    return (
        embeddings
        / norms
    )


def main() -> None:

    args = parse_args()

    import tensorflow as tf


    base_dir = (
        Path(__file__)
        .resolve()
        .parents[1]
    )


    model_path = (
        base_dir
        / "model"
        / "potato_resnet50.keras"
    )


    metadata_path = (
        base_dir
        / "model"
        / "model_metadata.json"
    )


    output_path = (
        base_dir
        / "model"
        / "ood_reference.npz"
    )


    if not model_path.exists():

        raise RuntimeError(
            f"No existe el modelo: "
            f"{model_path}"
        )


    if not metadata_path.exists():

        raise RuntimeError(
            f"No existen los metadatos: "
            f"{metadata_path}"
        )


    with metadata_path.open(
        "r",
        encoding="utf-8",
    ) as fh:

        metadata = json.load(
            fh
        )


    class_names: list[str] = list(
        metadata[
            "class_names"
        ]
    )


    image_size_raw = metadata.get(
        "image_size",
        [224, 224],
    )


    image_size = (
        int(
            image_size_raw[0]
        ),
        int(
            image_size_raw[1]
        ),
    )


    dataset_root = (
        Path(
            args.dataset_root
        )
        if args.dataset_root
        else (
            base_dir
            / "dataset"
        )
    )


    if not dataset_root.exists():

        raise RuntimeError(
            "No existe la carpeta dataset: "
            f"{dataset_root}"
        )


    split_dir = find_split_dir(
        dataset_root,
        args.split,
        class_names,
    )


    print(
        "Dataset utilizado:",
        split_dir,
    )


    print(
        "Cargando modelo:",
        model_path,
    )


    model = (
        tf.keras.models.load_model(
            model_path
        )
    )


    embedding_layer = (
        find_embedding_layer(
            tf,
            model,
            len(
                class_names
            ),
        )
    )


    print(
        "Capa de embeddings:",
        embedding_layer.name,
    )


    embedding_model = (
        tf.keras.Model(
            inputs=model.input,
            outputs=embedding_layer.output,
        )
    )


    centroids: list[
        np.ndarray
    ] = []


    thresholds: list[
        float
    ] = []


    counts: list[
        int
    ] = []


    for class_name in class_names:

        class_dir = find_class_dir(
            split_dir,
            class_name,
        )


        image_paths = sorted(
            [
                path
                for path in class_dir.rglob(
                    "*"
                )
                if (
                    path.is_file()
                    and
                    path.suffix.lower()
                    in IMAGE_EXTENSIONS
                )
            ]
        )


        if not image_paths:

            raise RuntimeError(
                f"La clase '{class_name}' "
                "no contiene imágenes."
            )


        print()
        print(
            f"Procesando {class_name}: "
            f"{len(image_paths)} imágenes"
        )


        class_embeddings: list[
            np.ndarray
        ] = []


        for start in range(
            0,
            len(image_paths),
            args.batch_size,
        ):

            batch_paths = (
                image_paths[
                    start:
                    start
                    + args.batch_size
                ]
            )


            batch_arrays = [
                load_image(
                    path,
                    image_size,
                )
                for path in batch_paths
            ]


            batch = np.stack(
                batch_arrays,
                axis=0,
            )


            embeddings = (
                embedding_model.predict(
                    batch,
                    verbose=0,
                )
            )


            embeddings = (
                np.asarray(
                    embeddings,
                    dtype=np.float32,
                )
            )


            if (
                embeddings.ndim
                > 2
            ):

                embeddings = (
                    embeddings.reshape(
                        embeddings.shape[0],
                        -1,
                    )
                )


            class_embeddings.append(
                embeddings
            )


        embeddings = np.concatenate(
            class_embeddings,
            axis=0,
        )


        embeddings = normalize_embeddings(
            embeddings
        )


        centroid = np.mean(
            embeddings,
            axis=0,
        )


        centroid_norm = np.linalg.norm(
            centroid
        )


        if centroid_norm <= 1e-8:

            raise RuntimeError(
                "Se obtuvo un centroide inválido "
                f"para {class_name}."
            )


        centroid = (
            centroid
            / centroid_norm
        )


        cosine_similarity = (
            embeddings
            @ centroid
        )


        cosine_distance = (
            1.0
            - cosine_similarity
        )


        percentile_value = float(
            np.percentile(
                cosine_distance,
                args.percentile,
            )
        )


        threshold = (
            percentile_value
            + float(
                args.margin
            )
        )


        centroids.append(
            centroid.astype(
                np.float32
            )
        )


        thresholds.append(
            threshold
        )


        counts.append(
            len(
                image_paths
            )
        )


        print(
            "  Distancia media:",
            round(
                float(
                    np.mean(
                        cosine_distance
                    )
                ),
                5,
            ),
        )


        print(
            f"  Percentil "
            f"{args.percentile}:",
            round(
                percentile_value,
                5,
            ),
        )


        print(
            "  Umbral final:",
            round(
                threshold,
                5,
            ),
        )


    centroids_array = np.stack(
        centroids,
        axis=0,
    )


    thresholds_array = np.asarray(
        thresholds,
        dtype=np.float32,
    )


    counts_array = np.asarray(
        counts,
        dtype=np.int32,
    )


    np.savez_compressed(
        output_path,

        class_names=np.asarray(
            class_names
        ),

        centroids=(
            centroids_array
        ),

        thresholds=(
            thresholds_array
        ),

        counts=(
            counts_array
        ),

        embedding_layer=np.asarray(
            embedding_layer.name
        ),

        model_version=np.asarray(
            metadata.get(
                "model_version",
                "",
            )
        ),

        percentile=np.asarray(
            args.percentile,
            dtype=np.float32,
        ),

        margin=np.asarray(
            args.margin,
            dtype=np.float32,
        ),
    )


    print()
    print(
        "================================="
    )

    print(
        "Referencia OOD creada correctamente."
    )

    print(
        "Archivo:",
        output_path,
    )

    print(
        "Clases:",
        class_names,
    )

    print(
        "Dimensión embedding:",
        centroids_array.shape[1],
    )

    print(
        "================================="
    )


if __name__ == "__main__":
    main()