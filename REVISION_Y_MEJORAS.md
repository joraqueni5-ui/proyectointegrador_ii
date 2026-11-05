# Revisión y mejoras aplicadas

## Alcance

Se revisaron el frontend Vue 3, el backend Laravel 12, la configuración Docker y la integración documentada con el microservicio CNN. El código del `cnn_service` se mantuvo funcionalmente sin cambios de arquitectura; se incluyó la versión liviana del código sin dataset, entorno virtual ni modelo entrenado pesado.

## Limpieza realizada

- Se excluyeron `frontend/node_modules/` y `backend/vendor/` del paquete final. Se regeneran con `npm ci` y `composer install`.
- Se excluyeron `.env`, logs, cachés, `.git`, artefactos compilados y `__pycache__`.
- Se eliminó la copia completa y redundante de `face-api.js` ubicada en `frontend/package/` y el archivo `face-api.js-0.22.2.tgz`.
- Se eliminó la dependencia antigua `face-api.js`; el proyecto conserva `@vladmandic/face-api`, que es la librería que realmente importa `FaceCaptureModal.vue`.
- Se eliminaron componentes Vue sin referencias: `HelloWorld.vue`, `AuthCard.vue`, `AuthLayout.vue` y `ProductoDeleteModal.vue`.
- Se eliminaron assets de plantilla Vite/Vue sin uso y el script auxiliar `fix-model.cjs` sin referencias.
- Se eliminaron las clases duplicadas y sin uso `Http/Requests/Auth/StoreUserRequest.php` y `UpdateUserRequest.php`; se conservan las clases del namespace `Http/Requests/User`, que son las utilizadas por `UserController`.
- Se eliminó una carga duplicada de la relación `enfermedad` en `DiagnosticoController`.

## Microvalidaciones y robustez

### Diagnóstico CNN

- El selector de inspecciones solicita al backend solo inspecciones elegibles para diagnóstico.
- Una inspección elegible debe ser de tipo `Cultivo`, estar `En_proceso` o `Completada`, tener cultivo activo y no poseer otro diagnóstico pendiente.
- El backend vuelve a comprobar que el cultivo esté activo y que corresponda a la misma parcela de la inspección.
- Se mantienen las validaciones de imagen JPG/JPEG/PNG/WEBP y máximo 5 MB.
- El frontend rechaza archivos vacíos y vuelve a comprobar que la inspección seleccionada continúe siendo elegible.
- Se consulta `/diagnosticos/cnn/estado` y se muestra el estado del modelo y su versión.
- El botón de análisis queda deshabilitado cuando el servicio CNN no está disponible.
- Se libera correctamente la URL temporal de la vista previa de imagen al desmontar el componente.

### Validación agronómica

- Una corrección solo puede seleccionar una enfermedad activa.
- Se evita marcar como `Corregido` un diagnóstico si se selecciona exactamente la misma enfermedad; en ese caso corresponde `Confirmado`.
- Las observaciones de validación tienen límite de 1000 caracteres en frontend y backend.

### Consultas y filtros

- Los filtros de diagnósticos e inspecciones se validan en Laravel antes de formar consultas.
- `per_page` se limita entre 1 y 100 para evitar consultas excesivas.
- Se valida que el rango de fechas de inspecciones sea coherente (`fecha_hasta >= fecha_desde`).

## Docker

- `frontend/Dockerfile` utiliza `npm ci` para instalaciones reproducibles a partir de `package-lock.json`.
- PostgreSQL incorpora `healthcheck`.
- El microservicio CNN incorpora `healthcheck` sobre `/health`.
- El backend espera a que PostgreSQL y CNN estén saludables antes de iniciar.
- Se actualizó la documentación: el `docker-compose.yml` actual sí contiene `frontend`, `backend`, `cnn` y `database`.

## Verificaciones realizadas

- 121 archivos `.vue` analizados y compilados sintácticamente con `@vue/compiler-sfc`: sin errores.
- Archivos JavaScript de `src/` comprobados con `node --check`: sin errores.
- Archivos PHP de `app/`, `routes/` y `database/` comprobados con `php -l`: sin errores.
- Código Python de `cnn_service/app` y `cnn_service/training` comprobado con `compileall`: sin errores.
- `package-lock.json` comprobado mediante npm en modo offline: consistente y sin vulnerabilidades reportadas por esa comprobación.
- `docker-compose.yml` validado como YAML y contiene los cuatro servicios esperados.
- No quedaron componentes Vue sin referencias detectables ni archivos fuente exactamente duplicados dentro de `frontend/src` o `backend/app`.

## Limitaciones de la validación en este entorno

No se ejecutó un build completo de Vite porque el entorno de revisión no dispone en caché de todas las dependencias Linux necesarias para reinstalar `node_modules`. Los `node_modules` recibidos provenían de otro sistema operativo y no son portables. Tampoco se ejecutó la suite completa de Laravel porque el PHP anfitrión de revisión no tiene habilitada la extensión `mbstring`; el `Dockerfile` del backend sí instala `mbstring`. Las verificaciones sintácticas y estructurales indicadas arriba sí se completaron correctamente.

## Modelo CNN

El paquete liviano no contiene `potato_resnet50.keras` ni el dataset, para evitar un ZIP innecesariamente pesado. Copiar el modelo entrenado actual a `cnn_service/model/` antes de iniciar la versión Docker. El código mantiene la arquitectura ResNet50 y la integración existente.


## Corrección 2 - Autenticación OTP y entornos local/Docker

Durante una segunda auditoría del ZIP original se detectaron errores de configuración que impedían el inicio de sesión:

- `backend/.env.example` usaba `DB_HOST=database`, válido solo dentro de Docker.
- `backend/.env.example` usaba `MAIL_HOST=mailpit`, pero el `docker-compose.yml` original no incluía ningún servicio Mailpit.
- El frontend utilizaba `/resend-otp` y `/verify-face`, pero esas rutas públicas no estaban declaradas en `routes/api.php`. Además existía un `RegisterView.vue` huérfano aunque el router indica que el sistema no permite registro público.
- `Mantener sesión` usaba una cookie, pero Axios y CORS tenían credenciales deshabilitadas.
- Las excepciones de autenticación API podían intentar redirigir a una ruta web `login` inexistente.
- La generación y envío OTP estaba duplicada varias veces en `AuthController`.

Correcciones aplicadas:

- `.env.example` ahora está orientado a ejecución local (`127.0.0.1`, puerto Vite 5173 y CNN 8001).
- En local se usa `MAIL_MAILER=log` + `OTP_DEBUG=true`, de forma que no se necesita SMTP para probar el sistema. El OTP de desarrollo se muestra únicamente con `APP_ENV=local` y `APP_DEBUG=true`.
- Se agregó `OtpService` para centralizar generación, expiración y entrega de OTP con `random_int`.
- Se agregaron las rutas faltantes `/resend-otp` y `/verify-face`, con límites de frecuencia. Se eliminó `RegisterView.vue` y el método `register()` no utilizado para respetar que el alta de usuarios es administrativa.
- Se corrigió CORS/Axios para permitir la cookie de dispositivo confiable.
- Las APIs no autenticadas ahora devuelven JSON 401 en lugar de intentar redirigir a `login`.
- Docker Compose ahora incluye Mailpit; su bandeja puede abrirse en `http://localhost:8025`.
