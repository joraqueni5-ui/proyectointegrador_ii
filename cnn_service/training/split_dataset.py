from __future__ import annotations

import argparse
import random
import shutil
from pathlib import Path

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def collect_images(folder: Path) -> list[Path]:
    return [
        path
        for path in folder.rglob("*")
        if path.is_file() and path.suffix.lower() in IMAGE_EXTENSIONS
    ]


def copy_group(paths: list[Path], destination: Path) -> None:
    destination.mkdir(parents=True, exist_ok=True)
    for path in paths:
        shutil.copy2(path, destination / path.name)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Divide el dataset por clase en 70% entrenamiento, 15% validación y 15% prueba."
    )
    base_dir = Path(__file__).resolve().parents[1]
    parser.add_argument("--source", type=Path, default=base_dir / "dataset" / "raw")
    parser.add_argument("--output", type=Path, default=base_dir / "dataset" / "processed")
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--train", type=float, default=0.70)
    parser.add_argument("--val", type=float, default=0.15)
    parser.add_argument("--test", type=float, default=0.15)
    args = parser.parse_args()

    if round(args.train + args.val + args.test, 6) != 1.0:
        raise SystemExit("Las proporciones train/val/test deben sumar 1.0.")

    class_dirs = sorted([p for p in args.source.iterdir() if p.is_dir()])
    if not class_dirs:
        raise SystemExit(f"No se encontraron clases en {args.source}.")

    random.seed(args.seed)

    if args.output.exists():
        for split in ("train", "val", "test"):
            split_dir = args.output / split
            if split_dir.exists():
                shutil.rmtree(split_dir)

    for class_dir in class_dirs:
        images = collect_images(class_dir)
        random.shuffle(images)

        if len(images) < 10:
            raise SystemExit(
                f"La clase '{class_dir.name}' tiene solo {len(images)} imágenes; se recomiendan más datos."
            )

        n_total = len(images)
        n_train = int(n_total * args.train)
        n_val = int(n_total * args.val)

        train_files = images[:n_train]
        val_files = images[n_train:n_train + n_val]
        test_files = images[n_train + n_val:]

        copy_group(train_files, args.output / "train" / class_dir.name)
        copy_group(val_files, args.output / "val" / class_dir.name)
        copy_group(test_files, args.output / "test" / class_dir.name)

        print(
            f"{class_dir.name}: total={n_total}, "
            f"train={len(train_files)}, val={len(val_files)}, test={len(test_files)}"
        )

    print(f"Dataset dividido en: {args.output}")


if __name__ == "__main__":
    main()
