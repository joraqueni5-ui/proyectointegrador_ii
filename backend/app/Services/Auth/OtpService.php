<?php

namespace App\Services\Auth;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpService
{
    /**
     * Genera, persiste y entrega un nuevo OTP.
     *
     * En desarrollo local puede devolver debug_otp para permitir pruebas sin
     * depender de un servidor SMTP. Nunca expone el código fuera de local.
     */
    public function issue(User $user): array
    {
        $expiresIn = max(1, (int) config('otp.expires_minutes', 15));
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes($expiresIn),
        ])->save();

        $mailSent = false;

        try {
            Mail::to($user->email)->send(
                new OtpMail($otp, $this->displayName($user), $expiresIn)
            );

            $mailSent = ! in_array(config('mail.default'), ['log', 'array'], true);
        } catch (Throwable $exception) {
            Log::warning('No se pudo entregar el OTP por correo.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            if (! $this->debugEnabled()) {
                throw $exception;
            }
        }

        $result = [
            'mail_sent' => $mailSent,
            'expires_in_minutes' => $expiresIn,
        ];

        if ($this->debugEnabled()) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    public function debugEnabled(): bool
    {
        return app()->environment('local')
            && (bool) config('app.debug')
            && (bool) config('otp.debug', false);
    }

    private function displayName(User $user): ?string
    {
        $name = trim(implode(' ', array_filter([
            $user->name ?? null,
            $user->paterno ?? null,
        ])));

        return $name !== '' ? $name : null;
    }
}
