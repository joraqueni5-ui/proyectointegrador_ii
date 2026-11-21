<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Autenticación en dos pasos.
 *
 *  1. /login valida correo y contraseña.
 *  2. Segundo factor, a elección del usuario:
 *       - código OTP (/verify-otp), o
 *       - rostro (/verify-face), que REEMPLAZA al OTP: solo se acepta con el ticket de un solo uso
 *         que entrega /login tras validar la contraseña (el rostro nunca da acceso por sí solo).
 */
class AuthController extends Controller
{
    private const MENSAJE_CUENTA_INACTIVA = 'Tu cuenta está desactivada. Contacta al administrador.';

    /** Hash válido de una contraseña imposible: iguala el tiempo de respuesta si el correo no existe. */
    private const HASH_FALSO = '$2y$12$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi';

    private const LOGIN_MAX_FALLOS = 5;
    private const LOGIN_BLOQUEO_SEGUNDOS = 900;
    private const OTP_MAX_FALLOS = 5;
    private const REENVIO_MAX = 5;
    private const ROSTRO_BLOQUEO_FALLOS = 5;

    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    // ---------------------------------------------------------------- paso 1: contraseña

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'metodo' => 'nullable|in:otp,rostro',
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $clave = $this->claveLogin($request);

        if (RateLimiter::tooManyAttempts($clave, self::LOGIN_MAX_FALLOS)) {
            return $this->demasiadosIntentos(RateLimiter::availableIn($clave));
        }

        $user = $this->buscarPorEmail($request->email);

        // Siempre se compara contra un hash para no revelar (por el tiempo) si el correo existe.
        $credencialesOk = Hash::check((string) $request->password, $user?->password ?? self::HASH_FALSO) && $user !== null;

        if (! $credencialesOk) {
            RateLimiter::hit($clave, self::LOGIN_BLOQUEO_SEGUNDOS);

            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        RateLimiter::clear($clave);

        $deviceId = $request->cookie('trusted_device_id');

        $isTrusted = $deviceId && DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->exists();

        if ($isTrusted) {
            return $this->respuestaConToken($user, 'Acceso directo por dispositivo de confianza.', [
                'skip_otp' => true,
            ]);
        }

        // El rostro reemplaza al OTP solo si el usuario lo pidió y tiene un rostro registrado.
        if ($request->input('metodo') === 'rostro' && $this->tieneRostroValido($user)) {
            return response()->json([
                'message' => 'Contraseña correcta. Verifica tu identidad con el rostro.',
                'email' => $user->email,
                'metodo' => 'rostro',
                'requiere_rostro' => true,
                'login_token' => $this->crearTicketRostro($user),
                'expires_in_minutes' => $this->minutosTicket(),
            ]);
        }

        $extra = ['skip_otp' => false, 'metodo' => 'otp'];

        if ($request->input('metodo') === 'rostro') {
            $extra['rostro_disponible'] = false;
        }

        return $this->emitirOtp(
            $user,
            $request->input('metodo') === 'rostro'
                ? 'No tienes un rostro registrado. Te enviamos un código OTP.'
                : 'OTP enviado correctamente.',
            'OTP generado en modo de desarrollo.',
            'No se pudo enviar el código OTP. Revisa la configuración de correo.',
            $extra
        );
    }

    // ---------------------------------------------------------------- paso 2a: OTP

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|digits:6',
            'remember_device' => 'boolean',
        ], [
            'otp_code.required' => 'Debe ingresar el código OTP.',
            'otp_code.digits' => 'El código OTP debe tener 6 dígitos.',
        ]);

        $user = $this->buscarPorEmail($request->email);

        // Respuesta idéntica para "correo inexistente" y "código incorrecto": no se revela quién existe.
        if (! $user) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 422);
        }

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        $claveFallos = 'otp_fallos:'.$user->id;

        if (RateLimiter::tooManyAttempts($claveFallos, self::OTP_MAX_FALLOS)) {
            return $this->otpAgotado($user);
        }

        if (! $user->otp_code || ! $user->otp_expires_at) {
            return response()->json([
                'message' => 'No existe un código OTP pendiente para este usuario. Solicita uno nuevo.',
            ], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'El código OTP ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        if (! hash_equals((string) $user->otp_code, (string) $request->otp_code)) {
            RateLimiter::hit($claveFallos, 900);

            if (RateLimiter::tooManyAttempts($claveFallos, self::OTP_MAX_FALLOS)) {
                return $this->otpAgotado($user);
            }

            $restantes = RateLimiter::remaining($claveFallos, self::OTP_MAX_FALLOS);

            return response()->json([
                'message' => "Código OTP incorrecto. Te quedan {$restantes} intento(s).",
            ], 422);
        }

        // Consumo atómico: si dos solicitudes llegan a la vez con el mismo código, solo una gana.
        $consumido = User::where('id', $user->id)
            ->where('otp_code', (string) $request->otp_code)
            ->where('otp_expires_at', '>', now())
            ->update(['otp_code' => null, 'otp_expires_at' => null]);

        if ($consumido !== 1) {
            return response()->json(['message' => 'Este código OTP ya fue utilizado. Solicita uno nuevo.'], 422);
        }

        RateLimiter::clear($claveFallos);

        $response = $this->respuestaConToken($user, 'OTP verificado correctamente.');

        if ($request->boolean('remember_device')) {
            $newDeviceId = Str::random(64);

            DB::table('trusted_devices')->insert([
                'user_id' => $user->id,
                'device_id' => $newDeviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $response->withCookie(
                cookie('trusted_device_id', $newDeviceId, 60 * 24 * 30)
            );
        }

        return $response;
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $clave = 'reenvio_otp:'.strtolower(trim($request->email));

        if (RateLimiter::tooManyAttempts($clave, self::REENVIO_MAX)) {
            return $this->demasiadosIntentos(RateLimiter::availableIn($clave));
        }

        RateLimiter::hit($clave, 900);

        $user = $this->buscarPorEmail($request->email);

        // Correo inexistente: misma respuesta que si existiera (no se revela quién tiene cuenta).
        if (! $user) {
            return response()->json(['message' => 'Si el correo está registrado, recibirás un nuevo código OTP.']);
        }

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        return $this->emitirOtp(
            $user,
            'Se envió un nuevo código OTP.',
            'Se generó un nuevo OTP en modo de desarrollo.',
            'No se pudo reenviar el código OTP. Revisa la configuración de correo.'
        );
    }

    // ---------------------------------------------------------------- paso 2b: rostro

    public function verifyFace(Request $request)
    {
        $request->validate([
            'login_token' => 'required|string|size:64',
            'descriptor' => 'required|array|size:128',
            'descriptor.*' => 'required|numeric|between:-5,5',
        ], [
            'login_token.required' => 'Primero ingresa tu correo y contraseña.',
            'login_token.size' => 'La verificación facial no es válida. Inicia sesión nuevamente.',
            'descriptor.size' => 'No se pudo leer el rostro correctamente. Intenta de nuevo.',
        ]);

        $claveTicket = $this->claveTicket((string) $request->login_token);
        $ticket = Cache::get($claveTicket);

        if (! is_array($ticket) || empty($ticket['user_id'])) {
            return response()->json([
                'match' => false,
                'message' => 'La verificación facial expiró. Inicia sesión nuevamente con tu correo y contraseña.',
            ], 422);
        }

        $user = User::with('rol:id,nombre,permisos,activo')->find($ticket['user_id']);

        if (! $user || ! $user->activo) {
            Cache::forget($claveTicket);

            return response()->json(['match' => false, 'message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        $claveBloqueo = 'rostro_fallos:'.$user->id;

        if (RateLimiter::tooManyAttempts($claveBloqueo, self::ROSTRO_BLOQUEO_FALLOS)) {
            Cache::forget($claveTicket);

            return $this->demasiadosIntentos(RateLimiter::availableIn($claveBloqueo), true);
        }

        // Intentos por ticket (incremento atómico: dos pestañas no suman intentos extra).
        $claveIntentos = $claveTicket.':intentos';
        Cache::add($claveIntentos, 0, now()->addMinutes($this->minutosTicket()));
        $intento = (int) Cache::increment($claveIntentos);
        $maximo = (int) config('app.face_max_attempts', 3);

        if ($intento > $maximo) {
            Cache::forget($claveTicket);

            return response()->json([
                'match' => false,
                'bloqueado' => true,
                'message' => 'Demasiados intentos fallidos con el rostro. Inicia sesión nuevamente o usa el código OTP.',
            ], 429);
        }

        $guardado = $user->face_descriptor;

        if (! $this->tieneRostroValido($user)) {
            Cache::forget($claveTicket);

            return response()->json([
                'match' => false,
                'message' => 'No tienes un rostro registrado. Usa el código OTP.',
            ], 422);
        }

        $distancia = $this->euclideanDistance($guardado, $request->descriptor);

        if ($distancia <= (float) config('app.face_threshold')) {
            // El ticket se consume: si otra solicitud ya lo usó, esta no entra.
            if (Cache::pull($claveTicket) === null) {
                return response()->json([
                    'match' => false,
                    'message' => 'Esta verificación ya fue utilizada. Inicia sesión nuevamente.',
                ], 422);
            }

            Cache::forget($claveIntentos);
            RateLimiter::clear($claveBloqueo);

            // La distancia NO se devuelve nunca: serviría para ir ajustando un rostro falso.
            return $this->respuestaConToken($user, 'Rostro reconocido.', ['match' => true]);
        }

        RateLimiter::hit($claveBloqueo, 900);
        $restantes = max(0, $maximo - $intento);

        return response()->json([
            'match' => false,
            'intentos_restantes' => $restantes,
            'message' => $restantes > 0
                ? "Rostro no reconocido. Te quedan {$restantes} intento(s) o puedes usar el código OTP."
                : 'Rostro no reconocido. Se agotaron los intentos: usa el código OTP.',
        ], 401);
    }

    /** Si el rostro no funciona, se pasa al OTP sin volver a escribir la contraseña. */
    public function usarOtpTrasRostro(Request $request)
    {
        $request->validate([
            'login_token' => 'required|string|size:64',
        ]);

        $claveTicket = $this->claveTicket((string) $request->login_token);
        $ticket = Cache::pull($claveTicket);

        if (! is_array($ticket) || empty($ticket['user_id'])) {
            return response()->json([
                'message' => 'La verificación expiró. Inicia sesión nuevamente con tu correo y contraseña.',
            ], 422);
        }

        $user = User::with('rol:id,nombre,permisos,activo')->find($ticket['user_id']);

        if (! $user || ! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        return $this->emitirOtp(
            $user,
            'Te enviamos un código OTP.',
            'OTP generado en modo de desarrollo.',
            'No se pudo enviar el código OTP. Revisa la configuración de correo.',
            ['skip_otp' => false, 'metodo' => 'otp']
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada',
        ]);
    }

    // ---------------------------------------------------------------- apoyo

    private function buscarPorEmail(string $email): ?User
    {
        return User::with('rol:id,nombre,permisos,activo')
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();
    }

    private function claveLogin(Request $request): string
    {
        return 'login_fallos:'.strtolower(trim((string) $request->input('email'))).'|'.$request->ip();
    }

    private function demasiadosIntentos(int $segundos, bool $rostro = false): JsonResponse
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        return response()->json([
            'message' => 'Demasiados intentos fallidos. Intenta nuevamente en '.$minutos.' minuto(s).',
            ...($rostro ? ['match' => false, 'bloqueado' => true] : []),
        ], 429);
    }

    private function otpAgotado(User $user): JsonResponse
    {
        // Con 5 fallos el código queda inválido: hay que pedir uno nuevo.
        $user->forceFill(['otp_code' => null, 'otp_expires_at' => null])->save();

        return response()->json([
            'message' => 'Demasiados intentos incorrectos. El código quedó anulado: solicita uno nuevo.',
        ], 429);
    }

    private function tieneRostroValido(User $user): bool
    {
        $descriptor = $user->face_descriptor;

        return is_array($descriptor) && count($descriptor) === 128;
    }

    private function minutosTicket(): int
    {
        return max(1, (int) config('app.face_ticket_minutes', 5));
    }

    private function claveTicket(string $ticket): string
    {
        return 'login_rostro:'.hash('sha256', $ticket);
    }

    /** Ticket de un solo uso que habilita la verificación facial después de validar la contraseña. */
    private function crearTicketRostro(User $user): string
    {
        $ticket = Str::random(64);

        Cache::put(
            $this->claveTicket($ticket),
            ['user_id' => $user->id],
            now()->addMinutes($this->minutosTicket())
        );

        return $ticket;
    }

    private function emitirOtp(
        User $user,
        string $mensajeEnviado,
        string $mensajeDebug,
        string $mensajeError,
        array $extra = []
    ): JsonResponse {
        try {
            $delivery = $this->otpService->issue($user);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $mensajeError], 503);
        }

        // Un código nuevo reinicia el conteo de intentos fallidos del anterior.
        RateLimiter::clear('otp_fallos:'.$user->id);

        return response()->json(array_filter([
            'message' => $delivery['mail_sent'] ? $mensajeEnviado : $mensajeDebug,
            'email' => $user->email,
            ...$extra,
            'debug_otp' => $delivery['debug_otp'] ?? null,
            'expires_in_minutes' => $delivery['expires_in_minutes'],
        ], static fn ($value) => $value !== null));
    }

    private function respuestaConToken(User $user, string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            ...$extra,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $this->formatUser($user),
        ]);
    }

    private function formatUser(User $user): array
    {
        $user->loadMissing('rol:id,nombre,permisos,activo');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'paterno' => $user->paterno,
            'materno' => $user->materno,
            'email' => $user->email,
            'celular' => $user->celular,
            'id_rol' => $user->id_rol,
            'rol' => [
                'id' => $user->rol?->id,
                'nombre' => $user->rol?->nombre,
            ],
            'permisos' => $user->permisos(),
            'activo' => $user->activo,
            'face_photo' => $user->face_photo,
            'created_at' => $user->created_at,
        ];
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $val) {
            $sum += ((float) $val - (float) ($b[$i] ?? 0)) ** 2;
        }

        return sqrt($sum);
    }
}
