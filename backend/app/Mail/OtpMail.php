<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;

    public ?string $userName;

    public int $expiresIn;

    public function __construct(string $otpCode, ?string $userName = null, int $expiresIn = 10)
    {
        $this->otpCode = $otpCode;
        $this->userName = $userName;
        $this->expiresIn = $expiresIn;
    }

    public function build()
    {
        return $this->subject('Código OTP de acceso - Sistema PROINPA')
            ->from(
                config('mail.from.address'),
                config('mail.from.name')
            )
            ->view('emails.otp');
    }
}
