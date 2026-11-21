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
