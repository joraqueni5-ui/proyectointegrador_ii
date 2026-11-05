# Corrección de Login y OTP

## Problema encontrado en el ZIP original

El ZIP original mezclaba valores pensados para Docker con la ejecución local:

- `DB_HOST=database` solo funciona dentro de Docker.
- `MAIL_HOST=mailpit` solo funciona si existe un servicio Mailpit.
- El `docker-compose.yml` original no incluía Mailpit.
- El frontend llamaba `/resend-otp` y `/verify-face`, pero esas rutas no estaban declaradas.
- La opción de dispositivo confiable dependía de una cookie, pero Axios/CORS no enviaban credenciales.
- Una API protegida sin sesión podía intentar redirigir a la ruta web `login` y generar error 500.

## Funcionamiento local corregido

Copiar:

```powershell
copy .env.example .env
php artisan key:generate
```

En local, el `.env.example` utiliza:

```env
DB_HOST=127.0.0.1
MAIL_MAILER=log
OTP_DEBUG=true
FRONTEND_URL=http://localhost:5173
CNN_SERVICE_URL=http://127.0.0.1:8001
```

Con `MAIL_MAILER=log` no es necesario instalar Mailpit ni configurar Gmail para probar el sistema. El código OTP se muestra en la pantalla de verificación **solo** cuando se cumplen simultáneamente:

```env
APP_ENV=local
APP_DEBUG=true
OTP_DEBUG=true
```

En producción debe usarse:

```env
APP_DEBUG=false
OTP_DEBUG=false
```

y configurar un SMTP real.

## Docker

Docker Compose ahora incluye `mailpit`. La bandeja de pruebas está disponible en:

```text
http://localhost:8025
```

## Archivos principales corregidos

- `backend/.env.example`
- `backend/config/otp.php`
- `backend/config/services.php`
- `backend/app/Services/Auth/OtpService.php`
- `backend/app/Http/Controllers/AuthController.php`
- `backend/bootstrap/app.php`
- `backend/config/cors.php`
- `backend/routes/api.php`
- `frontend/src/services/api.js`
- `frontend/src/views/auth/LoginView.vue`
- `frontend/src/views/auth/OtpView.vue`
- `docker-compose.yml`

También se eliminó `RegisterView.vue`, que estaba huérfano porque el router define explícitamente que el sistema no tiene registro público.
