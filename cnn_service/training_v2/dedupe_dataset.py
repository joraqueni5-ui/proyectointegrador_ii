"""Elimina fotos idénticas (mismo contenido) dentro del dataset v2.

El dataset de Tanzania repite algunas fotos con distinto número de archivo.
Si una misma foto cae en entrenamiento y en prueba, las métricas quedan infladas.
Prioridad al conservar: train > val > test. Dentro de un split se deja una sola copia.
"""

from __future__ import annotations

import collections
import hashlib
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import DATA_DIR  # noqa: E402

PRIORIDAD = ("train", "val", "test")


def main() -> None:
    vistos: dict[str, Path] = {}
    eliminadas: collections.Counter = collections.Counter()
    for split in PRIORIDAD:
        for archivo in sorted((DATA_DIR / split).rglob("*.jpg")):
            huella = hashlib.md5(archivo.read_bytes()).hexdigest()
            if huella in vistos:
                eliminadas[(split, archivo.parent.name)] += 1
                archivo.unlink()
            else:
                vistos[huella] = archivo
    print("Fotos duplicadas eliminadas:")
    for (split, clase), n in sorted(eliminadas.items()):
        print(f"  {split:5s} {clase:13s} {n}")
    print(f"Total eliminadas: {sum(eliminadas.values())}")
    for split in PRIORIDAD:
        conteo = {d.name: sum(1 for _ in d.glob('*.jpg')) for d in sorted((DATA_DIR / split).iterdir()) if d.is_dir()}
        print(f"{split:5s} {conteo}")


if __name__ == "__main__":
    main()
