"""
Grad-CAM (Gradient-weighted Class Activation Mapping) para el modelo de hojas de papa.

Idea: se toman los mapas de activación de la ÚLTIMA capa convolucional del backbone
(para ResNet50, ``conv5_block3_out``, de 7x7x2048) y se pesan por el gradiente de la
puntuación de la clase predicha. Las zonas con peso alto son las que más empujaron al modelo
hacia ese diagnóstico. El resultado es una ayuda visual: no mide el daño real de la hoja.

Detalles de implementación:
  * El modelo guardado es: imagen -> data_augmentation -> resnet50 -> GAP -> Dense ->
    Dropout -> logits -> temperatura (x 1/T) -> softmax. El backbone es un submodelo
    anidado: su salida se toma del grafo exterior (``backbone.output``).
  * El gradiente se calcula sobre los LOGITS de la clase predicha, no sobre la probabilidad:
    con un softmax saturado (p ~ 1) el gradiente se anula y el mapa sale vacío. La capa de
    temperatura solo multiplica por una constante (1/T), así que, una vez normalizado, el
    mapa es idéntico al que se obtendría a través de ella.
  * Todo es opcional: si falla, el diagnóstico sigue funcionando sin mapa.
"""

from __future__ import annotations

import base64
import io
import time
from typing import Any

import numpy as np
from PIL import Image


# Tamaño máximo (lado mayor) de la imagen con el mapa superpuesto.
MAX_OVERLAY_SIDE = 512

# Opacidad máxima de la capa de calor (las zonas poco importantes casi no se tiñen).
MAX_ALPHA = 0.65


class GradCamError(RuntimeError):
    """No se pudo preparar o calcular Grad-CAM con este modelo."""


def _jet(valores: np.ndarray) -> np.ndarray:
    """Mapa de colores 'jet' (azul -> verde -> amarillo -> rojo). Entrada 0..1, salida RGB 0..1."""

    r = np.clip(1.5 - np.abs(4.0 * valores - 3.0), 0.0, 1.0)
    g = np.clip(1.5 - np.abs(4.0 * valores - 2.0), 0.0, 1.0)
    b = np.clip(1.5 - np.abs(4.0 * valores - 1.0), 0.0, 1.0)

    return np.stack([r, g, b], axis=-1)


class GradCAM:

    def __init__(self, modelo: Any) -> None:

        import tensorflow as tf

        self._tf = tf

        backbone = next(
            (
                capa
                for capa in modelo.layers
                if isinstance(capa, tf.keras.Model)
                and capa.name != "data_augmentation"
            ),
            None,
        )

        if backbone is None:
            raise GradCamError("El modelo no tiene un submodelo convolucional (backbone).")

        try:
            logits = modelo.get_layer("logits").output

            # La salida del backbone EN EL GRAFO EXTERIOR (``backbone.output`` pertenece al grafo
            # interno del submodelo y no está conectado a la entrada del modelo completo).
            mapas = backbone._inbound_nodes[0].output_tensors[0]
        except Exception as exc:  # noqa: BLE001
            raise GradCamError(f"No se pudo acceder a las capas del modelo: {exc}") from exc

        self.capa = backbone.layers[-1].name

        self._modelo = tf.keras.Model(
            inputs=modelo.input,
            outputs=[mapas, logits],
        )

        # Se compila una sola vez: en modo "eager" cada predicción tardaba ~1 s.
        self._calcular = tf.function(
            self._calcular_cam,
            reduce_retracing=True,
        )

        # Calentamiento: el primer trazado del grafo se hace aquí y no en la primera foto real.
        self.mapa(
            np.zeros((1,) + tuple(modelo.input_shape[1:]), dtype=np.float32) + 127.0,
            0,
            _calentamiento=True,
        )

    def _calcular_cam(self, entrada, indice):
        """Grad-CAM de una imagen (se ejecuta como grafo compilado)."""

        tf = self._tf

        with tf.GradientTape() as cinta:
            mapas, logits = self._modelo(entrada, training=False)
            puntuacion = tf.gather(logits[0], indice)

        gradientes = cinta.gradient(puntuacion, mapas)

        # Peso de cada canal = promedio espacial de su gradiente.
        pesos = tf.reduce_mean(gradientes, axis=(1, 2), keepdims=True)
        cam = tf.reduce_sum(pesos * mapas, axis=-1)[0]

        return tf.nn.relu(cam)

    def mapa(
        self,
        lote: np.ndarray,
        indice_clase: int,
        _calentamiento: bool = False,
    ) -> np.ndarray:
        """Devuelve el mapa de importancia (h x w, valores 0..1) para la clase indicada."""

        tf = self._tf

        cam = self._calcular(
            tf.convert_to_tensor(lote, dtype=tf.float32),
            tf.constant(indice_clase, dtype=tf.int32),
        ).numpy()

        if _calentamiento:
            return cam

        maximo = float(cam.max())

        if not np.isfinite(maximo) or maximo <= 1e-8:
            raise GradCamError("El mapa de calor salió vacío para esta imagen.")

        return cam / maximo


def superponer(
    imagen: Image.Image,
    mapa: np.ndarray,
) -> Image.Image:
    """Superpone el mapa de calor (coloreado) sobre la fotografía original."""

    base = imagen.convert("RGB")
    base.thumbnail((MAX_OVERLAY_SIDE, MAX_OVERLAY_SIDE), Image.Resampling.LANCZOS)

    grande = Image.fromarray((mapa * 255).astype(np.uint8)).resize(
        base.size,
        Image.Resampling.BICUBIC,
    )
    intensidad = np.asarray(grande, dtype=np.float32) / 255.0

    color = _jet(intensidad)
    alfa = (np.clip(intensidad, 0.0, 1.0) * MAX_ALPHA)[..., None]

    fondo = np.asarray(base, dtype=np.float32) / 255.0
    mezcla = fondo * (1.0 - alfa) + color * alfa

    return Image.fromarray((np.clip(mezcla, 0.0, 1.0) * 255).astype(np.uint8))


def generar(
    calculador: GradCAM,
    imagen: Image.Image,
    lote: np.ndarray,
    indice_clase: int,
    nombre_clase: str,
) -> dict[str, Any]:
    """Calcula el mapa y lo devuelve como PNG en base64, con su información."""

    inicio = time.perf_counter()

    cam = calculador.mapa(lote, indice_clase)
    resultado = superponer(imagen, cam)

    buffer = io.BytesIO()
    resultado.save(buffer, format="PNG", optimize=True)

    return {
        "metodo": "Grad-CAM",
        "capa": calculador.capa,
        "clase_explicada": nombre_clase,
        "ancho": resultado.width,
        "alto": resultado.height,
        "tiempo_ms": round((time.perf_counter() - inicio) * 1000, 1),
        "imagen_png_base64": base64.b64encode(buffer.getvalue()).decode("ascii"),
    }
