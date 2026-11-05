# Implementación del aporte ingenieril CNN

La carpeta `cnn_service/` implementa el aporte ingenieril del proyecto siguiendo CRISP-DM.

## 1. Comprensión del problema

Objetivo técnico: recibir una fotografía de una hoja de papa, clasificarla con una CNN basada en ResNet50 y devolver la clase predicha, porcentaje de confianza, top 3 de probabilidades y versión del modelo. El diagnóstico queda pendiente de validación por un técnico/agronómo.

## 2. Adquisición/comprensión de datos

Para la primera prueba funcional se utilizarán tres clases compatibles con PlantVillage:

- `healthy` -> `CNN-SANA`
- `early_blight` -> `CNN-TTP`
- `late_blight` -> `CNN-TT`

El archivo `cnn_service/class_mapping.json` también deja preparados:

- `bacterial_wilt` -> `CNN-MB`
- `rhizoctonia` -> `CNN-RZ`

Estas dos clases solamente deben incorporarse cuando existan imágenes correctamente etiquetadas para ellas. No se deben sustituir por enfermedades diferentes solo por tener síntomas parecidos.

## 3. Preparación de datos

Después de descargar PlantVillage:

```powershell
cd cnn_service
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python training/prepare_plantvillage.py --source "C:\RUTA\PlantVillage"
python training/split_dataset.py
```

El script divide automáticamente el conjunto en 70% entrenamiento, 15% validación y 15% prueba.

## 4. Modelado

```powershell
python training/train.py --epochs 20
```

Se usa ResNet50 preentrenada con ImageNet, Transfer Learning, resolución 224x224, Data Augmentation, Adam y Softmax.

## 5. Evaluación

```powershell
python training/evaluate.py
```

Se generan:

- `artifacts/metrics.json`
- `artifacts/confusion_matrix.json`
- `artifacts/confusion_matrix.png`
- gráficas de accuracy y loss

## 6. Despliegue

Después del entrenamiento:

```powershell
uvicorn app.main:app --host 0.0.0.0 --port 8001 --reload
```

El backend Laravel usa `CNN_SERVICE_URL` y envía la imagen al endpoint `/predict`.

La interfaz Vue ya no solicita manualmente enfermedad ni porcentaje de confianza. Esos valores ahora provienen del modelo CNN. La severidad queda para la validación del técnico/agronómo.

## Flujo final

```text
Vue -> Laravel -> FastAPI -> ResNet50 -> FastAPI -> Laravel -> PostgreSQL -> Vue
```

Si todavía no existe el modelo entrenado, `GET /health` devuelve `waiting_model` y Laravel mostrará un error controlado al intentar analizar una imagen.

## Actualización metodológica del dataset

Para mantener la división 70% entrenamiento, 15% validación y 15% prueba sin fuga de datos, PlantVillage se divide por `leaf_id` (hoja física) y no por imagen individual. Se utiliza `cnn_service/training/download_and_split_plantvillage.py`, que descarga las clases de papa, conserva juntas todas las imágenes de una misma hoja y genera `dataset_stats.json` y `dataset_manifest.csv` como evidencia reproducible de la fase de preparación de datos de CRISP-DM.

Debido al desbalance del conjunto de papa de PlantVillage, el entrenamiento calcula pesos de clase a partir de las imágenes del conjunto de entrenamiento. El Data Augmentation se aplica exclusivamente al conjunto de entrenamiento.
