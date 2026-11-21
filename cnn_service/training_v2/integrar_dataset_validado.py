"""Integra el ZIP del "dataset validado" (exportado desde el sistema) con un dataset de entrenamiento.

El sistema exporta, desde Diagnósticos -> "Exportar dataset validado", un ZIP con:
    early_blight/  healthy/  late_blight/   fotos de diagnósticos Confirmados o Corregidos,
                                             en la carpeta de la enfermedad FINAL (validada).
    metadata.csv                              id, clase predicha/final, confianza, modelo, fecha,
                                             comunidad y parcela de cada foto.

Este script reparte esas fotos en train/val/test AGRUPANDO POR PARCELA: varias fotos de una misma
parcela se parecen mucho entre sí; si cayeran en conjuntos distintos, la evaluación saldría
inflada (fuga de datos). Opcionalmente parte de un dataset existente (por ejemplo dataset_v2c).

Uso (desde cnn_service/):
    python training_v2/integrar_dataset_validado.py --zip dataset_validado_20261121.zip \\
        --base dataset_v2c --out dataset_v3

Luego se entrena igual que antes:
    python training_v2/train_v2.py --data dataset_v3 --out model_v3 --artifacts artifacts_v3 --version PROINPA-v3

Notas:
  * Las fotos idénticas (mismo contenido) a las que ya existen en el dataset se omiten.
  * La clase "other" (no es papa) viene del dataset base; el sistema no la genera.
  * Con pocas parcelas (menos de 3) no se puede reservar validación y prueba: todo va a train
    y el script lo avisa. En ese caso conviene usar el ZIP solo como refuerzo del entrenamiento.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import io
import random
import shutil
import sys
import zipfile
from collections import defaultdict
from pathlib import Path

CLASES = ["early_blight", "healthy", "late_blight", "other"]
CLASES_DEL_SISTEMA = {"early_blight", "healthy", "late_blight"}
SPLITS = ("train", "val", "test")


def leer_metadata(zf: zipfile.ZipFile) -> list[dict]:
    with zf.open("metadata.csv") as fh:
        texto = io.TextIOWrapper(fh, encoding="utf-8-sig", newline="")
        return list(csv.DictReader(texto))


def repartir_parcelas(parcelas: list[str], fraccion_val: float, fraccion_test: float, seed: int) -> dict[str, str]:
    """Asigna cada parcela completa a train, val o test."""

    orden = sorted(parcelas)
    random.Random(seed).shuffle(orden)
    n = len(orden)

    if n < 3:
        return {p: "train" for p in orden}

    n_test = max(1, round(n * fraccion_test))
    n_val = max(1, round(n * fraccion_val))

    if n_val + n_test >= n:  # siempre debe quedar al menos una parcela de entrenamiento
        n_test, n_val = 1, 1

    asignacion = {}
    for i, parcela in enumerate(orden):
        asignacion[parcela] = "test" if i < n_test else "val" if i < n_test + n_val else "train"

    return asignacion


def hashes_existentes(destino: Path) -> set[str]:
    return {hashlib.md5(f.read_bytes()).hexdigest() for f in destino.rglob("*") if f.suffix.lower() in (".jpg", ".jpeg", ".png", ".webp")}


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--zip", required=True, type=Path, help="ZIP exportado desde el sistema")
    p.add_argument("--out", required=True, type=Path, help="carpeta de salida (no debe existir)")
    p.add_argument("--base", type=Path, default=None, help="dataset existente con train/val/test (se copia primero)")
    p.add_argument("--val", type=float, default=0.15, help="fracción de parcelas para validación")
    p.add_argument("--test", type=float, default=0.15, help="fracción de parcelas para prueba")
    p.add_argument("--seed", type=int, default=42)
    args = p.parse_args()

    if not args.zip.exists():
        sys.exit(f"No existe el ZIP: {args.zip}")
    if args.out.exists():
        sys.exit(f"{args.out} ya existe. Bórralo a mano o elige otra carpeta de salida.")
    if not (0 < args.val < 0.5 and 0 < args.test < 0.5):
        sys.exit("--val y --test deben estar entre 0 y 0,5.")

    if args.base:
        if not args.base.exists():
            sys.exit(f"No existe el dataset base: {args.base}")
        shutil.copytree(args.base, args.out)
    for split in SPLITS:
        for clase in CLASES:
            (args.out / split / clase).mkdir(parents=True, exist_ok=True)

    vistos = hashes_existentes(args.out)

    with zipfile.ZipFile(args.zip) as zf:
        filas = leer_metadata(zf)
        if not filas:
            sys.exit("El ZIP no tiene filas en metadata.csv.")

        parcelas = sorted({f["parcela"] for f in filas})
        asignacion = repartir_parcelas(parcelas, args.val, args.test, args.seed)
        if len(parcelas) < 3:
            print(f"AVISO: solo hay {len(parcelas)} parcela(s): no se puede reservar validación ni prueba; todo va a train.")

        agregadas: dict[tuple[str, str], int] = defaultdict(int)
        duplicadas = invalidas = 0

        for fila in filas:
            clase = fila["clase_final"]
            if clase not in CLASES_DEL_SISTEMA:
                invalidas += 1
                continue

            datos = zf.read(fila["archivo"])
            huella = hashlib.md5(datos).hexdigest()
            if huella in vistos:
                duplicadas += 1
                continue
            vistos.add(huella)

            split = asignacion[fila["parcela"]]
            extension = Path(fila["archivo"]).suffix.lower() or ".jpg"
            (args.out / split / clase / f"val_{int(fila['id_diagnostico']):05d}{extension}").write_bytes(datos)
            agregadas[(split, clase)] += 1

    print(f"\nDataset integrado en {args.out}")
    print(f"Parcelas: {len(parcelas)}  ->  " + ", ".join(f"{s}: {sum(1 for v in asignacion.values() if v == s)}" for s in SPLITS))
    for split in SPLITS:
        detalle = {c: agregadas.get((split, c), 0) for c in sorted(CLASES_DEL_SISTEMA)}
        print(f"  {split:5s} fotos validadas agregadas: {detalle}")
    print(f"Omitidas por estar repetidas: {duplicadas} | con clase no válida: {invalidas}")
    print("\nSiguiente paso: python training_v2/train_v2.py --data", args.out, "--out model_nuevo --artifacts artifacts_nuevo")


if __name__ == "__main__":
    main()
