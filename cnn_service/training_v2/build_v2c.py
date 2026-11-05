"""Arma el dataset v2c (para reentrenar con más datos y mejor evaluación).

Cambios respecto a v2b:
  * Tanzania: más fotos por clase (por defecto 4.000; v2b usó 2.000), por bloques.
  * Etiopía (Holeta, Mendeley v4w72bsts5): fotos sanas y con tizón tardío de una
    parcela real. Se reserva COMPLETA como prueba externa: el modelo nunca ve esa fuente.
  * iNaturalist: papa (se reparte en entrenamiento y prueba externa "¿es papa?") y
    otras especies de cultivos/plantas parecidas como negativos difíciles ("other").
  * Perú y PlantDoc se reparten igual que en v2b (se copian de dataset_v2b).

Requisitos previos (desde cnn_service/):
    python training_v2/fetch_datasets.py ...      # dataset_v2 (Tanzania, PlantDoc 'other')
    python training_v2/build_v2b.py               # dataset_v2b (Perú, PlantDoc papa)
    python training_v2/fetch_inat.py              # dataset_v2c/_raw/inat
    # dataset_v2c/_raw/etiopia/ (RAR de Mendeley descomprimido con 7-Zip)
"""

from __future__ import annotations

import argparse
import hashlib
import json
import random
import shutil
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import BASE_DIR, RemoteZip, save_image  # noqa: E402
from fetch_datasets import TANZANIA_ZIPS, ZENODO, numero  # noqa: E402

V2 = BASE_DIR / "dataset_v2"
V2B = BASE_DIR / "dataset_v2b"
DEST = BASE_DIR / "dataset_v2c"
RAW = DEST / "_raw"
SPLITS = ("train", "val", "test")
CLASES = ("early_blight", "healthy", "late_blight", "other")


def copiar(origen: Path, destino: Path) -> None:
    destino.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(origen, destino)


def tanzania(por_clase: int, bloque: int, workers: int, seed: int, campo: list[Path]) -> None:
    rng = random.Random(seed)
    existentes = {f.name: f for s in SPLITS for f in (V2 / s).glob("*/tz_*.jpg")}
    for clase, archivo in TANZANIA_ZIPS.items():
        remoto = RemoteZip(ZENODO.format(name=archivo), cache=V2 / "_raw" / f"indice_{archivo}.json")
        fotos = sorted(
            (
                e for e in remoto.entries
                if e["name"].lower().endswith((".jpg", ".jpeg", ".png"))
                and "__MACOSX" not in e["name"] and not Path(e["name"]).name.startswith("._")
            ),
            key=lambda e: numero(e["name"]),
        )
        bloques = [fotos[i : i + bloque] for i in range(0, len(fotos) - bloque + 1, bloque)]
        rng.shuffle(bloques)
        n = por_clase // bloque
        elegidos = bloques[:n]
        n_train, n_val = round(n * 0.70), round(n * 0.15)
        reparto = (
            [("train", b) for b in elegidos[:n_train]]
            + [("val", b) for b in elegidos[n_train : n_train + n_val]]
            + [("test", b) for b in elegidos[n_train + n_val :]]
        )
        tareas, reutilizadas = [], 0
        for split, grupo in reparto:
            for e in grupo:
                nombre = f"tz_{Path(e['name']).stem}.jpg"
                destino = DEST / split / clase / nombre
                if destino.exists():
                    continue
                if nombre in existentes:
                    copiar(existentes[nombre], destino)
                    reutilizadas += 1
                else:
                    tareas.append((e, destino))
        print(f"[Tanzania] {clase}: {n * bloque} fotos ({reutilizadas} reutilizadas, {len(tareas)} por descargar)", flush=True)

        def bajar(e: dict, destino: Path) -> bool:
            return save_image(remoto.read(e), destino)

        hechas = fallidas = 0
        with ThreadPoolExecutor(max_workers=workers) as pool:
            futuros = [pool.submit(bajar, *t) for t in tareas]
            for f in as_completed(futuros):
                try:
                    ok = f.result()
                except Exception as exc:  # noqa: BLE001
                    ok = False
                    print(f"  ! {exc}", flush=True)
                hechas += 1
                fallidas += 0 if ok else 1
                if hechas % 250 == 0 or hechas == len(futuros):
                    print(f"  {clase}: {hechas}/{len(futuros)} (fallidas {fallidas})", flush=True)


def fuentes_de_campo(campo: list[Path]) -> None:
    """Perú y PlantDoc (papa y 'other') tal como quedaron en v2b / v2."""
    for split in ("train", "val"):
        for clase in ("early_blight", "healthy", "late_blight"):
            for f in (V2B / split / clase).glob("*.jpg"):
                if f.name.startswith(("pe_", "pd_")) and "_rep" not in f.name:
                    destino = DEST / split / clase / f.name
                    copiar(f, destino)
                    if split == "train":
                        campo.append(destino)
    for f in (V2B / "external").rglob("*.jpg"):
        copiar(f, DEST / "external" / f.relative_to(V2B / "external"))
    for split in SPLITS:
        for f in (V2 / split / "other").glob("pd_*.jpg"):
            copiar(f, DEST / split / "other" / f.name)


def etiopia() -> None:
    base = RAW / "etiopia" / "Local"
    mapa = {"Healthy": "healthy", "Late Blight": "late_blight"}
    for carpeta, clase in mapa.items():
        for i, f in enumerate(sorted((base / carpeta).glob("*"))):
            if f.suffix.lower() in (".jpg", ".jpeg", ".png"):
                save_image(f.read_bytes(), DEST / "external" / "etiopia_holeta" / clase / f"et_{i:04d}.jpg")


def inaturalist(rng: random.Random, campo: list[Path]) -> None:
    papa = sorted((RAW / "inat" / "potato").glob("*.jpg"))
    rng.shuffle(papa)
    n_train, n_val = round(len(papa) * 0.55), round(len(papa) * 0.10)
    for i, f in enumerate(papa):
        if i < n_train:
            destino = DEST / "train" / "healthy" / f.name
            campo.append(destino)
        elif i < n_train + n_val:
            destino = DEST / "val" / "healthy" / f.name
        else:
            destino = DEST / "external" / "inat_papa" / "papa" / f.name
        copiar(f, destino)
    otros = sorted((RAW / "inat" / "other").glob("*.jpg"))
    rng.shuffle(otros)
    a, b = round(len(otros) * 0.70), round(len(otros) * 0.85)
    for i, f in enumerate(otros):
        split = "train" if i < a else "val" if i < b else "test"
        copiar(f, DEST / split / "other" / f.name)


def deduplicar(campo: list[Path]) -> None:
    vistos: dict[str, Path] = {}
    quitadas = 0
    for base in [DEST / s for s in SPLITS] + [DEST / "external"]:
        for f in sorted(base.rglob("*.jpg")):
            h = hashlib.md5(f.read_bytes()).hexdigest()
            if h in vistos:
                f.unlink()
                quitadas += 1
                if f in campo:
                    campo.remove(f)
            else:
                vistos[h] = f
    print(f"Fotos idénticas eliminadas: {quitadas}", flush=True)


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--per-class", type=int, default=4000, help="fotos de Tanzania por clase")
    p.add_argument("--block", type=int, default=40)
    p.add_argument("--field-repeat", type=int, default=3)
    p.add_argument("--workers", type=int, default=4)
    p.add_argument("--seed", type=int, default=42)
    args = p.parse_args()
    rng = random.Random(args.seed)

    campo: list[Path] = []
    tanzania(args.per_class, args.block, args.workers, args.seed, campo)
    fuentes_de_campo(campo)
    etiopia()
    inaturalist(rng, campo)
    deduplicar(campo)

    repetidas = 0
    for f in campo:
        if f.exists():
            for r in range(1, args.field_repeat):
                shutil.copyfile(f, f.with_name(f"{f.stem}_rep{r}.jpg"))
                repetidas += 1
    print(f"Copias de fotos de campo añadidas: {repetidas} (x{args.field_repeat})", flush=True)

    (DEST / "DATASETS.json").write_text(
        json.dumps(
            {
                "train_val_test": (
                    f"Irish Potato Imagery Dataset, Tanzania (Zenodo 8286529, CC BY 4.0), {args.per_class} fotos por clase en bloques; "
                    "Cajamarca-Perú (Mendeley kh8xjh2zyh) y PlantDoc papa (parte); iNaturalist papa (parte, CC0/CC BY/CC BY-SA)"
                ),
                "other": "PlantDoc (CC BY 4.0) e iNaturalist (CC0/CC BY/CC BY-SA): cultivos y plantas parecidas",
                "external_test": (
                    "Etiopía-Holeta (Mendeley v4w72bsts5, CC BY 4.0; fuente completa reservada), "
                    "reserva de Cajamarca-Perú, PlantDoc papa e iNaturalist papa"
                ),
            },
            ensure_ascii=False, indent=2,
        ),
        encoding="utf-8",
    )
    for split in SPLITS:
        print(split, {c: sum(1 for _ in (DEST / split / c).glob("*.jpg")) for c in CLASES}, flush=True)
    for fuente in sorted((DEST / "external").glob("*")):
        print("externo/" + fuente.name, {d.name: sum(1 for _ in d.glob("*.jpg")) for d in sorted(fuente.iterdir()) if d.is_dir()}, flush=True)


if __name__ == "__main__":
    main()
