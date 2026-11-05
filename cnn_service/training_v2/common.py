"""Utilidades compartidas del prototipo CNN v2 (descarga de datasets y rutas)."""

from __future__ import annotations

import io
import json
import struct
import time
import zlib
from pathlib import Path

import requests
from PIL import Image

BASE_DIR = Path(__file__).resolve().parents[1]
DATA_DIR = BASE_DIR / "dataset_v2"
RAW_DIR = DATA_DIR / "_raw"
EXTERNAL_DIR = DATA_DIR / "external"
MODEL_DIR = BASE_DIR / "model_v2"
ARTIFACTS_DIR = BASE_DIR / "artifacts_v2"

# Orden alfabético: es el que usa image_dataset_from_directory.
CLASS_NAMES = ["early_blight", "healthy", "late_blight", "other"]

CLASS_MAPPING = {
    "early_blight": {"code": "CNN-TTP", "label": "Tizón Temprano"},
    "healthy": {"code": "CNN-SANA", "label": "Sana (Sin enfermedad)"},
    "late_blight": {"code": "CNN-TT", "label": "Tizón Tardío"},
    # Clase de rechazo: el servicio la responde como "no parece una hoja de papa".
    "other": {"code": None, "label": "No es una hoja de papa"},
}

MAX_SIDE = 640  # las imágenes se reducen a este lado máximo al guardarlas


def save_image(raw: bytes, destination: Path) -> bool:
    """Guarda una imagen como JPEG RGB (lado máximo MAX_SIDE). False si está dañada."""
    try:
        image = Image.open(io.BytesIO(raw))
        image = image.convert("RGB")
        image.thumbnail((MAX_SIDE, MAX_SIDE), Image.Resampling.LANCZOS)
        destination.parent.mkdir(parents=True, exist_ok=True)
        image.save(destination, format="JPEG", quality=92)
        return True
    except Exception:  # noqa: BLE001 - imagen corrupta: se omite
        return False


class RemoteZip:
    """Lee archivos sueltos de un .zip remoto con peticiones HTTP Range.

    Evita descargar zips de varios GB: solo se baja el directorio central y
    los miembros que se piden (una petición por archivo).
    """

    def __init__(self, url: str, cache: Path | None = None) -> None:
        self.url = url
        self.session = requests.Session()
        if cache is not None and cache.exists():
            self.size = 0
            self.entries = json.loads(cache.read_text(encoding="utf-8"))
            return
        head = self.session.head(url, allow_redirects=True, timeout=60)
        head.raise_for_status()
        self.size = int(head.headers["Content-Length"])
        self.entries = self._read_central_directory()
        if cache is not None:
            cache.parent.mkdir(parents=True, exist_ok=True)
            cache.write_text(json.dumps(self.entries), encoding="utf-8")

    def _range(self, start: int, end: int) -> bytes:
        for intento in range(8):
            try:
                response = self.session.get(
                    self.url,
                    headers={"Range": f"bytes={start}-{end}"},
                    timeout=120,
                    allow_redirects=True,
                )
                if response.status_code == 429:
                    # Límite de peticiones: se respeta Retry-After y se reintenta.
                    espera = float(response.headers.get("Retry-After", 0)) or 2 ** (intento + 1)
                    time.sleep(min(espera, 90))
                    raise IOError("HTTP 429")
                if response.status_code != 206:
                    raise IOError(f"HTTP {response.status_code} (se esperaba 206)")
                return response.content
            except (requests.RequestException, IOError):
                if intento == 7:
                    raise
                time.sleep(1 + intento)
        raise RuntimeError("inalcanzable")

    def _read_central_directory(self) -> list[dict]:
        tail_size = min(self.size, 1 << 16)
        tail = self._range(self.size - tail_size, self.size - 1)
        eocd = tail.rfind(b"PK\x05\x06")
        if eocd < 0:
            raise ValueError("No se encontró el final del zip")
        (_, _, _, _, total, cd_size, cd_offset, _) = struct.unpack(
            "<4sHHHHIIH", tail[eocd : eocd + 22]
        )
        if cd_offset == 0xFFFFFFFF or total == 0xFFFF:
            raise ValueError("Zip64 no soportado por este lector")
        directory = self._range(cd_offset, cd_offset + cd_size - 1)
        entries, pos = [], 0
        while pos < len(directory) and directory[pos : pos + 4] == b"PK\x01\x02":
            (
                _, _, _, _, method, _, _, crc, comp_size, size, name_len, extra_len,
                comment_len, _, _, _, header_offset,
            ) = struct.unpack("<4sHHHHHHIIIHHHHHII", directory[pos : pos + 46])
            name = directory[pos + 46 : pos + 46 + name_len].decode("utf-8", "replace")
            entries.append(
                {
                    "name": name,
                    "method": method,
                    "crc": crc,
                    "comp_size": comp_size,
                    "size": size,
                    "offset": header_offset,
                }
            )
            pos += 46 + name_len + extra_len + comment_len
        return entries

    def read(self, entry: dict) -> bytes:
        """Descarga y descomprime un miembro (una sola petición HTTP)."""
        start = entry["offset"]
        buffer = self._range(start, start + 30 + 1024 + entry["comp_size"] - 1)
        if buffer[:4] != b"PK\x03\x04":
            raise ValueError("Cabecera local inválida")
        name_len, extra_len = struct.unpack("<HH", buffer[26:30])
        data_start = 30 + name_len + extra_len
        data = buffer[data_start : data_start + entry["comp_size"]]
        if len(data) < entry["comp_size"]:
            raise IOError("Descarga incompleta")
        if entry["method"] == 0:
            payload = data
        elif entry["method"] == 8:
            payload = zlib.decompress(data, -15)
        else:
            raise ValueError(f"Método de compresión {entry['method']} no soportado")
        if zlib.crc32(payload) & 0xFFFFFFFF != entry["crc"]:
            raise IOError("CRC incorrecto")
        return payload
