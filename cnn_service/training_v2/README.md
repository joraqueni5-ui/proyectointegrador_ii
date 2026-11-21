# Prototipo CNN v2 — hojas de papa en condiciones de campo

Prototipo para mejorar el diagnóstico de la CNN actual (v1), que se entrenó solo con
fotos de laboratorio (PlantVillage: una hoja sobre fondo gris). **No reemplaza a v1**:
v2 vive en carpetas aparte (`dataset_v2/`, `model_v2/`, `artifacts_v2/`) y el servicio
sigue usando v1 hasta que se active v2 de forma explícita (ver "Activar v2").

> **Estado:** prototipo entrenado **sin fotos de Bolivia**. Antes de usarlo en
> producción hay que validarlo con fotos reales de parcelas del altiplano.

## Qué cambia respecto a v1

| | v1 (`training/`) | v2 (`training_v2/`) |
|---|---|---|
| Datos | PlantVillage (laboratorio) | Fotos de campo (Tanzania) + hojas de otros cultivos |
| Clases | sana, tizón temprano, tizón tardío | las mismas + **`other`** ("no es una hoja de papa") |
| Aumento de datos | volteo horizontal, ±29°, zoom, contraste | + volteo vertical, ±90°, traslación, brillo |
| Entrenamiento | solo la cabeza (base congelada) | cabeza + **fine-tuning del bloque conv5** |
| Pérdida | entropía cruzada | + label smoothing 0,05 |
| Confianza | softmax sin calibrar | **calibrada por temperatura** (incluida en el modelo) |
| Evaluación | solo el 15 % de PlantVillage | conjuntos **externos** (Perú, PlantDoc) que v2 nunca ve |

## Datos y licencias

Todos los conjuntos tienen licencia abierta **CC BY 4.0** (hay que citar la fuente):

| Uso | Fuente | Detalle |
|---|---|---|
| Entrenamiento / validación / prueba | **Irish Potato Imagery Dataset** (Tanzania) — Zenodo [10.5281/zenodo.8286529](https://zenodo.org/records/8286529), artículo en [Data in Brief 2025](https://pmc.ncbi.nlm.nih.gov/articles/PMC12020891/) | Fotos de hojas en fincas, celular Samsung A03 Core. Se usa una **muestra** de ~2.000 fotos por clase |
| Clase `other` | **PlantDoc** — [GitHub](https://github.com/pratikkayal/PlantDoc-Dataset), [arXiv:1911.10317](https://arxiv.org/abs/1911.10317) | Hojas reales de otros cultivos (maíz, tomate, manzana…) |
| Prueba externa 1 | **Cajamarca, Perú** — Mendeley Data [kh8xjh2zyh](https://data.mendeley.com/datasets/kh8xjh2zyh/1) | 484 fotos en campo andino (216 sanas, 268 tizón tardío). **No se usa para entrenar** |
| Prueba externa 2 | **PlantDoc, clases de papa** | ~218 fotos reales de tizón temprano/tardío. **No se usa para entrenar** |

El zip de Tanzania pesa 7,2 GB; `common.RemoteZip` lee solo las fotos de la muestra con
peticiones HTTP *Range*, sin descargar el zip completo.

## Decisiones de diseño (y sus límites)

* **Bloques en lugar de fotos sueltas.** Tanzania no trae identificador de hoja o planta.
  Se agrupan fotos consecutivas (bloques de 40) y cada bloque va completo a un solo
  conjunto (70/15/15). Es una aproximación: reduce, pero no elimina, la fuga de datos.
* **Deduplicación.** Tanzania repite fotos idénticas con distinto número de archivo
  (en la muestra, ~1.100 de ~6.000, sobre todo tizón temprano). `dedupe_dataset.py` las
  elimina; si no, una misma foto caería en entrenamiento y en prueba.
* **Los números de Tanzania miden poco.** El conjunto de prueba de Tanzania es de la misma
  fuente que el entrenamiento; da una cifra optimista. La medida honesta de generalización
  son los conjuntos **externos** (Perú, PlantDoc).
* **PlantDoc es de internet:** sus etiquetas pueden tener ruido.
* **Las fotos se guardan reducidas** (lado máximo 640 px); las de Tanzania ya son de 640×480.

## Reproducir

Desde `cnn_service/` (requiere TensorFlow 2.18, ver `requirements.txt`):

```powershell
# 1. Datos de Cajamarca (24 MB) - guardar como dataset_v2/_raw/peru.zip
#    https://data.mendeley.com/datasets/kh8xjh2zyh/1  (botón "Download All")
# 2. Descargar muestras de Tanzania y PlantDoc, y armar train/val/test + externos
python training_v2/fetch_datasets.py --workers 4
# 3. Quitar fotos duplicadas
python training_v2/dedupe_dataset.py
# 4a. Versión "a" (solo Tanzania + PlantDoc other): sirve de ejemplo del sesgo de fuente
python training_v2/train_v2.py --epochs-head 5 --epochs-finetune 3
python training_v2/evaluate_v2.py --con-filtros
# 4b. Versión "b" (recomendada): papa de varias fuentes
python training_v2/build_v2b.py
python training_v2/train_v2.py --data dataset_v2b --out model_v2b --artifacts artifacts_v2b --zoom 0.35 --epochs-head 4 --epochs-finetune 3 --version PROINPA-ResNet50-v2.1-prototipo
python training_v2/evaluate_v2.py --data-dir dataset_v2b --v2-model model_v2b/potato_resnet50_v2.keras --v2-meta model_v2b/model_metadata.json --out artifacts_v2b --con-filtros
# (en CPU cada entrenamiento tarda ~40-100 min; en una GPU, minutos)
```

Resultados en `artifacts_v2/`: `evaluation_v1_vs_v2.md` (tablas), `.json`,
`confusion_matrices_v1_vs_v2.png`, `training_curves_v2.png`.

> Con `--workers` altos, Zenodo responde HTTP 429 (límite de peticiones). Usa 4 o menos;
> el script espera y reintenta, y es reanudable.

## Resultados (v1 actual vs prototipo v2b)

Detalle completo en `artifacts_v2b/evaluation_v1_vs_v2.md` y las matrices de confusión
en `artifacts_v2b/confusion_matrices_v1_vs_v2.png`. Accuracy solo del modelo (sin filtros):

| Conjunto | n | v1 | v2b | Cómo leerlo |
|---|---|---|---|---|
| Tanzania, prueba | 623 | 58,1 % | 97,6 % | Misma fuente que el entrenamiento: **optimista** |
| Perú (Cajamarca), reservada | 160 | 47,5 % | 98,1 % | Parte de Perú se usó para entrenar (bloques de 12 fotos): **probable fuga leve**, no es independiente |
| PlantDoc papa, reservada | 75 | 56,0 % | 45,3 % | Fotos web de otra fuente: **la medida más honesta** y v2b **no mejora** |
| Otros cultivos (no papa) | 213 | 0 % rechazadas | 91,5 % rechazadas | v1 diagnostica papa en el 91,5 % de estas hojas; v2b en el 4,7 % |

Conclusiones:

* v2b **mejora con claridad** en fotos de campo parecidas a las que vio (Tanzania, Perú)
  y **casi elimina los falsos diagnósticos** en hojas de otros cultivos.
* **No se demostró** que generalice a fuentes nuevas: en PlantDoc papa (n = 75, etiquetas con ruido) no supera a v1.
* La confianza de v2b está mejor calibrada (ECE 0,01–0,02 frente a 0,27–0,35 de v1 en los conjuntos de campo).

### Lo que falló primero (v2 "a") y por qué importa

La primera versión entrenó la papa solo con Tanzania y "other" solo con PlantDoc. Resultado:
97,6 % en Tanzania pero **1,7 % en Perú y 4,6 % en PlantDoc**: la red aprendió "se ve como
la cámara de Tanzania = papa" y rechazaba el ~90 % de las demás fotos de papa (sesgo de
fuente). Se corrigió en v2b mezclando papa de tres fuentes. Cualquier clase nueva debe
tener ejemplos de **todas** las fuentes, o la red aprende la fuente en lugar del contenido.

## Limitaciones conocidas

* **Sin fotos de Bolivia.** No hay validación en el altiplano; es el paso pendiente.
* Los filtros de calidad ya están recalibrados (ver "Filtros de calidad" abajo).
* **v2b rechaza como "no es papa" varias fotos *sanas* de papa de internet** (en una prueba
  con 25 imágenes propias: 5 de 8 sanas/campo). No tuvo ejemplos de hojas sanas de papa de
  esa fuente. v1 las acepta, pero se equivoca con más frecuencia al diagnosticar.
* v2b **rechaza como "no es papa" una ramita de papa sobre fondo blanco** (foto de stock):
  no hubo ejemplos así en el entrenamiento.
* Entre las hojas de otros cultivos, ~5 % aún recibe un diagnóstico de papa.
* PlantDoc papa es pequeño (75 fotos reservadas): un acierto más o menos mueve la cifra ~1,3 puntos.

## Filtros de calidad (nitidez y material vegetal)

Los filtros de `app/inference.py` se recalibraron con `calibrar_filtros.py` (mide las
mismas funciones que usa el servicio). Cambios:

| Filtro | Antes | Ahora | Por qué |
|---|---|---|---|
| Nitidez | varianza del Laplaciano ≥ 25 (absoluta) | **re-desenfoque relativo ≥ 0,40** a escala fija 640 px | La varianza absoluta depende de la textura y la resolución: rechazaba el 88–97 % de las fotos de campo y fotos nítidas de internet |
| Material vegetal | ≥ 2,5 % de píxeles verdes, exceso de verde ≥ 12 | ≥ 8 %, ≥ 20 | Selfis pasaban por 1 punto de margen; la papa real tiene ≥ 13 % |
| Resolución mínima | 224 px | 160 px | Fotos de internet de 275×183 se rechazaban |
| Sobre/subexposición | brillo y fracción de píxeles | + solo si el contraste es bajo (< 35) | Fotos de estudio con fondo blanco/negro se rechazaban |

Resultados (`python training_v2/calibrar_filtros.py --carpeta <fotos>`):

* Fotos reales de papa aceptadas por los filtros: Tanzania 100 %, Perú 99,3 %, PlantDoc 100 %.
* Desenfoque artificial: σ 1,0 → 9 % marcadas; σ 1,5 → 75 %; σ ≥ 2,5 → 100 %.
* 25 imágenes propias (papa de internet, selfis, siluetas): las 20 de plantas pasan, las 5 de
  personas se rechazan con el mensaje de "no es material vegetal".
* Limitación: la nitidez se mide a 640 px; un desenfoque muy leve en fotos de 12 MP
  puede pasar. Las variables `CNN_MIN_SHARPNESS_SCORE` (ahora 0–1) y las demás
  `CNN_*` ajustan los umbrales sin tocar el código.

## Activar v2 en el servicio

El servicio (`app/inference.py`) lee el modelo de variables de entorno, así que no hay que
reemplazar archivos. Desde `cnn_service/`:

```powershell
$env:CNN_MODEL_PATH    = "model_v2b/potato_resnet50_v2.keras"
$env:CNN_METADATA_PATH = "model_v2b/model_metadata.json"
uvicorn app.main:app --port 8001
```

Para volver a v1 basta quitar esas variables. Si la clase ganadora es `other`, el
servicio responde "la fotografía no parece corresponder a una hoja de papa".

## Ciclo de retroalimentación: reentrenar con diagnósticos validados

Cada diagnóstico que un agrónomo **confirma** o **corrige** es una foto etiquetada por un experto,
tomada en las condiciones reales de PROINPA. El sistema permite exportarlas para reentrenar la CNN
(es justo lo que faltaba: fotos de campo de Bolivia).

**1. Exportar desde el sistema.** En *Diagnóstico IA → Diagnósticos*, botón **Exportar dataset
validado** (solo Administrador, Agrónomo y Superadmin). Descarga un ZIP con:

| Contenido | Descripción |
|---|---|
| `early_blight/`, `healthy/`, `late_blight/` | Fotos Confirmadas o Corregidas, en la carpeta de la enfermedad **final** (la validada), con los mismos nombres de clase que usa `train_v2.py` |
| `metadata.csv` | `id_diagnostico, archivo, clase_predicha, clase_final, estado_validacion, confianza, version_modelo, fecha, comunidad, parcela` |
| `LEEME.txt` | Conteos y qué no se pudo incluir |

No se incluyen los diagnósticos Rechazados ni Pendientes, los que terminaron en una enfermedad que
la CNN no clasifica (por ejemplo Rizoctoniasis) ni los que ya no tienen su foto en el servidor.
La clase `other` ("no es papa") no sale del sistema: viene del dataset base.

**2. Integrarlo con el dataset de entrenamiento.** Desde `cnn_service/`:

```powershell
python training_v2/integrar_dataset_validado.py --zip dataset_validado_AAAAMMDD.zip --base dataset_v2c --out dataset_v3
```

El script reparte las fotos en `train/val/test` **agrupando por parcela** (fotos de una misma parcela
se parecen mucho; si quedaran en conjuntos distintos, la prueba saldría inflada), omite fotos
repetidas y conserva el dataset base. Con menos de 3 parcelas no puede reservar validación ni
prueba y lo avisa: en ese caso usa el ZIP solo como refuerzo del entrenamiento.

**3. Entrenar y comparar.**

```powershell
python training_v2/train_v2.py --data dataset_v3 --out model_v3 --artifacts artifacts_v3 --zoom 0.35 --epochs-head 4 --epochs-finetune 3 --version PROINPA-v3
python training_v2/evaluate_v2c.py --out artifacts_v3 --modelo "actual=model_v2b/potato_resnet50_v2.keras,model_v2b/model_metadata.json" --modelo "v3=model_v3/potato_resnet50_v2.keras,model_v3/model_metadata.json"
```

Para activar el modelo nuevo basta con `CNN_MODEL_PATH` y `CNN_METADATA_PATH` (ver "Activar v2 en el
servicio"). **Antes de activarlo**, compara con la prueba reservada (parcelas que el modelo no vio) y
con las vistas *Desempeño del modelo*; reentrenar con pocas fotos puede empeorar el modelo.

> Recomendación: reunir al menos unas 100 fotos validadas por clase y de varias parcelas antes de
> reentrenar. Con menos, el resultado es poco confiable.

## Archivos

| Archivo | Para qué |
|---|---|
| `common.py` | rutas, clases y lector de zip remoto |
| `fetch_datasets.py` | descarga y arma el dataset |
| `dedupe_dataset.py` | elimina fotos idénticas |
| `train_v2.py` | entrenamiento en dos etapas + calibración |
| `build_v2b.py` | dataset v2b (papa de varias fuentes, repetición de fotos de campo) |
| `evaluate_v2.py` | comparación v1 vs v2 |
| `integrar_dataset_validado.py` | integra el ZIP del dataset validado (exportado del sistema) con un dataset de entrenamiento, dividiendo por parcela |
