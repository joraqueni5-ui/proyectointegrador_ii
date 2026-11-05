<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Código OTP - Sistema PROINPA</title>
</head>

<body style="margin:0; padding:0; background:#f4f7f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7f6; padding:30px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg, #0b5d3b, #15803d); padding:30px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; color:#ffffff;">Sistema PROINPA</h1>
                            <p style="margin:8px 0 0; color:#dcfce7; font-size:15px;">
                                Gestión Agrícola Inteligente
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 34px;">
                            <h2 style="margin:0 0 16px; color:#111827; font-size:26px; text-align:center;">
                                Verificación de inicio de sesión
                            </h2>

                            <p style="margin:0 0 18px; font-size:16px; line-height:1.6; color:#4b5563; text-align:center;">
                                Hola{{ !empty($userName) ? ', ' . $userName : '' }}.
                                Hemos recibido una solicitud de acceso a tu cuenta.
                            </p>

                            <p style="margin:0 0 18px; font-size:16px; line-height:1.6; color:#4b5563; text-align:center;">
                                Usa el siguiente código de seguridad OTP para completar el inicio de sesión:
                            </p>

                            <div style="margin:28px auto; text-align:center;">
                                <div style="display:inline-block; background:#eff6ff; color:#1d4ed8; padding:18px 30px; border-radius:16px; font-size:44px; font-weight:800; letter-spacing:6px; border:2px dashed #93c5fd;">
                                    {{ $otpCode }}
                                </div>
                            </div>

                            <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#4b5563; text-align:center;">
                                Este código expirará en <strong>{{ $expiresIn ?? 10 }} minutos</strong>.
                            </p>

                            <div style="margin-top:26px; padding:16px 18px; background:#fef3c7; border-radius:12px; color:#92400e; font-size:14px; line-height:1.6;">
                                <strong>Importante:</strong> Si no solicitaste este acceso, ignora este mensaje.
                                No compartas este código con nadie.
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc; padding:22px 30px; text-align:center; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:14px; color:#6b7280;">
                                Fundación PROINPA · Sistema de Gestión Agrícola Inteligente
                            </p>
                            <p style="margin:8px 0 0; font-size:13px; color:#9ca3af;">
                                Este es un mensaje automático, por favor no respondas a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>