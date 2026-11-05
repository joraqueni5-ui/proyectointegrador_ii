from __future__ import annotations

import argparse
import csv
import json
import random
import shutil
from collections import defaultdict
from pathlib import Path

from datasets import concatenate_datasets, load_dataset

# Clases de papa disponibles en PlantVillage.
DISEASE_TO_TARGET = {
    "healthy": "healthy",
    "Early_blight": "early_blight",
    "Late_blight": "late_blight",
}

SPLITS = ("train", "val", "test")


def assign_leaf_groups(
    groups: list[tuple[str, list[int]]],
    fractions: dict[str, float],
    seed: int,
) -> dict[str, list[tuple[str, list[int]]]]:
    """Asigna hojas completas a cada split intentando respetar 70/15/15 por imágenes.

    Una misma hoja (leaf_id) nunca se reparte entre entrenamiento, validación y prueba.
    """
    rng = random.Random(seed)
    groups = sorted(groups, key=lambda item: item[0])
    rng.shuffle(groups)

    total_images = sum(len(indices) for _, indices in groups)
    targets = {name: total_images * fractions[name] for name in SPLITS}
    current = {name: 0 for name in SPLITS}
    assigned: dict[str, list[tuple[str, list[int]]]] = {name: [] for name in SPLITS}

    # Primero garantizamos al menos un grupo por split cuando sea posible.
    for split_name in SPLITS:
        if groups:
            leaf_id, indices = groups.pop()
            assigned[split_name].append((leaf_id, indices))
            current[split_name] += len(indices)

    # Luego enviamos cada hoja al split con mayor déficit proporcional.
    for leaf_id, indices in groups:
        deficits = {}
        for split_name in SPLITS:
            target = targets[split_name]
            if target <= 0:
                deficits[split_name] = float("-inf")
            else:
                deficits[split_name] = (target - current[split_name]) / target

        chosen = max(SPLITS, key=lambda name: deficits[name])
        assigned[chosen].append((leaf_id, indices))
        current[chosen] += len(indices)

    return assigned


def safe_filename(original: str, row_index: int) -> str:
    name = Path(original).name if original else f"image_{row_index}.jpg"
    stem = Path(name).stem
    suffix = Path(name).suffix or ".jpg"
    return f"{stem}_{row_index}{suffix.lower()}"


def main() -> None:
    parser = argparse.ArgumentParser(
        description=(
            "Descarga PlantVillage desde Hugging Face, conserva solo papa y crea una división "
            "70/15/15 por leaf_id para evitar fuga de datos."
        )
    )
    base_dir = Path(__file__).resolve().parents[1]
    parser.add_argument(
        "--output",
        type=Path,
        default=base_dir / "dataset" / "processed",
        help="Directorio de salida del dataset procesado.",
    )
    parser.add_argument(
        "--artifacts",
        type=Path,
        default=base_dir / "artifacts",
        help="Directorio donde se guardarán estadísticas y manifiesto.",
    )
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--train", type=float, default=0.70)
    parser.add_argument("--val", type=float, default=0.15)
    parser.add_argument("--test", type=float, default=0.15)
    args = parser.parse_args()

    fractions = {"train": args.train, "val": args.val, "test": args.test}
    if abs(sum(fractions.values()) - 1.0) > 1e-9:
        raise SystemExit("Las proporciones train/val/test deben sumar 1.0.")

    print("Descargando/cargando PlantVillage (configuración color) desde Hugging Face...")
    dataset = load_dataset("mohanty/PlantVillage", "color")
    full = concatenate_datasets([dataset["train"], dataset["test"]])

    # Filtramos por el campo de cultivo sin necesidad de decodificar todas las imágenes.
    potato = full.filter(lambda crop: crop == "Potato", input_columns=["crop"])
    if len(potato) == 0:
        raise SystemExit("No se encontraron imágenes de papa en PlantVillage.")

    groups_by_class: dict[str, dict[str, list[int]]] = {
        target: defaultdict(list) for target in DISEASE_TO_TARGET.values()
    }

    skipped = 0
    for idx, row in enumerate(potato):
        disease = str(row.get("disease", ""))
        target_class = DISEASE_TO_TARGET.get(disease)
        if target_class is None:
            skipped += 1
            continue

        leaf_id = str(row.get("leaf_id") or f"fallback_{idx}")
        groups_by_class[target_class][leaf_id].append(idx)

    if args.output.exists():
        shutil.rmtree(args.output)
    args.output.mkdir(parents=True, exist_ok=True)
    args.artifacts.mkdir(parents=True, exist_ok=True)

    manifest_rows: list[dict[str, str | int]] = []
    split_leaf_ids: dict[str, set[str]] = {name: set() for name in SPLITS}
    stats: dict[str, dict[str, int]] = {
        split_name: {class_name: 0 for class_name in groups_by_class}
        for split_name in SPLITS
    }

    for class_offset, (class_name, leaf_groups) in enumerate(sorted(groups_by_class.items())):
        groups = list(leaf_groups.items())
        if len(groups) < 3:
            raise SystemExit(
                f"La clase '{class_name}' tiene menos de 3 leaf_id; no puede dividirse en 3 conjuntos."
            )

        assignment = assign_leaf_groups(
            groups,
            fractions=fractions,
            seed=args.seed + class_offset,
        )

        for split_name in SPLITS:
            class_dir = args.output / split_name / class_name
            class_dir.mkdir(parents=True, exist_ok=True)

            for leaf_id, indices in assignment[split_name]:
                if leaf_id in split_leaf_ids[split_name]:
                    pass
                split_leaf_ids[split_name].add(leaf_id)

                for idx in indices:
                    row = potato[idx]
                    image = row["image"].convert("RGB")
                    filename = safe_filename(str(row.get("image_path", "")), idx)
                    destination = class_dir / filename
                    image.save(destination, format="JPEG", quality=95)

                    stats[split_name][class_name] += 1
                    manifest_rows.append(
                        {
                            "split": split_name,
                            "class": class_name,
                            "source_disease": str(row.get("disease", "")),
                            "leaf_id": leaf_id,
                            "source_image_path": str(row.get("image_path", "")),
                            "local_image_path": str(destination.relative_to(base_dir)),
                        }
                    )

    # Verificación global: un leaf_id no puede aparecer en más de un split.
    overlap_train_val = split_leaf_ids["train"] & split_leaf_ids["val"]
    overlap_train_test = split_leaf_ids["train"] & split_leaf_ids["test"]
    overlap_val_test = split_leaf_ids["val"] & split_leaf_ids["test"]
    overlaps = overlap_train_val | overlap_train_test | overlap_val_test
    if overlaps:
        raise RuntimeError(
            "Se detectó fuga de datos: existen leaf_id presentes en más de un split. "
            f"Ejemplos: {sorted(overlaps)[:5]}"
        )

    manifest_path = args.artifacts / "dataset_manifest.csv"
    with manifest_path.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(
            fh,
            fieldnames=[
                "split",
                "class",
                "source_disease",
                "leaf_id",
                "source_image_path",
                "local_image_path",
            ],
        )
        writer.writeheader()
        writer.writerows(manifest_rows)

    totals = {
        split_name: sum(stats[split_name].values())
        for split_name in SPLITS
    }
    grand_total = sum(totals.values())

    summary = {
        "source": "mohanty/PlantVillage (Hugging Face, color)",
        "crop": "Potato",
        "seed": args.seed,
        "requested_split": fractions,
        "split_strategy": "leaf_id grouped and class-stratified approximation",
        "classes": sorted(groups_by_class.keys()),
        "counts": stats,
        "split_totals": totals,
        "actual_split_ratio": {
            split_name: round(totals[split_name] / grand_total, 6) if grand_total else 0
            for split_name in SPLITS
        },
        "unique_leaf_ids": {
            split_name: len(split_leaf_ids[split_name]) for split_name in SPLITS
        },
        "skipped_rows": skipped,
        "data_leakage_leaf_id_overlap": 0,
    }

    stats_path = args.artifacts / "dataset_stats.json"
    with stats_path.open("w", encoding="utf-8") as fh:
        json.dump(summary, fh, ensure_ascii=False, indent=2)

    print("\nDataset preparado correctamente.")
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    print(f"\nManifiesto: {manifest_path}")
    print(f"Estadísticas: {stats_path}")


if __name__ == "__main__":
    main()
