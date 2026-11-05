"""Descarga fotos de iNaturalist con licencia abierta (CC0, CC BY, CC BY-SA).

  * papa (Solanum tuberosum): fotos de plantas reales en huertos y campos.
  * "other": cultivos y plantas parecidas (tomate, berenjena, ají, maíz, quinua,
    haba, frijol, trigo...) como negativos difíciles ("no es papa").

Guarda un CSV con autor, licencia y enlace de cada foto (la atribución es
obligatoria en CC BY / CC BY-SA). Se excluyen las licencias NC. Reanudable.
Usa la API pública de iNaturalist (sin cuenta), a 1 petición por segundo.
"""

from __future__ import annotations

import argparse
import csv
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

import requests

sys.path.insert(0, str(Path(__file__).resolve().parent))
from common import BASE_DIR, save_image  # noqa: E402

API = "https://api.inaturalist.org/v1/observations"
OUT = BASE_DIR / "dataset_v2c" / "_raw" / "inat"
LICENCIAS = "cc0,cc-by,cc-by-sa"
POTATO = "Solanum tuberosum"
OTROS = [
    "Solanum lycopersicum", "Solanum melongena", "Capsicum annuum", "Solanum nigrum",
    "Solanum americanum", "Zea mays", "Chenopodium quinoa", "Vicia faba",
    "Phaseolus vulgaris", "Triticum aestivum", "Hordeum vulgare", "Medicago sativa",
    "Beta vulgaris", "Pisum sativum", "Brassica oleracea", "Lactuca sativa",
    "Ipomoea batatas", "Manihot esculenta", "Cucurbita pepo", "Allium cepa",
]
HEADERS = {"User-Agent": "PROINPA-tesis-prototipo (investigacion academica)"}


def observaciones(taxon: str, maximo: int) -> list[dict]:
    """Observaciones con foto y licencia abierta (una foto por observación)."""
    resultado: list[dict] = []
    pagina = 1
    while len(resultado) < maximo:
        for intento in range(5):
            r = requests.get(
                API,
                params={
                    "taxon_name": taxon, "photos": "true", "photo_license": LICENCIAS,
                    "per_page": 100, "page": pagina, "order_by": "votes", "locale": "es",
                },
                headers=HEADERS, timeout=60,
            )
            if r.status_code == 429:
                time.sleep(10 * (intento + 1))
                continue
            r.raise_for_status()
            break
        datos = r.json()["results"]
        if not datos:
            break
        for obs in datos:
            for foto in obs.get("photos", []):
                if foto.get("license_code") in ("cc0", "cc-by", "cc-by-sa") and foto.get("url"):
                    resultado.append(
                        {
                            "obs": obs["id"],
                            "foto": foto["id"],
                            "url": foto["url"].replace("square", "medium"),
                            "licencia": foto["license_code"],
                            "autor": (foto.get("attribution") or "")[:200],
                            "enlace": obs.get("uri", ""),
                        }
                    )
                    break  # una foto por observación
        pagina += 1
        time.sleep(1.0)
        if len(datos) < 100:
            break
    return resultado[:maximo]


def descargar(item: dict, destino: Path) -> bool:
    if destino.exists():
        return True
    r = requests.get(item["url"], headers=HEADERS, timeout=60)
    r.raise_for_status()
    return save_image(r.content, destino)


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--papa", type=int, default=900, help="máximo de fotos de papa")
    p.add_argument("--otro-por-especie", type=int, default=90)
    p.add_argument("--workers", type=int, default=4)
    args = p.parse_args()

    filas: list[dict] = []
    tareas: list[tuple[dict, Path]] = []
    plan = [("potato", POTATO, args.papa)] + [("other", t, args.otro_por_especie) for t in OTROS]
    for clase, taxon, maximo in plan:
        obs = observaciones(taxon, maximo)
        print(f"[iNaturalist] {taxon}: {len(obs)} fotos", flush=True)
        for o in obs:
            destino = OUT / clase / f"inat_{o['obs']}.jpg"
            tareas.append((o, destino))
            filas.append({"clase": clase, "taxon": taxon, "archivo": destino.name, **{k: o[k] for k in ("obs", "licencia", "autor", "enlace")}})

    hechas = fallidas = 0
    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        futuros = [pool.submit(descargar, *t) for t in tareas]
        for f in as_completed(futuros):
            try:
                ok = f.result()
            except Exception:  # noqa: BLE001
                ok = False
            hechas += 1
            fallidas += 0 if ok else 1
            if hechas % 200 == 0 or hechas == len(tareas):
                print(f"  descargadas {hechas}/{len(tareas)} (fallidas {fallidas})", flush=True)

    OUT.mkdir(parents=True, exist_ok=True)
    with (OUT / "atribuciones.csv").open("w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=["clase", "taxon", "archivo", "obs", "licencia", "autor", "enlace"])
        w.writeheader()
        w.writerows(filas)
    print("Atribuciones guardadas en", OUT / "atribuciones.csv")


if __name__ == "__main__":
    main()
