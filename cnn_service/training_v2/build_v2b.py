"""Arma el dataset v2b: papa de VARIAS fuentes para evitar el sesgo de fuente.

Problema de v2 (a): todas las hojas de papa venían de una sola cámara (Tanzania)
y "other" venía de otras fuentes. La red aprendió "se ve como esa cámara = papa" y
rechazó el ~90 % de las fotos de papa de Perú y PlantDoc.

Solución: parte de las fotos de papa de Perú (Cajamarca) y PlantDoc pasan a
entrenamiento/validación, y el resto queda como prueba EXTERNA reservada.

  * Perú: bloques de 12 fotos consecutivas (por número de archivo) -> 55 % train,
    10 % val, 35 % prueba. Los bloques reducen la fuga entre fotos casi idénticas.
  * PlantDoc papa: aleatorio 55/10/35 (no hay identificador de planta).
  * Las fotos de campo del entrenamiento se repiten `--field-repeat` veces
    (son pocas frente a las ~4.000 de Tanzania).
  * Se eliminan fotos idénticas entre todos los conjuntos (prioridad train > val > test > externo).
"""

from __future__ import annotations

import argparse
import hashlib
import random
import re
import shutil
import sys
import zipfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import BASE_DIR, DATA_DIR, EXTERNAL_DIR, RAW_DIR, save_image  # noqa: E402

DEST = BASE_DIR / "dataset_v2b"
PERU_CLASSES = {"Health": "healthy", "Phytophthora": "late_blight"}
FRACCIONES = (0.55, 0.10)  # train, val; el resto es prueba externa
SPLITS = ("train", "val", "test")


def numero(nombre: str) -> int:
    m = re.findall(r"\d+", nombre)
    return int(m[-1]) if m else 0


def reparto(bloques: list, rng: random.Random) -> dict[str, list]:
    rng.shuffle(bloques)
    n = len(bloques)
    n_train, n_val = round(n * FRACCIONES[0]), round(n * FRACCIONES[1])
    return {"train": bloques[:n_train], "val": bloques[n_train : n_train + n_val], "holdout": bloques[n_train + n_val :]}


def destino(split: str, clase: str, nombre: str) -> Path:
    if split == "holdout":
        fuente = "peru_cajamarca" if nombre.startswith("pe_") else "plantdoc_potato"
        return DEST / "external" / fuente / clase / nombre
    return DEST / split / clase / nombre


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--field-repeat", type=int, default=3)
    p.add_argument("--block", type=int, default=12)
    p.add_argument("--seed", type=int, default=42)
    args = p.parse_args()
    rng = random.Random(args.seed)

    if DEST.exists():
        raise SystemExit(f"{DEST} ya existe. Bórralo a mano si quieres reconstruirlo.")

    # 1) Base: train/val/test de v2 (Tanzania + PlantDoc 'other'), ya deduplicados.
    for split in SPLITS:
        shutil.copytree(DATA_DIR / split, DEST / split)

    campo_train: list[Path] = []

    def colocar(split: str, clase: str, nombre: str, datos: bytes | None = None, origen: Path | None = None) -> None:
        ruta = destino(split, clase, nombre)
        ruta.parent.mkdir(parents=True, exist_ok=True)
        if datos is not None:
            save_image(datos, ruta)
        else:
            shutil.copyfile(origen, ruta)
        if split == "train":
            campo_train.append(ruta)

    # 2) Perú, por bloques de fotos consecutivas
    with zipfile.ZipFile(RAW_DIR / "peru.zip") as z:
        for carpeta, clase in PERU_CLASSES.items():
            nombres = sorted(
                (n for n in z.namelist() if n.lower().endswith((".jpg", ".jpeg", ".png")) and n.split("/")[-2] == carpeta),
                key=numero,
            )
            bloques = [nombres[i : i + args.block] for i in range(0, len(nombres), args.block)]
            for split, grupos in reparto(bloques, rng).items():
                for grupo in grupos:
                    for nombre in grupo:
                        colocar(split, clase, f"pe_{numero(nombre):04d}.jpg", datos=z.read(nombre))

    # 3) PlantDoc papa, aleatorio
    for clase in ("early_blight", "late_blight"):
        archivos = sorted((EXTERNAL_DIR / "plantdoc_potato" / clase).glob("*.jpg"))
        rng.shuffle(archivos)
        n_train, n_val = round(len(archivos) * FRACCIONES[0]), round(len(archivos) * FRACCIONES[1])
        for i, f in enumerate(archivos):
            split = "train" if i < n_train else "val" if i < n_train + n_val else "holdout"
            colocar(split, clase, f.name, origen=f)

    # 4) Deduplicar entre todos los conjuntos (train > val > test > externo)
    vistos: dict[str, Path] = {}
    quitadas = 0
    orden = [DEST / s for s in SPLITS] + [DEST / "external"]
    for base in orden:
        for f in sorted(base.rglob("*.jpg")):
            h = hashlib.md5(f.read_bytes()).hexdigest()
            if h in vistos:
                f.unlink()
                quitadas += 1
                if f in campo_train:
                    campo_train.remove(f)
            else:
                vistos[h] = f
    print(f"Fotos idénticas eliminadas: {quitadas}")

    # 5) Repetir las fotos de campo del entrenamiento
    repetidas = 0
    for f in campo_train:
        if f.exists():
            for r in range(1, args.field_repeat):
                shutil.copyfile(f, f.with_name(f"{f.stem}_rep{r}.jpg"))
                repetidas += 1
    print(f"Copias de fotos de campo añadidas: {repetidas} (x{args.field_repeat})")

    for split in SPLITS:
        conteo = {d.name: sum(1 for _ in d.glob("*.jpg")) for d in sorted((DEST / split).iterdir()) if d.is_dir()}
        print(f"{split:5s} {conteo}")
    for fuente in sorted((DEST / "external").glob("*")):
        conteo = {d.name: sum(1 for _ in d.glob("*.jpg")) for d in sorted(fuente.iterdir()) if d.is_dir()}
        print(f"externo/{fuente.name}: {conteo}")


if __name__ == "__main__":
    main()
