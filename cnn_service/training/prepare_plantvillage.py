from __future__ import annotations

import argparse
import shutil
from pathlib import Path

SOURCE_TO_TARGET = {
    "Potato___healthy": "healthy",
    "Potato___Early_blight": "early_blight",
    "Potato___Late_blight": "late_blight",
}

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def find_class_dir(root: Path, expected_name: str) -> Path | None:
    direct = root / expected_name
    if direct.is_dir():
        return direct

    for candidate in root.rglob(expected_name):
        if candidate.is_dir():
            return candidate

    return None


def copy_images(source: Path, target: Path) -> int:
    target.mkdir(parents=True, exist_ok=True)
    copied = 0

    for path in source.rglob("*"):
        if path.is_file() and path.suffix.lower() in IMAGE_EXTENSIONS:
            destination = target / path.name
            if destination.exists():
                destination = target / f"{path.parent.name}_{path.name}"
            shutil.copy2(path, destination)
            copied += 1

    return copied


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Extrae las clases de papa de PlantVillage y las normaliza para el proyecto."
    )
    parser.add_argument("--source", required=True, type=Path, help="Carpeta donde se descargó PlantVillage.")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path(__file__).resolve().parents[1] / "dataset" / "raw",
        help="Directorio de salida (por defecto cnn_service/dataset/raw).",
    )
    args = parser.parse_args()

    args.output.mkdir(parents=True, exist_ok=True)

    total = 0
    for source_name, target_name in SOURCE_TO_TARGET.items():
        found = find_class_dir(args.source, source_name)
        if found is None:
            raise SystemExit(
                f"No se encontró la carpeta '{source_name}' dentro de {args.source}."
            )

        count = copy_images(found, args.output / target_name)
        total += count
        print(f"{source_name} -> {target_name}: {count} imágenes")

    print(f"Preparación terminada. Total copiado: {total} imágenes")


if __name__ == "__main__":
    main()
