from __future__ import annotations

from fastapi import FastAPI, File, HTTPException, UploadFile

from app.inference import (
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
        raise HTTPException(
            status_code=422,
            detail=(
                "La imagen debe estar en formato "
                "JPG, JPEG, PNG o WEBP."
            ),
        )

    # ---------------------------------------------------------
    # LEER ARCHIVO
    # ---------------------------------------------------------

    raw = await file.read()

    if not raw:
        raise HTTPException(
            status_code=422,
            detail="La imagen está vacía.",
        )

    # ---------------------------------------------------------
    # VALIDAR PESO
    # ---------------------------------------------------------

    if len(raw) > MAX_IMAGE_SIZE:
        raise HTTPException(
            status_code=413,
            detail="La imagen no debe superar los 5 MB.",
        )

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
        raise HTTPException(
            status_code=422,
            detail=str(exc),
        ) from exc

    except Exception as exc:  # noqa: BLE001
        raise HTTPException(
            status_code=500,
            detail=(
                "No se pudo ejecutar la inferencia CNN: "
                f"{exc}"
            ),
        ) from exc