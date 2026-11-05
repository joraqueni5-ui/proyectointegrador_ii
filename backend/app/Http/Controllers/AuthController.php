<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const MENSAJE_CUENTA_INACTIVA = 'Tu cuenta está desactivada. Contacta al administrador.';

    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->buscarPorEmail($request->email);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

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

        return $this->emitirOtp(
            $user,
            'OTP enviado correctamente.',
            'OTP generado en modo de desarrollo.',
            'No se pudo enviar el código OTP. Revisa la configuración de correo.',
            ['skip_otp' => false]
        );
    }

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

        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_INACTIVA], 403);
        }

        if (! $user->otp_code || ! $user->otp_expires_at) {
            return response()->json([
                'message' => 'No existe un código OTP pendiente para este usuario.',
            ], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'El código OTP ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        if (! hash_equals((string) $user->otp_code, (string) $request->otp_code)) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 422);
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

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

        $user = $this->buscarPorEmail($request->email);

        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
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

    public function verifyFace(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'descriptor' => 'required|array|size:128',
            'descriptor.*' => 'numeric',
        ]);

        $user = $this->buscarPorEmail($request->email);

        if (! $user || ! $user->face_descriptor) {
            return response()->json([
                'match' => false,
                'message' => 'Este usuario no tiene datos faciales registrados.',
            ], 404);
        }

        if (! $user->activo) {
            return response()->json([
                'match' => false,
                'message' => 'Cuenta desactivada.',
            ], 403);
        }

        $stored = $user->face_descriptor;

        if (! is_array($stored) || count($stored) !== 128) {
            return response()->json([
                'match' => false,
                'message' => 'Los datos faciales registrados no son válidos.',
            ], 422);
        }

        $distance = $this->euclideanDistance($stored, $request->descriptor);

        if ($distance <= (float) config('app.face_threshold')) {
            return $this->respuestaConToken($user, 'Rostro reconocido.', [
                'match' => true,
                'distance' => round($distance, 4),
            ]);
        }

        return response()->json([
            'match' => false,
            'distance' => round($distance, 4),
            'message' => 'Rostro no reconocido. Usa usuario y contraseña.',
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada',
        ]);
    }

    private function buscarPorEmail(string $email): ?User
    {
        return User::with('rol:id,nombre,permisos,activo')
            ->where('email', strtolower(trim($email)))
            ->first();
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
