# Sistema PROINPA - Gestión Agrícola Inteligente

## Descripción

Sistema web desarrollado como parte del Proyecto Integrador para apoyar la gestión agrícola de la Fundación PROINPA.

La plataforma permite centralizar y administrar información relacionada con productores, parcelas, cultivos, inspecciones agrícolas, diagnósticos fitosanitarios, inventarios, ventas, alertas y reportes.

Como aporte ingenieril, el sistema incorpora un modelo de Inteligencia Artificial basado en una Red Neuronal Convolucional utilizando la arquitectura **ResNet50**, entrenada mediante **Transfer Learning**, para apoyar la detección de enfermedades foliares en cultivos de papa a partir de fotografías.

Actualmente, el modelo CNN reconoce las siguientes categorías:

- Sana (`healthy`)
- Tizón Temprano (`early_blight`)
- Tizón Tardío (`late_blight`)

El diagnóstico generado por la inteligencia artificial queda pendiente de validación por parte del personal técnico o agronómico, quien puede confirmar, corregir o rechazar el resultado.

---

# Funcionalidades Principales

El sistema permite realizar las siguientes operaciones:

- Gestión de usuarios
- Gestión de roles y permisos
- Autenticación de usuarios
- Verificación OTP mediante correo electrónico
- Gestión de comunidades
- Gestión de productores
- Gestión de parcelas
- Gestión de cultivos
- Gestión de cosechas
- Registro de inspecciones agrícolas
- Carga de fotografías foliares
- Diagnóstico mediante CNN
- Visualización del porcentaje de confianza
- Visualización de las tres principales predicciones
- Validación técnica de diagnósticos
- Gestión de enfermedades
- Gestión de alertas fitosanitarias
- Gestión de inventario
- Gestión de almacenes
- Movimientos de inventario
- Cotizaciones y ventas
- Reportes y estadísticas
- Historial agrícola por parcela

---

# Arquitectura del Sistema

El sistema utiliza una arquitectura en capas y servicios independientes.

```text
                    ┌────────────────────┐
                    │      Usuario       │
                    └─────────┬──────────┘
                              │
                              ▼
                    ┌────────────────────┐
                    │       Vue 3        │
                    │     Frontend       │
                    └─────────┬──────────┘
                              │
                         REST API / HTTP
                              │
                              ▼
                    ┌────────────────────┐
                    │    Laravel 12      │
                    │      Backend       │
                    └───────┬─────┬──────┘
                            │     │
                            │     │ HTTP
                            │     ▼
                            │  ┌─────────────────┐
                            │  │     FastAPI     │
                            │  │ Servicio CNN    │
                            │  └────────┬────────┘
                            │           │
                            │           ▼
                            │  ┌─────────────────┐
                            │  │    ResNet50     │
                            │  │ Modelo CNN      │
                            │  └─────────────────┘
                            │
                            ▼
                    ┌────────────────────┐
                    │   PostgreSQL 16    │
                    │   Base de Datos    │
                    └────────────────────┘
```

La comunicación entre Laravel y el modelo de inteligencia artificial se realiza mediante una API REST desarrollada con FastAPI.

---

# Arquitectura del Aporte Ingenieril

El modelo de Inteligencia Artificial utiliza:

```text
Imagen RGB
224 × 224 × 3
      │
      ▼
Data Augmentation
      │
      ▼
Preprocesamiento ResNet50
      │
      ▼
ResNet50
Transfer Learning
      │
      ▼
GlobalAveragePooling2D
      │
      ▼
Dense 256 + ReLU
      │
      ▼
Dropout 0.4
      │
      ▼
Dense 3 + Softmax
      │
      ▼
Predicción
```

El modelo fue desarrollado utilizando aprendizaje supervisado para clasificación multiclase.

Versión actual del modelo:

```text
PROINPA-ResNet50-v1.0
```

---

# Tecnologías Utilizadas

## Frontend

- Vue 3
- Vue Router
- Axios
- Vite
- JavaScript
- HTML5
- CSS3

## Backend

- Laravel 12
- PHP
- REST API
- Autenticación
- Roles y permisos
- OTP mediante correo electrónico

## Inteligencia Artificial

- Python 3.11
- TensorFlow
- Keras
- ResNet50
- Transfer Learning
- NumPy
- Pillow
- Scikit-learn
- Matplotlib
- Hugging Face Datasets

## Microservicio CNN

- FastAPI
- Uvicorn
- Python Multipart

## Base de Datos

- PostgreSQL

## Contenerización

- Docker
- Docker Compose
- Nginx

## Control de Versiones

- Git

---

# Estructura General del Proyecto

```text
proyecto_inte_cnn/
│
├── frontend/
│   ├── src/
│   ├── public/
│   └── package.json
│
├── backend/
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── storage/
│   └── composer.json
│
├── cnn_service/
│   ├── app/
│   │   ├── main.py
│   │   └── inference.py
│   │
│   ├── training/
│   │   ├── train.py
│   │   ├── evaluate.py
│   │   └── download_and_split_plantvillage.py
│   │
│   ├── dataset/
│   ├── artifacts/
│   ├── model/
│   │   ├── potato_resnet50.keras
│   │   └── model_metadata.json
│   │
│   └── requirements.txt
│
└── docker-compose.yml
```

---

# Puertos Utilizados

| Servicio            | Puerto |
| ------------------- | -----: |
| Frontend Docker     |   8080 |
| Frontend Vite local |   5173 |
| Laravel Backend     |   8000 |
| FastAPI CNN         |   8001 |
| PostgreSQL          |   5432 |

---

# Configuración Inicial

Antes de ejecutar el sistema se deben tener instaladas las herramientas necesarias.

```text
PHP
Composer
Node.js
NPM
Python 3.11
PostgreSQL
Docker Desktop (opcional)
```

---

# Ejecución del Sistema en Entorno Local

Para trabajar en desarrollo local se deben iniciar tres servicios principales:

```text
1. Microservicio CNN
2. Backend Laravel
3. Frontend Vue
```

PostgreSQL también debe encontrarse iniciado.

---

## 1. Iniciar el Microservicio CNN

Abrir una terminal PowerShell y acceder a:

```powershell
cd cnn_service
```

Activar el entorno virtual:

```powershell
.\.venv\Scripts\Activate.ps1
```

Si todavía no existe el entorno virtual:

```powershell
python -m venv .venv
```

Activarlo:

```powershell
.\.venv\Scripts\Activate.ps1
```

Instalar dependencias si es la primera ejecución:

```powershell
pip install -r requirements.txt
```

Iniciar FastAPI:

```powershell
uvicorn app.main:app --reload --port 8001
```

El servicio CNN estará disponible en:

```text
http://127.0.0.1:8001
```

Para verificar su estado:

```text
http://127.0.0.1:8001/health
```

Una respuesta correcta debe indicar que el modelo se encuentra cargado y listo.

Ejemplo:

```json
{
  "status": "ready",
  "model_loaded": true,
  "model_version": "PROINPA-ResNet50-v1.0"
}
```

Mantener esta terminal abierta.

---

## 2. Iniciar Laravel

Abrir una segunda terminal.

```powershell
cd backend
```

Instalar dependencias si es la primera ejecución:

```powershell
composer install
```

Si no existe el archivo `.env`:

```powershell
copy .env.example .env
```

Generar la clave de Laravel:

```powershell
php artisan key:generate
```

Configurar PostgreSQL en `.env`.

Ejemplo:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=backend_db
DB_USERNAME=postgres
DB_PASSWORD=TU_CONTRASEÑA
```

Configurar la dirección del servicio CNN y el frontend local:

```env
CNN_SERVICE_URL=http://127.0.0.1:8001
FRONTEND_URL=http://localhost:5173
```

Para desarrollo local, el `.env.example` utiliza:

```env
MAIL_MAILER=log
OTP_DEBUG=true
```

Con esta configuración no se necesita Mailpit ni Gmail para probar el inicio de sesión. Laravel registra el correo en `storage/logs/laravel.log` y la pantalla OTP muestra el código únicamente cuando `APP_ENV=local`, `APP_DEBUG=true` y `OTP_DEBUG=true`. Para un entorno real, configure SMTP y cambie `OTP_DEBUG=false`.

Limpiar configuración:

```powershell
php artisan config:clear
```

Ejecutar las migraciones:

```powershell
php artisan migrate
```

Ejecutar seeders si es necesario:

```powershell
php artisan db:seed
```

Para reconstruir completamente la base de datos:

```powershell
php artisan migrate:fresh --seed
```

Crear el enlace para visualizar las fotografías almacenadas:

```powershell
php artisan storage:link
```

Finalmente iniciar Laravel:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

El backend estará disponible en:

```text
http://127.0.0.1:8000
```

Mantener esta terminal abierta.

---

## 3. Iniciar Vue

Abrir una tercera terminal.

```powershell
cd frontend
```

Instalar dependencias si es la primera ejecución:

```powershell
npm install
```

Iniciar el frontend:

```powershell
npm run dev
```

Vite mostrará una dirección similar a:

```text
http://localhost:5173
```

Abrir esa dirección en el navegador.

---

# Orden Recomendado para Iniciar el Sistema

```text
PostgreSQL
    ↓
FastAPI CNN
Puerto 8001
    ↓
Laravel
Puerto 8000
    ↓
Vue
Puerto 5173
    ↓
Navegador
```

Es recomendable iniciar primero el microservicio CNN antes de utilizar el módulo de diagnóstico.

---

# Ejecución Utilizando Docker

La infraestructura principal también puede levantarse mediante Docker Compose.

Desde la carpeta raíz del proyecto:

```bash
docker compose up --build -d
```

Verificar los contenedores:

```bash
docker ps
```

Ejecutar las migraciones:

```bash
docker exec -it proyecto_backend php artisan migrate
```

Ejecutar seeders:

```bash
docker exec -it proyecto_backend php artisan db:seed
```

Para reconstruir completamente la base:

```bash
docker exec -it proyecto_backend php artisan migrate:fresh --seed
```

Crear el enlace de almacenamiento:

```bash
docker exec -it proyecto_backend php artisan storage:link
```

La configuración actual de Docker contempla cinco servicios principales:

```text
Docker Compose
├── frontend
├── backend
├── cnn
├── database
└── mailpit
```

El backend se comunica con el contenedor CNN mediante `CNN_SERVICE_URL=http://cnn:8001`. Para las pruebas de correo OTP dentro de Docker se utiliza Mailpit. Su bandeja se abre en `http://localhost:8025`. PostgreSQL y el servicio CNN incluyen comprobaciones de salud para reducir errores de arranque entre servicios.

> **Importante:** el archivo entrenado `cnn_service/model/potato_resnet50.keras` no se incluye en paquetes de código livianos cuando su tamaño es elevado. Antes de levantar Docker, copie en `cnn_service/model/` el modelo entrenado de su instalación actual junto con `model_metadata.json` si corresponde.

---

# Acceso al Sistema

## Desarrollo Local

Frontend:

```text
http://localhost:5173
```

Backend:

```text
http://127.0.0.1:8000
```

CNN:

```text
http://127.0.0.1:8001
```

## Docker

Frontend:

```text
http://localhost:8080
```

Backend:

```text
http://localhost:8000
```

---

# Usuarios de Prueba

> Credenciales destinadas únicamente al entorno local de desarrollo.

## Administrador

Correo:

```text
admin@proinpa.test
```

Contraseña:

```text
Proinpa123*
```

## Usuario

Correo:

```text
usuario@proinpa.test
```

Contraseña:

```text
User123*
```

---

# Funcionamiento del Diagnóstico CNN

El proceso de diagnóstico funciona de la siguiente manera:

```text
Usuario selecciona inspección
        ↓
Carga fotografía de hoja
        ↓
Vue envía fotografía
        ↓
Laravel recibe solicitud
        ↓
Laravel envía imagen a FastAPI
        ↓
FastAPI prepara imagen
        ↓
ResNet50 realiza predicción
        ↓
Softmax genera probabilidades
        ↓
FastAPI devuelve JSON
        ↓
Laravel guarda diagnóstico
        ↓
PostgreSQL almacena resultado
        ↓
Vue muestra diagnóstico
        ↓
Técnico valida resultado
```

La API CNN devuelve información como:

```json
{
  "clase": "healthy",
  "nombre": "Sana (Sin enfermedad)",
  "codigo_enfermedad": "CNN-SANA",
  "confianza": 99.35,
  "modelo_version": "PROINPA-ResNet50-v1.0",
  "top3": []
}
```

---

# Entrenamiento del Modelo CNN

El archivo principal de entrenamiento es:

```text
cnn_service/training/train.py
```

El entrenamiento utiliza:

```text
Arquitectura: ResNet50
Aprendizaje: Supervisado
Tipo: Clasificación multiclase
Técnica: Transfer Learning
Entrada: 224 × 224 × 3
Batch Size: 32
Máximo de épocas: 20
Optimizador: Adam
Learning Rate inicial: 0.001
Loss: Sparse Categorical Cross-Entropy
Activación final: Softmax
Dropout: 0.4
Seed: 42
```

La distribución utilizada fue aproximadamente:

| Conjunto      | Imágenes | Porcentaje |
| ------------- | -------: | ---------: |
| Entrenamiento |     1496 |    69.52 % |
| Validación    |      328 |    15.24 % |
| Prueba        |      328 |    15.24 % |
| Total         |     2152 |      100 % |

---

# Evaluación del Modelo

El archivo encargado de evaluar la CNN es:

```text
cnn_service/training/evaluate.py
```

El modelo fue evaluado sobre un conjunto independiente de 328 imágenes.

Resultados obtenidos:

| Métrica             | Resultado |
| ------------------- | --------: |
| Accuracy            |   96.95 % |
| Precision ponderada |   97.30 % |
| Recall ponderado    |   96.95 % |
| F1-Score ponderado  |   97.03 % |

Estos resultados corresponden al conjunto de prueba utilizado durante el desarrollo del modelo y no deben interpretarse como una validación definitiva en condiciones reales de campo.

---

# Validación del Diagnóstico

Los resultados generados automáticamente por la CNN inicialmente quedan con estado:

```text
Pendiente
```

Posteriormente, el personal autorizado puede:

```text
Confirmar
Corregir
Rechazar
```

el diagnóstico generado por la Inteligencia Artificial.

De esta manera, la CNN funciona como una herramienta de apoyo al diagnóstico y no sustituye la decisión técnica o agronómica.

---

# Versiones

| Versión  | Estado     | Descripción                             |
| -------- | ---------- | --------------------------------------- |
| v1.0.0   | Estable    | Funcionalidades principales del sistema |
| v1.1.0   | Estable    | Mejoras en módulos agrícolas            |
| v1.2.0   | Desarrollo | Dockerización e integración             |
| CNN v1.0 | Funcional  | ResNet50 con tres categorías            |

---

# Validaciones y controles incorporados

La versión depurada incorpora validaciones tanto en Vue como en Laravel para evitar operaciones inconsistentes. Entre ellas se incluyen validación de formato y tamaño de fotografías, selección únicamente de inspecciones elegibles para diagnóstico, verificación de cultivos activos, prevención de diagnósticos pendientes duplicados, comprobación del estado del microservicio CNN, límites de paginación y validaciones durante la confirmación o corrección de diagnósticos.

El frontend muestra el estado del modelo antes de permitir un análisis y el backend vuelve a validar todas las reglas críticas, por lo que las validaciones visuales no sustituyen las reglas del servidor.

---

# Mejoras del aporte ingenieril (etapas 1 a 8)

| Etapa | Qué se agregó | Dónde |
|---|---|---|
| 1 | Modelo `PROINPA-ResNet50-v2.1-prototipo` por defecto (`CNN_MODEL_PATH` y `CNN_METADATA_PATH` siguen funcionando) | `cnn_service/app/inference.py` |
| 2 | El diagnóstico muestra lo que la IA calcula: top-3 con barras, margen, confianza calibrada, calidad de imagen y versión; los rechazos indican el motivo exacto y qué hacer | `DiagnosticoIaInforme.vue` |
| 3 | Grad-CAM: mapa de calor sobre la hoja, guardado con el diagnóstico y mostrado lado a lado (si falla, el diagnóstico sigue funcionando; `CNN_GRADCAM=false` lo desactiva) | `cnn_service/app/gradcam.py` |
| 4 | Vista y PDF «Desempeño del modelo»: matriz de confusión, precisión/recall/F1, confirmados/corregidos/rechazados y evolución mensual | `/dashboard/desempeno-modelo` |
| 5 | Exportación del dataset validado (ZIP con fotos por clase final + `metadata.csv`) para reentrenar; solo Administrador y Agrónomo | `training_v2/README.md` |
| 6 | Login facial como segundo factor (correo y contraseña primero; el rostro reemplaza al OTP, con límite de intentos y sin exponer distancias); OTP reforzado; validaciones de usuarios | `AuthController`, `LoginView.vue` |
| 7 | Comparación ResNet50 vs EfficientNetB0 con la misma partición, prueba de McNemar, tamaño, parámetros y tiempo en CPU. **No cambia el modelo activo** | `cnn_service/artifacts_v2c/` |
| 8 | Validaciones de módulos: coordenadas dentro de Bolivia, superficies, suma de parcelas, montos con 2 decimales, estados de ventas, agenda del técnico, rango de reportes | `backend/app/Http/Requests` |

## Pruebas automáticas

Las pruebas **nunca usan la base real**: `phpunit.xml` apunta a `proinpa_test` y `tests/TestCase.php` aborta si la base no se llama así.

```bash
cd backend
php artisan test
```

Para crear la base de pruebas una sola vez: `createdb proinpa_test` (PostgreSQL). Las pruebas cubren diagnósticos, desempeño, dataset, autenticación, usuarios, módulos agrícolas, comercial (stock, ventas, cotizaciones) e inspecciones/alertas.

---

# Estado Actual del Proyecto

El sistema se encuentra funcional en entorno de desarrollo local.

Actualmente se encuentran implementados:

```text
Frontend Vue
Backend Laravel
PostgreSQL
Gestión agrícola
Inspecciones
Diagnósticos
CNN ResNet50
FastAPI
Validación técnica
Inventario
Alertas
Ventas
Reportes
OTP
Docker Compose
```

Trabajos futuros:

```text
Despliegue en servidor o nube
Configuración HTTPS
Dominio institucional
Optimización para producción
Ampliación del dataset
Incorporación de nuevas enfermedades
Validación con imágenes reales de campo
```

---

# Proyecto Integrador

**Sistema Inteligente de Apoyo a la Gestión Agrícola para la Detección de Enfermedades Foliares en Cultivos de Papa mediante Redes Neuronales Convolucionales**

Caso:

```text
Fundación PROINPA
```

Carrera:

```text
Ingeniería de Sistemas
```

Gestión:

```text
2026
```
