from __future__ import annotations

from threading import Thread

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.responses import JSONResponse

from app.inference import (
    CONSEJOS,
    InvalidImageError,
    ModelNotReadyError,
    ModelRuntime,
)


MAX_IMAGE_SIZE = 5 * 1024 * 1024

ALLOWED_CONTENT_TYPES = {
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/webp",
}


app = FastAPI(
    title="PROINPA CNN Service",
    version="1.1.0",
    description=(
        "Microservicio de inferencia para clasificación "
        "de enfermedades foliares en cultivos de papa."
    ),
)


runtime = ModelRuntime()


@app.on_event("startup")
def precargar_modelo() -> None:
    """Carga el modelo y Grad-CAM en segundo plano, sin retrasar el arranque del servicio."""

    Thread(target=runtime.warmup, daemon=True).start()


def _rechazo(
    status: int,
    mensaje: str,
    codigo: str,
    consejo: str | None = None,
) -> JSONResponse:
    """
    Respuesta de rechazo con motivo estructurado.

    Mantiene `detail` (texto) por compatibilidad con Laravel y agrega
    `codigo` y `consejo` para mostrar al usuario qué hacer.
    """

    return JSONResponse(
        status_code=status,
        content={
            "detail": mensaje,
            "codigo": codigo,
            "consejo": consejo or CONSEJOS.get(codigo, ""),
        },
    )


@app.get("/health")
def health() -> dict:
    """
    Verifica el estado del microservicio CNN.
    """
    return runtime.status()


@app.post("/predict")
async def predict(
    file: UploadFile = File(...)
) -> dict:
    """
    Recibe una imagen, valida su calidad y,
    si cumple los requisitos mínimos,
    ejecuta la clasificación CNN.
    """

    # ---------------------------------------------------------
    # VALIDAR TIPO MIME
    # ---------------------------------------------------------

    if file.content_type not in ALLOWED_CONTENT_TYPES:
        return _rechazo(
            422,
            "La imagen debe estar en formato JPG, JPEG, PNG o WEBP.",
            "formato_invalido",
        )

    # ---------------------------------------------------------
    # LEER ARCHIVO
    # ---------------------------------------------------------

    raw = await file.read()

    if not raw:
        return _rechazo(422, "La imagen está vacía.", "imagen_vacia")

    # ---------------------------------------------------------
    # VALIDAR PESO
    # ---------------------------------------------------------

    if len(raw) > MAX_IMAGE_SIZE:
        return _rechazo(413, "La imagen no debe superar los 5 MB.", "peso_excesivo")

    # ---------------------------------------------------------
    # VALIDACIÓN + INFERENCIA
    # ---------------------------------------------------------

    try:
        return runtime.predict_bytes(raw)

    except ModelNotReadyError as exc:
        raise HTTPException(
            status_code=503,
            detail=str(exc),
        ) from exc

    except InvalidImageError as exc:
        return _rechazo(422, str(exc), exc.codigo, exc.consejo)

    except Exception as exc:  # noqa: BLE001
        raise HTTPException(
            status_code=500,
            detail=(
                "No se pudo ejecutar la inferencia CNN: "
                f"{exc}"
            ),
        ) from exc