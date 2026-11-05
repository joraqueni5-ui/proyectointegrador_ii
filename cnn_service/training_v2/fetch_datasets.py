"""Descarga y arma el dataset del prototipo CNN v2 (fotos de hojas de papa en campo).

Fuentes (todas con licencia abierta, ver README.md):
  * Tanzania (Zenodo 8286529, CC BY 4.0): fotos de campo -> entrenamiento/validación/prueba.
  * PlantDoc (GitHub, CC BY 4.0): hojas de OTROS cultivos -> clase "other" (no es papa).
  * Cajamarca, Perú (Mendeley kh8xjh2zyh): prueba EXTERNA (ambiente andino). No se entrena con ella.
  * PlantDoc papa (tizón temprano/tardío reales): prueba EXTERNA. No se entrena con ella.

Es reanudable: si un archivo ya existe, se omite.
"""

from __future__ import annotations

import argparse
import csv
import json
import random
import re
import sys
import zipfile
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
from urllib.parse import quote

import requests

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import DATA_DIR, EXTERNAL_DIR, RAW_DIR, RemoteZip, save_image  # noqa: E402

ZENODO = "https://zenodo.org/records/8286529/files/{name}?download=1"
TANZANIA_ZIPS = {
    "healthy": "healthy.zip",
    "early_blight": "earlyblt.zip",
    "late_blight": "lateblt.zip",
}
PLANTDOC_RAW = "https://raw.githubusercontent.com/pratikkayal/PlantDoc-Dataset/master/{path}"
PLANTDOC_TREE = RAW_DIR / "plantdoc_tree.json"
PERU_ZIP = RAW_DIR / "peru.zip"
PERU_CLASSES = {"Health": "healthy", "Phytophthora": "late_blight"}
PLANTDOC_POTATO = {
    "Potato leaf early blight": "early_blight",
    "Potato leaf late blight": "late_blight",
}
IMAGE_SUFFIXES = (".jpg", ".jpeg", ".png")


def numero(nombre: str) -> int:
    encontrado = re.findall(r"\d+", nombre)
    return int(encontrado[-1]) if encontrado else 0


def ejecutar(tareas: list, trabajador, workers: int, etiqueta: str) -> int:
    hechas = fallidas = 0
    with ThreadPoolExecutor(max_workers=workers) as pool:
        futuros = [pool.submit(trabajador, *t) for t in tareas]
        for futuro in as_completed(futuros):
            try:
                ok = futuro.result()
            except Exception as exc:  # noqa: BLE001
                ok = False
                print(f"  ! {exc}", flush=True)
            hechas += 1
            fallidas += 0 if ok else 1
            if hechas % 200 == 0 or hechas == len(tareas):
                print(f"  {etiqueta}: {hechas}/{len(tareas)} (fallidas: {fallidas})", flush=True)
    return fallidas


# ---------------------------------------------------------------- Tanzania
def fetch_tanzania(per_class: int, block: int, workers: int, seed: int, manifest: list) -> None:
    rng = random.Random(seed)
    for clase, archivo in TANZANIA_ZIPS.items():
        print(f"[Tanzania] {clase}: leyendo índice de {archivo} ...", flush=True)
        remoto = RemoteZip(ZENODO.format(name=archivo), cache=RAW_DIR / f"indice_{archivo}.json")
        imagenes = sorted(
            (
                e
                for e in remoto.entries
                if e["name"].lower().endswith(IMAGE_SUFFIXES)
                # El zip trae archivos basura de macOS (__MACOSX/._foto.jpg).
                and "__MACOSX" not in e["name"]
                and not Path(e["name"]).name.startswith("._")
            ),
            key=lambda e: numero(e["name"]),
        )
        # Bloques de fotos consecutivas: aproximan "misma hoja/planta/visita".
        # Cada bloque va completo a un solo split para reducir la fuga de datos.
        bloques = [imagenes[i : i + block] for i in range(0, len(imagenes) - block + 1, block)]
        rng.shuffle(bloques)
        n_bloques = per_class // block
        elegidos = bloques[:n_bloques]
        n_train = round(n_bloques * 0.70)
        n_val = round(n_bloques * 0.15)
        reparto = (
            [("train", b) for b in elegidos[:n_train]]
            + [("val", b) for b in elegidos[n_train : n_train + n_val]]
            + [("test", b) for b in elegidos[n_train + n_val :]]
        )
        print(
            f"[Tanzania] {clase}: {len(imagenes)} fotos en el zip; muestra de {n_bloques} bloques "
            f"x {block} = {n_bloques * block} fotos",
            flush=True,
        )
        tareas = []
        for indice, (split, grupo) in enumerate(reparto):
            for entrada in grupo:
                destino = DATA_DIR / split / clase / f"tz_{Path(entrada['name']).stem}.jpg"
                manifest.append(
                    {"fuente": "tanzania", "clase": clase, "split": split, "bloque": indice,
                     "origen": entrada["name"], "archivo": str(destino.relative_to(DATA_DIR))}
                )
                if not destino.exists():
                    tareas.append((remoto, entrada, destino))

        def trabajar(remoto, entrada, destino) -> bool:
            return save_image(remoto.read(entrada), destino)

        ejecutar(tareas, trabajar, workers, f"tanzania/{clase}")


# ---------------------------------------------------------------- Perú
def fetch_peru() -> None:
    if not PERU_ZIP.exists():
        raise SystemExit(f"Falta {PERU_ZIP}. Descárgalo de Mendeley (kh8xjh2zyh) primero.")
    contador = {"healthy": 0, "late_blight": 0}
    with zipfile.ZipFile(PERU_ZIP) as z:
        for nombre in z.namelist():
            partes = nombre.split("/")
            if len(partes) < 3 or not nombre.lower().endswith(IMAGE_SUFFIXES):
                continue
            clase = PERU_CLASSES.get(partes[-2])
            if clase is None:
                continue
            contador[clase] += 1
            destino = EXTERNAL_DIR / "peru_cajamarca" / clase / f"pe_{contador[clase]:04d}.jpg"
            if not destino.exists():
                save_image(z.read(nombre), destino)
    print(f"[Perú] {contador}", flush=True)


# ---------------------------------------------------------------- PlantDoc
def plantdoc_rutas() -> list[str]:
    if not PLANTDOC_TREE.exists():
        respuesta = requests.get(
            "https://api.github.com/repos/pratikkayal/PlantDoc-Dataset/git/trees/master?recursive=1",
            timeout=60,
        )
        respuesta.raise_for_status()
        PLANTDOC_TREE.write_text(respuesta.text, encoding="utf-8")
    arbol = json.loads(PLANTDOC_TREE.read_text(encoding="utf-8"))
    return [
        e["path"]
        for e in arbol["tree"]
        if e["type"] == "blob" and e["path"].lower().endswith(IMAGE_SUFFIXES)
    ]


def descargar_plantdoc(ruta: str, destino: Path) -> bool:
    if destino.exists():
        return True
    url = PLANTDOC_RAW.format(path=quote(ruta))
    respuesta = requests.get(url, timeout=120)
    respuesta.raise_for_status()
    return save_image(respuesta.content, destino)


def fetch_plantdoc(neg_por_carpeta: int, workers: int, seed: int, manifest: list) -> None:
    rng = random.Random(seed)
    rutas = plantdoc_rutas()
    por_carpeta: dict[tuple[str, str], list[str]] = {}
    for ruta in rutas:
        partes = ruta.split("/")
        if len(partes) == 3:
            por_carpeta.setdefault((partes[0], partes[1]), []).append(ruta)

    tareas = []
    for (split_origen, carpeta), lista in sorted(por_carpeta.items()):
        lista = sorted(lista)
        if carpeta in PLANTDOC_POTATO:
            # Papa real: solo prueba externa (todas las fotos, train + test de PlantDoc).
            clase = PLANTDOC_POTATO[carpeta]
            for ruta in lista:
                destino = EXTERNAL_DIR / "plantdoc_potato" / clase / f"pd_{split_origen}_{Path(ruta).stem}.jpg"
                tareas.append((ruta, destino))
            continue
        # Otros cultivos -> clase "other". El split de PlantDoc 'test' se conserva como prueba.
        if split_origen == "test":
            seleccion = [("test", r) for r in lista]
        else:
            rng.shuffle(lista)
            tomadas = lista[:neg_por_carpeta]
            corte = max(1, round(len(tomadas) * 0.85))
            seleccion = [("train", r) for r in tomadas[:corte]] + [("val", r) for r in tomadas[corte:]]
        for split, ruta in seleccion:
            destino = DATA_DIR / split / "other" / f"pd_{split_origen}_{Path(ruta).stem}.jpg"
            manifest.append(
                {"fuente": "plantdoc", "clase": "other", "split": split, "bloque": "",
                 "origen": ruta, "archivo": str(destino.relative_to(DATA_DIR))}
            )
            tareas.append((ruta, destino))

    print(f"[PlantDoc] {len(tareas)} imágenes a descargar", flush=True)
    ejecutar(tareas, descargar_plantdoc, workers, "plantdoc")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    parser.add_argument("--per-class", type=int, default=2000, help="fotos de Tanzania por clase")
    parser.add_argument("--block", type=int, default=40, help="fotos consecutivas por bloque")
    parser.add_argument("--neg-per-folder", type=int, default=40, help="negativos PlantDoc por carpeta")
    parser.add_argument("--workers", type=int, default=8)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--only", choices=["tanzania", "peru", "plantdoc"], default=None)
    args = parser.parse_args()

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    manifest: list[dict] = []

    if args.only in (None, "peru"):
        fetch_peru()
    if args.only in (None, "plantdoc"):
        fetch_plantdoc(args.neg_per_folder, args.workers, args.seed, manifest)
    if args.only in (None, "tanzania"):
        fetch_tanzania(args.per_class, args.block, args.workers, args.seed, manifest)

    ruta_manifest = DATA_DIR / f"manifest_{args.only or 'completo'}.csv"
    with ruta_manifest.open("w", newline="", encoding="utf-8") as fh:
        escritor = csv.DictWriter(fh, fieldnames=["fuente", "clase", "split", "bloque", "origen", "archivo"])
        escritor.writeheader()
        escritor.writerows(manifest)

    print("\nConteo final:")
    for split in ("train", "val", "test"):
        cuentas = {
            d.name: sum(1 for _ in d.glob("*.jpg"))
            for d in sorted((DATA_DIR / split).glob("*")) if d.is_dir()
        }
        print(f"  {split:5s} {cuentas}")
    for externo in sorted(EXTERNAL_DIR.glob("*")):
        cuentas = {d.name: sum(1 for _ in d.glob("*.jpg")) for d in sorted(externo.glob("*")) if d.is_dir()}
        print(f"  externo/{externo.name}: {cuentas}")


if __name__ == "__main__":
    main()
