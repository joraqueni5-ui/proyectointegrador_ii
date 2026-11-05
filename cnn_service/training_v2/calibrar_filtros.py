"""Mide los filtros de calidad del servicio (nitidez y material vegetal).

Usa las funciones reales de app/inference.py, así que lo que se mide es lo que
se ejecuta en producción. Reporta:

  * falsos rechazos: fotos reales de papa/hojas que los filtros rechazan;
  * detección de desenfoque: las mismas fotos con desenfoque gaussiano artificial;
  * (opcional) una carpeta propia de fotos de prueba: estado de cada una.

Uso (desde cnn_service/):
    python training_v2/calibrar_filtros.py
    python training_v2/calibrar_filtros.py --carpeta "C:/Users/HP/Pictures/proinpa"
"""

from __future__ import annotations

import argparse
import glob
import random
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter

BASE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BASE))
from app.inference import InvalidImageError, ModelRuntime  # noqa: E402


def muestra(patron: str, n: int, rng: random.Random) -> list[str]:
    archivos = sorted(glob.glob(str(BASE / patron), recursive=True))
    rng.shuffle(archivos)
    return archivos[:n]


def motivo(rt: ModelRuntime, imagen: Image.Image) -> str:
    """'ok' o el filtro que rechaza la foto (mismo orden que predict_bytes)."""
    try:
        calidad = rt._validate_basic_quality(imagen)
    except InvalidImageError:
        return "calidad"
    try:
        rt._validate_probable_vegetation(imagen)
    except InvalidImageError:
        return "no_vegetal"
    try:
        rt._validate_sharpness(calidad)
    except InvalidImageError:
        return "borrosa"
    return "ok"


def mov(im: Image.Image, largo: int) -> Image.Image:
    """Desenfoque de movimiento horizontal (cámara que se mueve al disparar)."""
    a = np.asarray(im, dtype=np.float32)
    acc = np.zeros_like(a)
    for k in range(largo):
        acc += np.roll(a, k - largo // 2, axis=1)
    return Image.fromarray(np.clip(acc / largo, 0, 255).astype("uint8"))


def ruido(im: Image.Image, sigma: float) -> Image.Image:
    a = np.asarray(im, dtype=np.float32) + np.random.RandomState(0).normal(0, sigma, (im.height, im.width, 3))
    return Image.fromarray(np.clip(a, 0, 255).astype("uint8"))


def jpeg(im: Image.Image, calidad: int) -> Image.Image:
    import io
    b = io.BytesIO()
    im.save(b, "JPEG", quality=calidad)
    return Image.open(io.BytesIO(b.getvalue())).convert("RGB")


def escala(im: Image.Image, f: float) -> Image.Image:
    a = np.asarray(im, dtype=np.float32) * f
    return Image.fromarray(np.clip(a, 0, 255).astype("uint8"))


DEGRADACIONES = {
    "movimiento leve (7 px)": lambda i: mov(i, 7),
    "movimiento fuerte (21 px)": lambda i: mov(i, 21),
    "poca luz (x0.45)": lambda i: escala(i, 0.45),
    "muy oscura (x0.2)": lambda i: escala(i, 0.2),
    "sobreexpuesta (+110)": lambda i: Image.fromarray(np.clip(np.asarray(i, dtype=np.float32) + 110, 0, 255).astype("uint8")),
    "ruido alto (sigma 25)": lambda i: ruido(i, 25),
    "JPEG muy comprimido (q=15)": lambda i: jpeg(i, 15),
}


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawTextHelpFormatter)
    p.add_argument("--carpeta", default=None, help="carpeta con fotos de prueba propias")
    p.add_argument("--n", type=int, default=150)
    args = p.parse_args()

    rt = ModelRuntime()
    rng = random.Random(7)

    conjuntos = {
        "Tanzania (papa, campo)": muestra("dataset_v2b/test/*[hb]*/*.jpg", args.n, rng),
        "Perú (papa, altiplano)": muestra("dataset_v2b/external/peru_cajamarca/*/*.jpg", args.n, rng),
        "PlantDoc papa (internet)": muestra("dataset_v2b/external/plantdoc_potato/*/*.jpg", 75, rng),
        "Otras hojas (no papa)": muestra("dataset_v2b/test/other/*.jpg", args.n, rng),
    }

    print("\n== Falsos rechazos en fotos reales (debería ser ~0 %) ==")
    nitidas: list[str] = []
    for nombre, archivos in conjuntos.items():
        if not archivos:
            continue
        conteo = {"ok": 0, "calidad": 0, "no_vegetal": 0, "borrosa": 0}
        for f in archivos:
            conteo[motivo(rt, Image.open(f).convert("RGB"))] += 1
        n = len(archivos)
        print(f"{nombre:28s} n={n:3d}  aceptadas {100*conteo['ok']/n:5.1f}%  "
              f"borrosa {100*conteo['borrosa']/n:4.1f}%  no_vegetal {100*conteo['no_vegetal']/n:4.1f}%  calidad {100*conteo['calidad']/n:4.1f}%")
        if "papa" in nombre:
            nitidas += archivos

    print("\n== Detección de desenfoque (mismas fotos de papa, desenfoque artificial) ==")
    base = [Image.open(f).convert("RGB") for f in nitidas[:150]]
    for sigma in (0.0, 1.0, 1.5, 2.5, 4.0):
        borrosas = sum(
            motivo(rt, im if sigma == 0 else im.filter(ImageFilter.GaussianBlur(sigma))) == "borrosa"
            for im in base
        )
        etiqueta = "original" if sigma == 0 else f"sigma {sigma}"
        print(f"  {etiqueta:10s} marcadas como borrosas: {100*borrosas/len(base):5.1f}%")

    print("\n== Degradaciones realistas (qué filtro actúa; sobre las mismas fotos de papa) ==")
    for nombre, fn in DEGRADACIONES.items():
        c = {"ok": 0, "calidad": 0, "no_vegetal": 0, "borrosa": 0}
        for im in base[:80]:
            c[motivo(rt, fn(im))] += 1
        n = 80
        print(f"  {nombre:28s} aceptadas {100*c['ok']/n:5.1f}%  borrosa {100*c['borrosa']/n:5.1f}%  calidad(luz/contraste) {100*c['calidad']/n:5.1f}%  no_vegetal {100*c['no_vegetal']/n:5.1f}%")

    if args.carpeta:
        print(f"\n== Carpeta {args.carpeta} ==")
        for f in sorted(glob.glob(args.carpeta + "/*.*")):
            try:
                imagen = rt._decode_image(Path(f).read_bytes())
            except InvalidImageError as exc:
                print(f"  {Path(f).name[:44]:44s} rechazada al leer: {exc}")
                continue
            calidad = rt._calculate_quality_metrics(imagen)
            print(f"  {Path(f).name[:44]:44s} {imagen.size[0]}x{imagen.size[1]:<5} nitidez={calidad['sharpness']:.2f}  -> {motivo(rt, imagen)}")


if __name__ == "__main__":
    main()
