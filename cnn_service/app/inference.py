from __future__ import annotations

import io
import json
import os

from pathlib import Path
from threading import Lock
from typing import Any

import numpy as np

from PIL import (
    Image,
    ImageFilter,
    ImageOps,
    UnidentifiedImageError,
)

from app.gradcam import (
    GradCAM,
    generar as generar_gradcam,
)


# ============================================================
# CONFIGURACIÓN GENERAL
# ============================================================

os.environ.setdefault(
    "TF_CPP_MIN_LOG_LEVEL",
    "2",
)


# ============================================================
# FORMATOS PERMITIDOS
# ============================================================

SUPPORTED_IMAGE_FORMATS = {
    "JPEG",
    "PNG",
    "WEBP",
}

# AVIF (común en imágenes descargadas de internet): solo si está instalado
# el plugin opcional "pillow-avif-plugin".
try:
    import pillow_avif  # noqa: F401

    SUPPORTED_IMAGE_FORMATS.add("AVIF")
except ImportError:  # pragma: no cover
    pass


# ============================================================
# RESOLUCIÓN
# ============================================================

# El modelo recibe 224x224, pero las fotos de internet pequeñas (~270x180)
# todavía son diagnosticables: se rechaza solo por debajo de 160 px.
MIN_IMAGE_WIDTH = int(
    os.getenv(
        "CNN_MIN_IMAGE_WIDTH",
        "160",
    )
)

MIN_IMAGE_HEIGHT = int(
    os.getenv(
        "CNN_MIN_IMAGE_HEIGHT",
        "160",
    )
)

MAX_IMAGE_PIXELS = int(
    os.getenv(
        "CNN_MAX_IMAGE_PIXELS",
        "24000000",
    )
)


# ============================================================
# ILUMINACIÓN
# ============================================================

MIN_MEAN_BRIGHTNESS = float(
    os.getenv(
        "CNN_MIN_MEAN_BRIGHTNESS",
        "35",
    )
)

MAX_MEAN_BRIGHTNESS = float(
    os.getenv(
        "CNN_MAX_MEAN_BRIGHTNESS",
        "220",
    )
)

DARK_PIXEL_LIMIT = int(
    os.getenv(
        "CNN_DARK_PIXEL_LIMIT",
        "25",
    )
)

BRIGHT_PIXEL_LIMIT = int(
    os.getenv(
        "CNN_BRIGHT_PIXEL_LIMIT",
        "245",
    )
)

MAX_DARK_FRACTION = float(
    os.getenv(
        "CNN_MAX_DARK_FRACTION",
        "0.65",
    )
)

MAX_BRIGHT_FRACTION = float(
    os.getenv(
        "CNN_MAX_BRIGHT_FRACTION",
        "0.65",
    )
)


# ============================================================
# CONTRASTE
# ============================================================

# Una foto realmente sobreexpuesta o subexpuesta es plana (poco contraste).
# Las fotos de estudio con fondo blanco o negro y la hoja bien marcada tienen
# contraste alto (42-63) y deben aceptarse.
MAX_EXPOSURE_CONTRAST_STD = float(
    os.getenv(
        "CNN_MAX_EXPOSURE_CONTRAST_STD",
        "35",
    )
)

MIN_CONTRAST_STD = float(
    os.getenv(
        "CNN_MIN_CONTRAST_STD",
        "8",
    )
)


# ============================================================
# NITIDEZ
# ============================================================

# La nitidez es RELATIVA (método de re-desenfoque): mide qué fracción del
# detalle de la foto se pierde al desenfocarla otra vez. Una foto nítida
# pierde mucho (valor alto); una foto ya borrosa casi nada (valor bajo).
# Así no depende de la resolución ni de cuánta textura tenga la hoja
# (la varianza del Laplaciano anterior rechazaba fotos nítidas con poca textura).
#
# Calibración (training_v2/calibrar_filtros.py): fotos reales de papa en campo
# dan >= 0.51 (percentil 1); las mismas fotos con desenfoque gaussiano
# sigma 2.5 dan <= 0.30; se toma el peor de los ejes horizontal/vertical
# (detecta también desenfoque de movimiento). Umbral 0.42: ~0 % de falsos rechazos.
SHARPNESS_ANALYSIS_SIDE = 640

SHARPNESS_REBLUR_RADIUS = float(
    os.getenv(
        "CNN_SHARPNESS_REBLUR_RADIUS",
        "2.0",
    )
)

MIN_SHARPNESS_SCORE = float(
    os.getenv(
        "CNN_MIN_SHARPNESS_SCORE",
        "0.42",
    )
)


# ============================================================
# CONTENIDO VEGETAL
# ============================================================

# Calibración: en fotos reales de papa el percentil 1 de píxeles verdes es
# 13 % (global); en selfis/personas es 0-2 %. Los umbrales anteriores (2,5 %)
# dejaban pasar fotos de personas por un margen mínimo.
MIN_VEGETATION_FRACTION = float(
    os.getenv(
        "CNN_MIN_VEGETATION_FRACTION",
        "0.08",
    )
)

MIN_CENTER_VEGETATION_FRACTION = float(
    os.getenv(
        "CNN_MIN_CENTER_VEGETATION_FRACTION",
        "0.05",
    )
)

MIN_EXCESS_GREEN_PERCENTILE = float(
    os.getenv(
        "CNN_MIN_EXCESS_GREEN_PERCENTILE",
        "20",
    )
)

MIN_CORE_VEGETATION_FRACTION = float(
    os.getenv(
        "CNN_MIN_CORE_VEGETATION_FRACTION",
        "0.04",
    )
)

MIN_VERTICAL_SPREAD_FRACTION = float(
    os.getenv(
        "CNN_MIN_VERTICAL_SPREAD_FRACTION",
        "0.02",
    )
)


# ============================================================
# CONFIANZA CNN
# ============================================================

MIN_MODEL_CONFIDENCE = float(
    os.getenv(
        "CNN_MIN_MODEL_CONFIDENCE",
        "55",
    )
)

MIN_PREDICTION_MARGIN = float(
    os.getenv(
        "CNN_MIN_PREDICTION_MARGIN",
        "8",
    )
)


# ============================================================
# EXPLICABILIDAD (Grad-CAM)
# ============================================================

# CNN_GRADCAM=0 desactiva el mapa de calor (el diagnóstico no cambia).
GRADCAM_ENABLED = os.getenv(
    "CNN_GRADCAM",
    "1",
).strip().lower() not in {"0", "false", "no"}


# ============================================================
# EXCEPCIONES
# ============================================================

class ModelNotReadyError(RuntimeError):
    """
    El servicio está disponible, pero no existe
    un modelo CNN listo para inferencia.
    """


# Consejo concreto para cada motivo de rechazo (se muestra al usuario).
CONSEJOS = {
    "formato_invalido": "Use una fotografía en formato JPG, PNG o WEBP.",
    "imagen_animada": "Suba una fotografía estática (no un GIF ni una animación).",
    "archivo_danado": "Vuelva a tomar o a exportar la foto: el archivo no se pudo leer.",
    "resolucion_baja": "Acérquese a la hoja o use una foto de al menos 160×160 píxeles (mejor de 1000 px o más).",
    "resolucion_excesiva": "Reduzca la resolución de la foto antes de subirla.",
    "muy_oscura": "Tome la foto con luz natural, de día y sin sombras fuertes sobre la hoja.",
    "sobreexpuesta": "Evite el sol directo sobre la hoja: fotografíela a la sombra o con cielo nublado.",
    "poco_contraste": "Tome la foto con luz uniforme y ponga la hoja sobre un fondo distinto (suelo o cielo).",
    "no_vegetal": "Encuadre una hoja de papa de cerca, centrada y que ocupe gran parte de la foto.",
    "borrosa": "Sostenga el celular firme, toque la hoja en la pantalla para enfocar y espere un segundo antes de disparar.",
    "prediccion_invalida": "Intente nuevamente con otra fotografía.",
    "baja_certeza": "Acérquese a una sola hoja con síntomas, con buena luz y buen enfoque, y repita la foto.",
    "clases_similares": "Tome una foto más clara de una sola hoja; si persiste, consulte a un agrónomo.",
    "no_es_papa": "Fotografíe una hoja de papa (no otra planta, suelo ni objetos), de cerca y sobre un fondo simple.",
    "peso_excesivo": "Reduzca el tamaño de la foto a menos de 5 MB.",
    "imagen_vacia": "El archivo está vacío: seleccione otra fotografía.",
}


class InvalidImageError(ValueError):
    """
    La fotografía no cumple las condiciones necesarias
    para ejecutar un diagnóstico confiable.

    Lleva un código estable (para que el frontend decida qué mostrar)
    y un consejo concreto para volver a tomar la foto.
    """

    def __init__(
        self,
        mensaje: str,
        codigo: str = "imagen_no_valida",
        consejo: str | None = None,
    ) -> None:

        super().__init__(mensaje)

        self.codigo = codigo

        self.consejo = (
            consejo
            or CONSEJOS.get(codigo)
            or "Tome otra fotografía de la hoja con buena luz y enfoque."
        )


# ============================================================
# RUNTIME DEL MODELO
# ============================================================

class ModelRuntime:

    def __init__(self) -> None:

        base_dir = (
            Path(__file__)
            .resolve()
            .parents[1]
        )

        # Modelo por defecto: prototipo v2.1 (ResNet50 con fotos de campo y clase
        # "no es papa"). CNN_MODEL_PATH / CNN_METADATA_PATH permiten usar otro
        # (por ejemplo, el modelo v1 en model/ o el prototipo v2c).
        default_dir = base_dir / "model_v2b"

        self.model_path = Path(
            os.getenv(
                "CNN_MODEL_PATH",
                str(default_dir / "potato_resnet50_v2.keras"),
            )
        )

        self.metadata_path = Path(
            os.getenv(
                "CNN_METADATA_PATH",
                str(default_dir / "model_metadata.json"),
            )
        )

        self._model = None

        self._gradcam: GradCAM | None = None

        self._gradcam_lock = Lock()

        self._metadata: (
            dict[str, Any]
            | None
        ) = None

        self._loaded_signature: (
            tuple[float, float]
            | None
        ) = None

        self._lock = Lock()


    # ========================================================
    # FIRMA
    # ========================================================

    def _signature(
        self,
    ) -> tuple[float, float] | None:

        if (
            not self.model_path.exists()
            or
            not self.metadata_path.exists()
        ):
            return None

        return (
            self.model_path.stat().st_mtime,
            self.metadata_path.stat().st_mtime,
        )


    # ========================================================
    # CARGAR MODELO
    # ========================================================

    def _load_if_needed(
        self,
    ) -> None:

        signature = self._signature()

        if signature is None:

            raise ModelNotReadyError(
                "No existe un modelo CNN entrenado. "
                "Ejecute primero el proceso de entrenamiento."
            )

        if (
            self._model is not None
            and
            self._metadata is not None
            and
            signature == self._loaded_signature
        ):
            return

        with self._lock:

            signature = self._signature()

            if signature is None:

                raise ModelNotReadyError(
                    "No existe un modelo CNN entrenado."
                )

            if (
                self._model is not None
                and
                self._metadata is not None
                and
                signature == self._loaded_signature
            ):
                return

            import tensorflow as tf

            with self.metadata_path.open(
                "r",
                encoding="utf-8",
            ) as fh:

                metadata = json.load(
                    fh
                )

            required = [
                "class_names",
                "class_mapping",
                "image_size",
                "model_version",
            ]

            missing = [
                key
                for key in required
                if key not in metadata
            ]

            if missing:

                raise RuntimeError(
                    "Metadatos incompletos del modelo. "
                    "Faltan: "
                    + ", ".join(missing)
                )

            self._model = (
                tf.keras.models.load_model(
                    self.model_path
                )
            )

            self._metadata = metadata

            # El calculador de Grad-CAM depende del modelo cargado.
            self._gradcam = None

            self._loaded_signature = (
                signature
            )


    # ========================================================
    # GRAD-CAM (se crea una sola vez por modelo cargado)
    # ========================================================

    def _obtener_gradcam(
        self,
    ) -> GradCAM:

        with self._gradcam_lock:

            if self._gradcam is None:

                self._gradcam = GradCAM(
                    self._model
                )

            return self._gradcam


    # ========================================================
    # PRECARGA (al iniciar el servicio)
    #
    # Carga el modelo y compila Grad-CAM antes de la primera
    # fotografía, para que el primer usuario no espere ~8 s.
    # Es opcional: si falla, todo se hace al primer uso.
    # ========================================================

    def warmup(
        self,
    ) -> None:

        try:

            self._load_if_needed()

            if GRADCAM_ENABLED:

                self._obtener_gradcam()

        except Exception:  # noqa: BLE001

            pass


    # ========================================================
    # HEALTH
    # ========================================================

    def status(
        self,
    ) -> dict[str, Any]:

        signature = self._signature()

        if signature is None:

            return {
                "status":
                    "waiting_model",

                "model_loaded":
                    False,

                "model_path":
                    str(
                        self.model_path
                    ),

                "message":
                    (
                        "Servicio disponible. "
                        "Falta entrenar o copiar el modelo CNN."
                    ),

                "image_validation":
                    True,
            }

        try:

            self._load_if_needed()

            return {
                "status":
                    "ready",

                "model_loaded":
                    True,

                "model_version":
                    self._metadata.get(
                        "model_version"
                    )
                    if self._metadata
                    else None,

                "classes":
                    self._metadata.get(
                        "class_names"
                    )
                    if self._metadata
                    else [],

                "image_validation":
                    True,

                "validation_rules": {

                    "min_width":
                        MIN_IMAGE_WIDTH,

                    "min_height":
                        MIN_IMAGE_HEIGHT,

                    "blur_detection":
                        True,

                    "blur_threshold":
                        MIN_SHARPNESS_SCORE,

                    "blur_region":
                        "center_70_percent",

                    "illumination_detection":
                        True,

                    "contrast_detection":
                        True,

                    "content_validation":
                        True,

                    "min_vegetation_fraction":
                        MIN_VEGETATION_FRACTION,

                    "min_center_vegetation_fraction":
                        MIN_CENTER_VEGETATION_FRACTION,

                    "min_core_vegetation_fraction":
                        MIN_CORE_VEGETATION_FRACTION,

                    "min_vertical_spread_fraction":
                        MIN_VERTICAL_SPREAD_FRACTION,

                    "prediction_confidence_min":
                        MIN_MODEL_CONFIDENCE,

                    "prediction_margin_min":
                        MIN_PREDICTION_MARGIN,
                },
            }

        except Exception as exc:  # noqa: BLE001

            return {
                "status":
                    "error",

                "model_loaded":
                    False,

                "message":
                    str(exc),

                "image_validation":
                    True,
            }


    # ========================================================
    # DECODIFICAR IMAGEN
    # ========================================================

    def _decode_image(
        self,
        raw: bytes,
    ) -> Image.Image:

        try:

            with Image.open(
                io.BytesIO(raw)
            ) as test_image:

                image_format = (
                    test_image.format
                    or ""
                ).upper()

                if (
                    image_format
                    not in SUPPORTED_IMAGE_FORMATS
                ):

                    raise InvalidImageError(
                        "El archivo no corresponde a una "
                        "imagen JPG, PNG, WEBP o AVIF válida.",
                codigo="formato_invalido",
            )

                frames = getattr(
                    test_image,
                    "n_frames",
                    1,
                )

                if frames > 1:

                    raise InvalidImageError(
                        "No se permiten imágenes animadas. "
                        "Utilice una fotografía estática.",
                codigo="imagen_animada",
            )

                test_image.verify()

            with Image.open(
                io.BytesIO(raw)
            ) as source_image:

                image = (
                    ImageOps.exif_transpose(
                        source_image
                    )
                )

                image = image.convert(
                    "RGB"
                )

                image.load()

                return image.copy()

        except InvalidImageError:
            raise

        except (
            UnidentifiedImageError,
            OSError,
            SyntaxError,
            ValueError,
        ) as exc:

            raise InvalidImageError(
                "El archivo está dañado o su formato no es compatible (use JPG, PNG o WEBP). "
                "no corresponde a una imagen válida.",
                codigo="archivo_danado",
            ) from exc


    # ========================================================
    # NITIDEZ RELATIVA (RE-DESENFOQUE)
    #
    # Se compara la foto con una versión más desenfocada de sí
    # misma: una foto nítida pierde mucho gradiente, una ya
    # borrosa casi nada. Devuelve 0 (muy borrosa) a ~1 (nítida).
    # ========================================================

    @staticmethod
    def _relative_sharpness(
        image: Image.Image,
    ) -> float:

        analysis = image.copy()

        analysis.thumbnail(
            (
                SHARPNESS_ANALYSIS_SIDE,
                SHARPNESS_ANALYSIS_SIDE,
            ),
            Image.Resampling.LANCZOS,
        )

        gray = analysis.convert("L")

        width, height = gray.size

        # Zona central: evita bordes, textos y marcas de agua.
        gray = gray.crop(
            (
                int(width * 0.15),
                int(height * 0.15),
                int(width * 0.85),
                int(height * 0.85),
            )
        )

        if min(gray.size) < 8:
            return 0.0

        sharp = np.asarray(
            gray,
            dtype=np.float32,
        )

        blurred = np.asarray(
            gray.filter(
                ImageFilter.GaussianBlur(
                    radius=SHARPNESS_REBLUR_RADIUS
                )
            ),
            dtype=np.float32,
        )

        def gradients(
            array: np.ndarray,
        ) -> tuple[np.ndarray, np.ndarray]:

            return (
                np.abs(np.diff(array, axis=1))[:-1, :],
                np.abs(np.diff(array, axis=0))[:, :-1],
            )

        sharp_x, sharp_y = gradients(sharp)
        blur_x, blur_y = gradients(blurred)

        def retained_loss(
            original: np.ndarray,
            reblurred: np.ndarray,
        ) -> float:

            total = float(original.sum())

            if total <= 1e-6:
                return 0.0

            return float(
                np.maximum(0.0, original - reblurred).sum()
            ) / total

        # Se evalúa cada eje por separado y se toma el peor: el desenfoque
        # de movimiento solo borra los bordes en una dirección.
        return min(
            retained_loss(sharp_x, blur_x),
            retained_loss(sharp_y, blur_y),
        )


    # ========================================================
    # MÉTRICAS DE CALIDAD
    # ========================================================

    def _calculate_quality_metrics(
        self,
        image: Image.Image,
    ) -> dict[str, Any]:

        width, height = image.size

        quality_image = image.copy()

        quality_image.thumbnail(
            (1000, 1000),
            Image.Resampling.LANCZOS,
        )

        grayscale = (
            quality_image.convert(
                "L"
            )
        )

        gray_array = np.asarray(
            grayscale,
            dtype=np.float32,
        )


        # ----------------------------------------------------
        # BRILLO
        # ----------------------------------------------------

        brightness = float(
            np.mean(
                gray_array
            )
        )


        # ----------------------------------------------------
        # CONTRASTE
        # ----------------------------------------------------

        contrast = float(
            np.std(
                gray_array
            )
        )


        # ----------------------------------------------------
        # PÍXELES OSCUROS
        # ----------------------------------------------------

        dark_fraction = float(
            np.mean(
                gray_array
                <= DARK_PIXEL_LIMIT
            )
        )


        # ----------------------------------------------------
        # PÍXELES SOBREEXPUESTOS
        # ----------------------------------------------------

        bright_fraction = float(
            np.mean(
                gray_array
                >= BRIGHT_PIXEL_LIMIT
            )
        )


        # ----------------------------------------------------
        # NITIDEZ RELATIVA (zona central, escala fija)
        # ----------------------------------------------------

        sharpness = (
            self._relative_sharpness(
                image
            )
        )


        return {

            "width":
                int(width),

            "height":
                int(height),

            "pixels":
                int(
                    width * height
                ),

            "brightness":
                round(
                    brightness,
                    2,
                ),

            "contrast":
                round(
                    contrast,
                    2,
                ),

            "dark_percentage":
                round(
                    dark_fraction * 100,
                    2,
                ),

            "bright_percentage":
                round(
                    bright_fraction * 100,
                    2,
                ),

            "sharpness":
                round(
                    sharpness,
                    3,
                ),
        }


    # ========================================================
    # VALIDAR CALIDAD BÁSICA
    #
    # Aquí NO rechazamos todavía por desenfoque.
    # Primero comprobamos el contenido vegetal.
    # ========================================================

    def _validate_basic_quality(
        self,
        image: Image.Image,
    ) -> dict[str, Any]:

        metrics = (
            self._calculate_quality_metrics(
                image
            )
        )

        width = int(
            metrics[
                "width"
            ]
        )

        height = int(
            metrics[
                "height"
            ]
        )

        pixels = int(
            metrics[
                "pixels"
            ]
        )


        # ----------------------------------------------------
        # RESOLUCIÓN
        # ----------------------------------------------------

        if (
            width < MIN_IMAGE_WIDTH
            or
            height < MIN_IMAGE_HEIGHT
        ):

            raise InvalidImageError(
                "La imagen tiene una resolución "
                "demasiado baja. "
                f"Resolución recibida: "
                f"{width}x{height}px. "
                "Utilice una fotografía de al menos "
                f"{MIN_IMAGE_WIDTH}x"
                f"{MIN_IMAGE_HEIGHT}px.",
                codigo="resolucion_baja",
            )


        if (
            pixels > MAX_IMAGE_PIXELS
        ):

            raise InvalidImageError(
                "La resolución de la imagen es "
                "excesivamente grande.",
                codigo="resolucion_excesiva",
            )


        # ----------------------------------------------------
        # ILUMINACIÓN
        # ----------------------------------------------------

        brightness = float(
            metrics[
                "brightness"
            ]
        )

        dark_fraction = (
            float(
                metrics[
                    "dark_percentage"
                ]
            )
            / 100.0
        )

        bright_fraction = (
            float(
                metrics[
                    "bright_percentage"
                ]
            )
            / 100.0
        )


        flat_image = (
            float(metrics["contrast"])
            < MAX_EXPOSURE_CONTRAST_STD
        )

        if (
            flat_image
            and
            brightness
            < MIN_MEAN_BRIGHTNESS
            and
            dark_fraction
            > MAX_DARK_FRACTION
        ):

            raise InvalidImageError(
                "La fotografía está demasiado oscura. "
                "Tome nuevamente la imagen con "
                "mejor iluminación.",
                codigo="muy_oscura",
            )


        if (
            flat_image
            and
            brightness
            > MAX_MEAN_BRIGHTNESS
            and
            bright_fraction
            > MAX_BRIGHT_FRACTION
        ):

            raise InvalidImageError(
                "La fotografía está demasiado clara "
                "o sobreexpuesta. "
                "Evite luz directa intensa.",
                codigo="sobreexpuesta",
            )


        # ----------------------------------------------------
        # CONTRASTE
        # ----------------------------------------------------

        contrast = float(
            metrics[
                "contrast"
            ]
        )

        if (
            contrast
            < MIN_CONTRAST_STD
        ):

            raise InvalidImageError(
                "La imagen presenta muy poco contraste. "
                "Tome nuevamente la fotografía con "
                "una iluminación más uniforme.",
                codigo="poco_contraste",
            )


        return metrics


    # ========================================================
    # MÉTRICAS DE VEGETACIÓN
    # ========================================================

    def _calculate_vegetation_metrics(
        self,
        image: Image.Image,
    ) -> dict[str, float]:

        analysis_image = image.copy()

        analysis_image.thumbnail(
            (800, 800),
            Image.Resampling.BILINEAR,
        )

        rgb = np.asarray(
            analysis_image,
            dtype=np.float32,
        )

        r = rgb[
            :,
            :,
            0
        ]

        g = rgb[
            :,
            :,
            1
        ]

        b = rgb[
            :,
            :,
            2
        ]


        # ----------------------------------------------------
        # EXCESS GREEN
        #
        # ExG = 2G - R - B
        # ----------------------------------------------------

        excess_green = (
            2.0 * g
            - r
            - b
        )


        # ----------------------------------------------------
        # MÁSCARA VEGETAL
        # ----------------------------------------------------

        vegetation_mask = (
            (g > 40)
            &
            (g > r * 1.03)
            &
            (g > b * 1.03)
            &
            (excess_green > 12)
        )


        # ----------------------------------------------------
        # VEGETACIÓN GLOBAL
        # ----------------------------------------------------

        vegetation_fraction = float(
            np.mean(
                vegetation_mask
            )
        )


        mask_height, mask_width = (
            vegetation_mask.shape
        )


        # ----------------------------------------------------
        # ZONA CENTRAL AMPLIA
        # ----------------------------------------------------

        y1 = int(
            mask_height * 0.20
        )

        y2 = int(
            mask_height * 0.80
        )

        x1 = int(
            mask_width * 0.20
        )

        x2 = int(
            mask_width * 0.80
        )

        center_mask = (
            vegetation_mask[
                y1:y2,
                x1:x2
            ]
        )

        if (
            center_mask.size > 0
        ):

            center_fraction = float(
                np.mean(
                    center_mask
                )
            )

        else:

            center_fraction = 0.0


        # ----------------------------------------------------
        # NÚCLEO CENTRAL
        # ----------------------------------------------------

        core_y1 = int(
            mask_height * 0.30
        )

        core_y2 = int(
            mask_height * 0.70
        )

        core_x1 = int(
            mask_width * 0.30
        )

        core_x2 = int(
            mask_width * 0.70
        )

        core_mask = (
            vegetation_mask[
                core_y1:core_y2,
                core_x1:core_x2
            ]
        )

        if (
            core_mask.size > 0
        ):

            core_fraction = float(
                np.mean(
                    core_mask
                )
            )

        else:

            core_fraction = 0.0


        # ----------------------------------------------------
        # DISTRIBUCIÓN VERTICAL
        # ----------------------------------------------------

        middle_y = int(
            mask_height * 0.50
        )

        upper_mask = (
            vegetation_mask[
                :middle_y,
                :
            ]
        )

        lower_mask = (
            vegetation_mask[
                middle_y:,
                :
            ]
        )

        if (
            upper_mask.size > 0
        ):

            upper_fraction = float(
                np.mean(
                    upper_mask
                )
            )

        else:

            upper_fraction = 0.0


        if (
            lower_mask.size > 0
        ):

            lower_fraction = float(
                np.mean(
                    lower_mask
                )
            )

        else:

            lower_fraction = 0.0


        # ----------------------------------------------------
        # PERCENTIL DE EXCESO VERDE
        # ----------------------------------------------------

        excess_green_p90 = float(
            np.percentile(
                excess_green,
                90,
            )
        )


        return {

            "vegetation_fraction":
                vegetation_fraction,

            "center_vegetation_fraction":
                center_fraction,

            "core_vegetation_fraction":
                core_fraction,

            "upper_vegetation_fraction":
                upper_fraction,

            "lower_vegetation_fraction":
                lower_fraction,

            "excess_green_p90":
                excess_green_p90,
        }


    # ========================================================
    # VALIDAR CONTENIDO VEGETAL
    # ========================================================

    def _validate_probable_vegetation(
        self,
        image: Image.Image,
    ) -> dict[str, Any]:

        metrics = (
            self._calculate_vegetation_metrics(
                image
            )
        )


        global_fraction = float(
            metrics[
                "vegetation_fraction"
            ]
        )

        center_fraction = float(
            metrics[
                "center_vegetation_fraction"
            ]
        )

        core_fraction = float(
            metrics[
                "core_vegetation_fraction"
            ]
        )

        upper_fraction = float(
            metrics[
                "upper_vegetation_fraction"
            ]
        )

        lower_fraction = float(
            metrics[
                "lower_vegetation_fraction"
            ]
        )

        excess_green_p90 = float(
            metrics[
                "excess_green_p90"
            ]
        )


        # ----------------------------------------------------
        # CANTIDAD DE VEGETACIÓN
        # ----------------------------------------------------

        enough_global = (
            global_fraction
            >= MIN_VEGETATION_FRACTION
        )

        enough_center = (
            center_fraction
            >= MIN_CENTER_VEGETATION_FRACTION
        )


        # ----------------------------------------------------
        # VEGETACIÓN EN EL NÚCLEO
        # ----------------------------------------------------

        enough_core = (
            core_fraction
            >= MIN_CORE_VEGETATION_FRACTION
        )


        # ----------------------------------------------------
        # DISTRIBUCIÓN VERTICAL
        # ----------------------------------------------------

        enough_vertical_spread = (
            upper_fraction
            >= MIN_VERTICAL_SPREAD_FRACTION
            and
            lower_fraction
            >= MIN_VERTICAL_SPREAD_FRACTION
        )


        # ----------------------------------------------------
        # SEÑAL VERDE
        # ----------------------------------------------------

        enough_green_signal = (
            excess_green_p90
            >= MIN_EXCESS_GREEN_PERCENTILE
        )


        # ----------------------------------------------------
        # DISTRIBUCIÓN VÁLIDA
        # ----------------------------------------------------

        valid_spatial_distribution = (
            enough_core
            or
            enough_vertical_spread
        )


        # ----------------------------------------------------
        # DECISIÓN
        # ----------------------------------------------------

        if not (
            enough_green_signal
            and
            (
                enough_global
                or
                enough_center
            )
            and
            valid_spatial_distribution
        ):

            raise InvalidImageError(
                "La fotografía no parece corresponder "
                "a una hoja o material vegetal válido. "
                "Tome una fotografía donde la hoja de papa "
                "sea claramente visible, esté centrada "
                "y ocupe una parte importante de la imagen.",
                codigo="no_vegetal",
            )


        return {

            "contenido_vegetal_probable":
                True,

            "vegetacion_pct":
                round(
                    global_fraction
                    * 100,
                    2,
                ),

            "vegetacion_central_pct":
                round(
                    center_fraction
                    * 100,
                    2,
                ),

            "vegetacion_nucleo_pct":
                round(
                    core_fraction
                    * 100,
                    2,
                ),

            "vegetacion_superior_pct":
                round(
                    upper_fraction
                    * 100,
                    2,
                ),

            "vegetacion_inferior_pct":
                round(
                    lower_fraction
                    * 100,
                    2,
                ),

            "exceso_verde_p90":
                round(
                    excess_green_p90,
                    2,
                ),
        }


    # ========================================================
    # VALIDAR NITIDEZ
    # ========================================================

    def _validate_sharpness(
        self,
        quality_metrics: dict[
            str,
            Any,
        ],
    ) -> dict[str, Any]:

        sharpness = float(
            quality_metrics[
                "sharpness"
            ]
        )

        if (
            sharpness
            < MIN_SHARPNESS_SCORE
        ):

            raise InvalidImageError(
                "La fotografía está demasiado borrosa "
                "o desenfocada para realizar un "
                "diagnóstico confiable. "
                "Tome nuevamente la fotografía "
                "manteniendo la cámara estable y "
                "enfocando claramente la hoja.",
                codigo="borrosa",
            )


        return {

            "nitidez_valida":
                True,

            "nitidez":
                round(
                    sharpness,
                    3,
                ),

            "umbral":
                MIN_SHARPNESS_SCORE,
        }


    # ========================================================
    # CONSTRUIR TOP 3
    # ========================================================

    @staticmethod
    def _build_top3(
        probabilities: np.ndarray,
        class_names: list[str],
        class_mapping: dict[
            str,
            dict[str, Any],
        ],
    ) -> list[dict[str, Any]]:

        sorted_indices = (
            np.argsort(
                probabilities
            )[::-1]
        )

        top_n = min(
            3,
            len(
                sorted_indices
            ),
        )

        top3: list[
            dict[str, Any]
        ] = []


        for idx in (
            sorted_indices[
                :top_n
            ]
        ):

            idx_int = int(
                idx
            )

            class_name = (
                class_names[
                    idx_int
                ]
            )

            mapping = (
                class_mapping.get(
                    class_name,
                    {},
                )
            )

            confidence = (
                float(
                    probabilities[
                        idx_int
                    ]
                )
                * 100
            )


            top3.append(
                {

                    "clase":
                        class_name,

                    "nombre":
                        mapping.get(
                            "label",
                            class_name,
                        ),

                    "codigo_enfermedad":
                        mapping.get(
                            "code"
                        ),

                    "confianza":
                        round(
                            confidence,
                            2,
                        ),
                }
            )


        return top3


    # ========================================================
    # VALIDAR CONFIANZA CNN
    # ========================================================

    def _validate_prediction(
        self,
        top3: list[
            dict[str, Any]
        ],
    ) -> dict[str, Any]:

        if not top3:

            raise InvalidImageError(
                "El modelo no pudo generar "
                "una predicción válida.",
                codigo="prediccion_invalida",
            )


        first_confidence = float(
            top3[0][
                "confianza"
            ]
        )


        second_confidence = (
            float(
                top3[1][
                    "confianza"
                ]
            )
            if len(
                top3
            ) > 1
            else 0.0
        )


        margin = (
            first_confidence
            - second_confidence
        )


        # ----------------------------------------------------
        # CONFIANZA MÍNIMA
        # ----------------------------------------------------

        if (
            first_confidence
            < MIN_MODEL_CONFIDENCE
        ):

            raise InvalidImageError(
                "La CNN no tiene suficiente certeza "
                "para emitir un diagnóstico confiable. "
                "Tome otra fotografía de la hoja "
                "con buena iluminación y enfoque.",
                codigo="baja_certeza",
            )


        # ----------------------------------------------------
        # DIFERENCIA ENTRE PRIMERA Y SEGUNDA CLASE
        # ----------------------------------------------------

        if (
            margin
            < MIN_PREDICTION_MARGIN
        ):

            raise InvalidImageError(
                "La imagen genera resultados demasiado "
                "similares entre varias clases. "
                "Tome otra fotografía más clara "
                "de la hoja.",
                codigo="clases_similares",
            )


        return {

            "prediccion_confiable":
                True,

            "confianza_principal":
                round(
                    first_confidence,
                    2,
                ),

            "segunda_confianza":
                round(
                    second_confidence,
                    2,
                ),

            "margen":
                round(
                    margin,
                    2,
                ),
        }


    # ========================================================
    # PREDICCIÓN PRINCIPAL
    # ========================================================

    def predict_bytes(
        self,
        raw: bytes,
    ) -> dict[str, Any]:

        # ----------------------------------------------------
        # 1. CARGAR MODELO
        # ----------------------------------------------------

        self._load_if_needed()

        assert (
            self._model
            is not None
        )

        assert (
            self._metadata
            is not None
        )


        # ----------------------------------------------------
        # 2. DECODIFICAR
        # ----------------------------------------------------

        image = (
            self._decode_image(
                raw
            )
        )


        # ----------------------------------------------------
        # 3. CALIDAD BÁSICA
        #
        # Resolución
        # iluminación
        # contraste
        # ----------------------------------------------------

        quality_metrics = (
            self._validate_basic_quality(
                image
            )
        )


        # ----------------------------------------------------
        # 4. CONTENIDO VEGETAL
        #
        # Se valida antes del desenfoque.
        # ----------------------------------------------------

        content_validation = (
            self._validate_probable_vegetation(
                image
            )
        )


        # ----------------------------------------------------
        # 5. NITIDEZ
        # ----------------------------------------------------

        sharpness_validation = (
            self._validate_sharpness(
                quality_metrics
            )
        )


        # ----------------------------------------------------
        # 6. PREPARAR IMAGEN PARA CNN
        # ----------------------------------------------------

        image_size = (
            self._metadata.get(
                "image_size",
                [224, 224],
            )
        )

        width = int(
            image_size[
                0
            ]
        )

        height = int(
            image_size[
                1
            ]
        )

        model_image = (
            image.resize(
                (
                    width,
                    height,
                ),
                Image.Resampling.BILINEAR,
            )
        )

        array = np.asarray(
            model_image,
            dtype=np.float32,
        )

        array = np.expand_dims(
            array,
            axis=0,
        )


        # ----------------------------------------------------
        # 7. CNN
        # ----------------------------------------------------

        probabilities = (
            self._model.predict(
                array,
                verbose=0,
            )[0]
        )


        class_names: list[str] = list(
            self._metadata[
                "class_names"
            ]
        )

        class_mapping: dict[
            str,
            dict[str, Any],
        ] = dict(
            self._metadata[
                "class_mapping"
            ]
        )


        if (
            len(
                probabilities
            )
            != len(
                class_names
            )
        ):

            raise RuntimeError(
                "El número de salidas del modelo "
                "no coincide con sus metadatos."
            )


        # ----------------------------------------------------
        # 8. TOP 3
        # ----------------------------------------------------

        top3 = (
            self._build_top3(
                probabilities,
                class_names,
                class_mapping,
            )
        )


        # ----------------------------------------------------
        # 9. CONFIANZA
        # ----------------------------------------------------

        prediction_validation = (
            self._validate_prediction(
                top3
            )
        )


        # ----------------------------------------------------
        # 10. MEJOR CLASE
        # ----------------------------------------------------

        best = top3[
            0
        ]

        # Los modelos con clase de rechazo ("other") identifican fotos que
        # no son hojas de papa (otro cultivo, fondo, etc.): no se diagnostica.
        if best["clase"] == "other":
            raise InvalidImageError(
                "La fotografía no parece corresponder a una hoja de papa. "
                "Tome una foto cercana de una sola hoja de papa.",
                codigo="no_es_papa",
            )

        if not best.get(
            "codigo_enfermedad"
        ):

            raise RuntimeError(
                f"La clase "
                f"'{best['clase']}' "
                "no tiene codigo_enfermedad "
                "configurado."
            )


        # ----------------------------------------------------
        # 10b. EXPLICABILIDAD (Grad-CAM)
        #
        # Es opcional: si falla, el diagnóstico sigue sin mapa.
        # ----------------------------------------------------

        mapa_calor = None

        mapa_calor_error = None

        if GRADCAM_ENABLED:

            try:

                mapa_calor = generar_gradcam(
                    self._obtener_gradcam(),
                    image,
                    array,
                    class_names.index(
                        best["clase"]
                    ),
                    best["nombre"],
                )

            except Exception as exc:  # noqa: BLE001

                mapa_calor_error = (
                    f"{type(exc).__name__}: {exc}"
                )[:240]

        # ----------------------------------------------------
        # 11. RESPUESTA
        # ----------------------------------------------------

        return {

            "clase":
                best[
                    "clase"
                ],

            "nombre":
                best[
                    "nombre"
                ],

            "codigo_enfermedad":
                best[
                    "codigo_enfermedad"
                ],

            "confianza":
                best[
                    "confianza"
                ],

            "modelo_version":
                self._metadata[
                    "model_version"
                ],

            "modelo": {
                "version":
                    self._metadata[
                        "model_version"
                    ],
                "arquitectura":
                    self._metadata.get(
                        "architecture"
                    ),
                "calibrado":
                    bool(
                        self._metadata.get(
                            "calibration"
                        )
                    ),
                "temperatura":
                    (
                        self._metadata.get(
                            "calibration"
                        ) or {}
                    ).get(
                        "temperatura"
                    ),
                "confianza_minima":
                    MIN_MODEL_CONFIDENCE,
                "margen_minimo":
                    MIN_PREDICTION_MARGIN,
            },

            "top3":
                top3,

            "mapa_calor":
                mapa_calor,

            "mapa_calor_error":
                mapa_calor_error,


            # ------------------------------------------------
            # CALIDAD
            # ------------------------------------------------

            "calidad_imagen": {

                "valida":
                    True,

                "resolucion":
                    (
                        f"{quality_metrics['width']}"
                        f"x"
                        f"{quality_metrics['height']}"
                    ),

                "brillo":
                    quality_metrics[
                        "brightness"
                    ],

                "contraste":
                    quality_metrics[
                        "contrast"
                    ],

                "nitidez":
                    quality_metrics[
                        "sharpness"
                    ],

                "region_nitidez":
                    "70% central",
            },


            # ------------------------------------------------
            # CONTENIDO
            # ------------------------------------------------

            "validacion_contenido":
                content_validation,


            # ------------------------------------------------
            # NITIDEZ
            # ------------------------------------------------

            "validacion_nitidez":
                sharpness_validation,


            # ------------------------------------------------
            # PREDICCIÓN
            # ------------------------------------------------

            "validacion_prediccion":
                prediction_validation,
        }