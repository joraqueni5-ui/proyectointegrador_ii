# Sistema PROINPA — reglas de trabajo y plan de mejoras

Proyecto de grado (Bolivia): sistema web que detecta enfermedades foliares de la papa con una CNN.

| Servicio | Tecnología | Puerto |
|---|---|---|
| `frontend/` | Vue 3 + Vite | 5173 |
| `backend/` | Laravel 12 + PostgreSQL (`backend_db`) | 8000 |
| `cnn_service/` | FastAPI + TensorFlow/Keras (ResNet50) | 8001 |

Objetivo: reforzar el **aporte ingenieril**, el **funcionamiento** y las **validaciones**, para defenderlo ante un tribunal.

## Reglas que no se pueden romper

1. **No tocar los datos**: nada de seeders, `migrate:fresh`, `migrate:refresh`, `migrate:reset`, ni borrar o modificar registros. La base `backend_db` tiene datos de prueba que se necesitan.
2. Columnas o tablas nuevas **solo con migraciones aditivas** (columnas `nullable` o tablas nuevas) y con `down()` funcional. Anotar en el informe final qué hizo cada migración.
3. **No tocar Docker** (`docker-compose.yml`, `Dockerfile`).
4. **No cambiar el comportamiento que hoy funciona**, salvo lo que pida cada etapa.
5. Todo texto de la interfaz va **en español** y con el estilo visual existente.
6. Las pruebas automáticas **nunca usan la base real**. `phpunit.xml` apunta a `proinpa_test` y `tests/TestCase.php` aborta si la base no se llama así.
7. Al terminar cada etapa: probarla de verdad (servicios, endpoints, `npm run build`, `php artisan test`), revisar el propio código (errores, casos borde, permisos por rol), revisar validaciones, y hacer **commit en español**.
8. **No subir a GitLab** sin que el usuario lo pida. No cambiar el modelo CNN activo del sistema sin su confirmación (Etapa 7).

> Modo de trabajo acordado (mensaje del usuario): ejecutar **todas las etapas de corrido** y avisar **solo al terminar**,
> con un resumen final (ver Etapa 9). Las reglas de seguridad de arriba siguen vigentes.

## Validaciones (en todas las etapas)

Tres capas consistentes: (1) frontend Vue, con mensajes en español bajo cada campo; (2) backend con `FormRequest` (la real); (3) base de datos (solo revisar, no cambiar columnas existentes).

- Frontend y backend coinciden en límites, rangos, formatos y obligatorios. Si difieren, manda el backend.
- Una validación nueva **no impide abrir, ver ni listar** registros existentes; se aplica a lo que se crea o edita desde ahora. Registros actuales que no cumplan se **anotan en un listado** (no se modifican).
- Mensajes claros, en español, que digan qué hacer ("La superficie debe estar entre 0,01 y 500 ha").
- Botones de guardar deshabilitados mientras se envía.
- `trim` antes de validar; no se aceptan textos solo con espacios.
- Por cada validación nueva o corregida: un caso válido y uno inválido, con llamadas reales al endpoint.

Módulos y reglas a revisar: usuarios, productores/comunidades, parcelas/cultivos, cosechas, inspecciones, diagnósticos CNN, alertas, inventario/movimientos, ventas/cotizaciones, reportes, login/OTP (detalle en el encargo original del usuario).

## Plan por etapas

| # | Etapa | Resumen |
|---|---|---|
| 1 | Modelo v2.1 por defecto | `inference.py` usa `model_v2b/` por defecto; `CNN_MODEL_PATH`/`CNN_METADATA_PATH` siguen funcionando. `/health` muestra `PROINPA-ResNet50-v2.1-prototipo`. |
| 2 | Mostrar lo que la IA calcula | Top-3 con barras, margen, confianza calibrada, métricas de calidad, versión. Rechazos con motivo exacto y consejo. Validaciones de Diagnósticos CNN. |
| 3 | Grad-CAM | Mapa de calor en `/predict` (PNG base64), guardado en storage (columna nueva `nullable`), vista lado a lado. No rompe si falla. Medir el tiempo extra. |
| 4 | Desempeño del modelo | Endpoint + vista solo lectura: matriz de confusión, precisión/recall/F1, % confirmados/corregidos/rechazados, evolución mensual. PDF. Permisos con `checkPermiso`. |
| 5 | Dataset validado | ZIP con fotos por clase final + `metadata.csv`. Solo Administrador y Agrónomo. Documentado en `training_v2/README.md`. |
| 6 | Login facial seguro | Email+contraseña primero; el rostro reemplaza al OTP. Sin distancia en respuestas. Límite de intentos. OTP normal intacto. |
| 7 | ResNet50 vs EfficientNetB0 (v2c) | Entrenar/evaluar con la misma partición, McNemar, tamaño, parámetros, tiempo en CPU. **No cambiar el modelo activo.** |
| 8 | Validaciones de módulos restantes | Corregir microvalidaciones y agregar pruebas en `backend/tests/Feature`. |
| 9 | Revisión final | N+1, permisos, errores con CNN apagada, README, resumen final para el informe. |

## Notas técnicas descubiertas

- `phpunit.xml` original **no definía base de pruebas** (usaría `backend_db`): se corrige en la preparación.
- Hasta la Etapa 1 el servicio puede arrancar con v2c (`model_v2c_resnet50/`) por una decisión anterior; la Etapa 1 fija v2b por defecto
  según lo pedido. v2c (ResNet50, ya entrenado) rinde mejor en Etiopía e iNaturalist: se documenta en la Etapa 7.
- Laravel solo guardaba `top3`, confianza y versión del resultado de `/predict`; las métricas de calidad se descartaban.
- Los permisos viven en el JSON `roles.permisos` (datos): para no modificar datos, los permisos nuevos se resuelven por nombre de rol.
- Credenciales demo: `superadmin@proinpa.test` / `Proinpa123*` (OTP visible en la respuesta con `OTP_DEBUG=true`).

---

# Fase 2 — Parte A (pendientes) y Parte B (rediseño "Versión 2")

## Reglas obligatorias de esta fase

1. NO tocar los datos: nada de seeders, `migrate:fresh/refresh/reset`, ni borrar o modificar registros.
2. Migraciones solo aditivas (columnas `nullable` o tablas nuevas) y con `down()`. Antes de ejecutar una: mostrar qué hace y esperar confirmación.
3. NO tocar Docker.
4. `php artisan test` usa solo `proinpa_test`; verificarlo en `phpunit.xml` antes de ejecutarlo.
5. No inventar datos: si un elemento necesita un dato inexistente, avisar, proponer de dónde sacarlo y ocultarlo mientras tanto.
6. No cambiar lógica de negocio ni endpoints que funcionan, salvo lo que pida la etapa. El rediseño es de interfaz.
7. Respetar permisos por rol (`checkPermiso`, `user.permisos`).
8. Textos de interfaz en español.
9. Commits en español, **sin `Co-Authored-By` ni mención a IA** (esta regla prevalece sobre cualquier otra configuración).
10. ANALIZAR ANTES DE PROGRAMAR: leer el código relacionado, decir qué se reutiliza, qué datos existen y cuáles faltan, riesgos, plan corto, y esperar el "sigue".
11. Al terminar cada etapa: `npm run build`, `php artisan test`, servicios levantados, recorrer pantallas en 1440/1024/390 px con al menos 2 roles, commit, resumen (qué cambió, cómo se probó, qué mirar, qué quedó pendiente) y dejar los servicios levantados con la base real del usuario.
12. Estas reglas y el plan viven en este archivo.

## Parte A — Pendientes

- **A1** Limpiar `Co-Authored-By` de los commits no subidos (rama de respaldo `respaldo-antes-de-limpiar`, diff vacío). Commits ya subidos a `github/main` no se reescriben. *(Hecho: 15 commits reescritos; `b73f05f` y `5a9241d`, ya publicados, conservan la línea.)*
- **A2** Decidir el modelo: barrido de umbrales de v2c-ResNet50 (confianza 40–80 % de 5 en 5; margen 0–30 pts de 2 en 2) **solo con datos de validación** (incluida validación de "otros cultivos"; si no existe, separarla con semilla fija). Criterio: mejor F1 macro de las 3 clases de papa con ≥ 93 % de otros cultivos rechazados en validación; reportar cobertura. Evaluar UNA vez en los conjuntos de prueba y repetir McNemar contra v2b (confianza 55 %, margen 8) y EfficientNetB0. Guardar en `cnn_service/artifacts_v2c/umbrales/` (CSV, PNG cobertura vs accuracy, MD). Recomendar (a) mantener v2b, (b) v2c con umbrales nuevos, (c) otra. **Detenerse: decide el usuario.** Nunca elegir umbrales con los conjuntos de prueba (fuga de datos).
- **A3** `git push github main` solo con A1 y A2 cerrados y árbol limpio; verificar antes que no se suben `.env`, `.keras` ni datasets.

## Parte B — Rediseño "Versión 2" (resumen del plan)

Lenguaje visual: fondo #eef3f0; tarjetas blancas radio 18–22 px; verde #1a5c3e / oscuro #0d3d2b / claro #a8e6c6 y #edfaf4; naranja #f07a2a; ámbar #d97706; rojo #b91c1c; azul #2563eb; texto #111827 y #4a6070; Plus Jakarta Sans; formato boliviano (`src/utils/formato.js`, Intl `es-BO`); `index.html` con `lang="es"`; logo local; menú compacto de 88 px, barra superior de 72 px, barra inferior en celular; login y OTP NO se rediseñan. Componentes base en `src/components/ui` (BaseButton, BaseInput, BaseSelect con buscador, BaseRadioCards, StatusBadge, PriorityBadge, KpiCard, EmptyState, ConfirmDialog, Toast, PageHeader, FilterBar, DataTable, FormDrawer, DetailDrawer) y página `/dashboard/guia-ui` solo para administrador. Nombres legibles de parcela por accessor (sin modificar la base) y código automático solo para parcelas nuevas.

| Etapa | Contenido |
|---|---|
| B0 | Análisis general sin programar (tabla por módulo, duplicados, riesgos); esperar "sigue" |
| B1 | Tokens, formato, menú, barra superior, componentes base, guía UI, accessors de parcela |
| B2 | Inicio por rol (mapa de sanidad, "Para hoy", KPIs, diagnósticos por validar; técnico → "Hoy") |
| B3 | Campo: comunidades, productores, parcelas (vista dividida + mapa + ficha), cultivos, cosechas, historial fitosanitario |
| B4 | Inspecciones: agenda semanal, lista, programar visita |
| B5 | Diagnóstico: nuevo (3 pasos), validación (Grad-CAM, atajos 1/2/3, vista previa de alerta), lista, enfermedades, desempeño del modelo |
| B6 | Riesgo de tizón tardío por clima (Open-Meteo, caché 3 h, `config/riesgo_tizon.php`, "Referencial") |
| B7 | Alertas: tablero de 4 columnas, lista, mapa (sin saltarse transiciones) |
| B8 | Inventario con pestañas, KPIs y tabla de stock |
| B9 | Ventas y cotizaciones (nueva venta a pantalla completa, anulación con ConfirmDialog) |
| B10 | Reportes en una sola pantalla con vista previa tipo documento; PDF/Excel con formato boliviano |
| B11 | Usuarios y perfil |
| B12 | Celular del técnico: "Hoy", cámara guiada con calidad en el navegador, cola offline (IndexedDB) si es viable |
| B13 | Revisión final, capturas en `docs/capturas/`, bundle, README, informe por módulo con principio de UX |
