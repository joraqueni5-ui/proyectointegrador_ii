# CNN Service - PROINPA

Microservicio Python/FastAPI para el aporte ingenieril del proyecto: clasificación de enfermedades foliares de papa mediante una CNN basada en **ResNet50 + Transfer Learning**.

## Relación con CRISP-DM

1. **Comprensión del problema:** apoyar el diagnóstico preliminar de enfermedades foliares de papa.
2. **Comprensión de los datos:** PlantVillage como dataset inicial y revisión de clases/cantidad/calidad.
3. **Preparación de datos:** `training/download_and_split_plantvillage.py`, división 70/15/15 por `leaf_id`, evitando fuga de datos.
4. **Modelado:** `training/train.py`, ResNet50 preentrenada, Data Augmentation y pesos de clase.
5. **Evaluación:** `training/evaluate.py`, Accuracy, Precision, Recall, F1-Score y matriz de confusión.
6. **Despliegue:** API FastAPI en `app/main.py`, consumida por Laravel.

## Dataset inicial

PlantVillage ofrece tres clases de papa que utilizaremos para la primera versión funcional:

- `Potato___healthy` -> `healthy` -> `CNN-SANA`
- `Potato___Early_blight` -> `early_blight` -> `CNN-TTP`
- `Potato___Late_blight` -> `late_blight` -> `CNN-TT`

El proyecto conserva en `class_mapping.json` las clases futuras `bacterial_wilt` y `rhizoctonia`, pero **no se entrenan hasta contar con imágenes correctamente etiquetadas**.

> Importante: PlantVillage contiene varias imágenes de una misma hoja física. Por eso la división 70/15/15 se realiza por `leaf_id`, no por archivo individual. De esta manera una hoja nunca aparece al mismo tiempo en entrenamiento y prueba.

## 1. Preparar Python en Windows

Se recomienda Python 3.11.

Desde la carpeta `cnn_service`:

```powershell
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.txt
```

## 2. Descargar y preparar PlantVillage automáticamente

No hace falta descargarlo manualmente. Ejecute:

```powershell
python training/download_and_split_plantvillage.py
```

El script descarga el dataset oficial desde Hugging Face, conserva únicamente las clases de papa y genera:

```text
dataset/processed/
├── train/
│   ├── early_blight/
│   ├── healthy/
│   └── late_blight/
├── val/
│   ├── early_blight/
│   ├── healthy/
│   └── late_blight/
└── test/
    ├── early_blight/
    ├── healthy/
    └── late_blight/
```

También genera evidencia para el informe:

```text
artifacts/dataset_manifest.csv
artifacts/dataset_stats.json
```

`dataset_stats.json` permite demostrar las cantidades reales por clase y por conjunto, mientras `dataset_manifest.csv` conserva la trazabilidad de cada imagen y su `leaf_id`.

## 3. Entrenar ResNet50

```powershell
python training/train.py --epochs 20
```

El entrenamiento aplica Data Augmentation solo a `train` y calcula pesos de clase para compensar el desbalance de PlantVillage.

## 4. Evaluar con el conjunto de prueba

```powershell
python training/evaluate.py
```

Se generan:

```text
model/potato_resnet50.keras
model/model_metadata.json
artifacts/metrics.json
artifacts/confusion_matrix.json
artifacts/confusion_matrix.png
artifacts/training_accuracy.png
artifacts/training_loss.png
```

## 5. Ejecutar la API de IA

```powershell
uvicorn app.main:app --host 0.0.0.0 --port 8001 --reload
```

Verifique:

```text
GET http://localhost:8001/health
```

Cuando el modelo ya existe devolverá `ready`.

La inferencia se realiza con:

```text
POST http://localhost:8001/predict
form-data: file=<imagen>
```

## Nota para la validación final

PlantVillage es adecuado para construir y demostrar la primera versión de la CNN, pero fue capturado principalmente en condiciones controladas. La evaluación final del proyecto debería complementarse con imágenes reales de campo de PROINPA o de un dataset externo de campo, sin mezclarlas con las imágenes usadas para entrenamiento.
