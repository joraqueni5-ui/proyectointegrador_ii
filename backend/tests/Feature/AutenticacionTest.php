<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Login en dos pasos: contraseña + (OTP o rostro). El rostro REEMPLAZA al OTP y solo se acepta
 * con el ticket que entrega /login después de validar la contraseña.
 */
class AutenticacionTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private const CLAVE = 'Clave-Segura-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearRolesYUsuarios();
    }

    private function usuario(string $rol = 'Agrónomo'): User
    {
        return $this->usuarios[$rol]->fresh();
    }

    private function descriptor(float $base = 0.1): array
    {
        return array_map(fn ($i) => round($base + $i * 0.001, 4), range(0, 127));
    }

    private function conRostro(User $user, float $base = 0.1): User
    {
        $user->forceFill(['face_descriptor' => $this->descriptor($base)])->save();

        return $user->fresh();
    }

    private function login(string $email, string $password = self::CLAVE, array $extra = [])
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password] + $extra);
    }

    private function loginConRostro(User $user)
    {
        return $this->login($user->email, self::CLAVE, ['metodo' => 'rostro']);
    }

    private function otpDe(User $user): string
    {
        return (string) DB::table('users')->where('id', $user->id)->value('otp_code');
    }

    // ================================================================= contraseña

    public function test_login_correcto_envia_otp_y_no_entrega_token(): void
    {
        $u = $this->usuario();

        $r = $this->login($u->email)->assertOk();

        $this->assertArrayNotHasKey('token', $r->json());
        $this->assertSame('otp', $r->json('metodo'));
        $this->assertSame(6, strlen($this->otpDe($u)));
    }

    public function test_login_incorrecto_responde_igual_para_correo_inexistente_y_clave_mala(): void
    {
        $malaClave = $this->login($this->usuario()->email, 'otra-clave')->assertStatus(401);
        $noExiste = $this->login('nadie@prueba.test', self::CLAVE)->assertStatus(401);

        $this->assertSame($malaClave->json('message'), $noExiste->json('message'));
    }

    public function test_login_ignora_mayusculas_en_el_correo(): void
    {
        DB::table('users')->where('id', $this->usuario()->id)->update(['email' => 'Mayus@Prueba.Test']);

        $this->login('mayus@prueba.test')->assertOk();
        $this->login('MAYUS@PRUEBA.TEST')->assertOk();
    }

    public function test_se_bloquea_el_login_tras_5_fallos_y_un_exito_reinicia_el_conteo(): void
    {
        $email = $this->usuario()->email;

        for ($i = 0; $i < 3; $i++) {
            $this->login($email, 'mala')->assertStatus(401);
        }
        $this->login($email)->assertOk();            // un acierto reinicia el conteo

        for ($i = 0; $i < 5; $i++) {
            $this->login($email, 'mala')->assertStatus(401);
        }

        $r = $this->login($email)->assertStatus(429); // bloqueado aunque la clave sea la correcta
        $this->assertStringContainsString('minuto', $r->json('message'));
    }

    public function test_un_usuario_desactivado_no_puede_entrar(): void
    {
        $u = $this->usuario();
        $u->update(['activo' => false]);

        $this->login($u->email)->assertStatus(403);
    }

    public function test_un_usuario_desactivado_pierde_el_acceso_aunque_conserve_su_token(): void
    {
        $u = $this->usuario();
        $token = $u->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/profile')->assertOk();

        $u->update(['activo' => false]);
        $this->app['auth']->forgetGuards(); // en producción cada petición es nueva; aquí se reutiliza la app

        $this->withToken($token)->getJson('/api/profile')->assertStatus(403);
        $this->assertSame(0, $u->tokens()->count()); // el token se revocó
    }

    // ================================================================= OTP

    public function test_flujo_normal_con_otp_sigue_funcionando(): void
    {
        $u = $this->usuario();
        $this->login($u->email)->assertOk();

        $r = $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $this->otpDe($u)])->assertOk();

        $this->assertNotEmpty($r->json('token'));
        $this->assertSame($u->email, $r->json('user.email'));
    }

    public function test_el_otp_no_se_puede_usar_dos_veces(): void
    {
        $u = $this->usuario();
        $this->login($u->email);
        $codigo = $this->otpDe($u);

        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $codigo])->assertOk();
        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $codigo])->assertStatus(422);
        $this->assertSame('', $this->otpDe($u));
    }

    public function test_el_otp_expirado_se_rechaza(): void
    {
        $u = $this->usuario();
        $this->login($u->email);
        DB::table('users')->where('id', $u->id)->update(['otp_expires_at' => now()->subMinute()]);

        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $this->otpDe($u)])
            ->assertStatus(422)->assertJsonPath('message', 'El código OTP ha expirado. Solicita uno nuevo.');
    }

    public function test_5_otp_incorrectos_anulan_el_codigo(): void
    {
        $u = $this->usuario();
        $this->login($u->email);
        $bueno = $this->otpDe($u);
        $malo = $bueno === '111111' ? '222222' : '111111';

        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $malo])
            ->assertStatus(422)->assertJsonPath('message', 'Código OTP incorrecto. Te quedan 4 intento(s).');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $malo])->assertStatus(422);
        }
        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $malo])->assertStatus(429);

        // Aunque ahora se envíe el código correcto, ya está anulado.
        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $bueno])->assertStatus(429);
        $this->assertSame('', $this->otpDe($u));
    }

    public function test_un_otp_nuevo_reinicia_los_intentos(): void
    {
        $u = $this->usuario();
        $this->login($u->email);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => '000000']);
        }

        $this->postJson('/api/resend-otp', ['email' => $u->email])->assertOk();

        $this->postJson('/api/verify-otp', ['email' => $u->email, 'otp_code' => $this->otpDe($u)])->assertOk();
    }

    public function test_no_se_revela_si_un_correo_existe(): void
    {
        $this->postJson('/api/verify-otp', ['email' => 'nadie@prueba.test', 'otp_code' => '123456'])
            ->assertStatus(422)->assertJsonPath('message', 'Código OTP incorrecto.');
        $this->postJson('/api/resend-otp', ['email' => 'nadie@prueba.test'])->assertOk();
    }

    public function test_el_reenvio_de_otp_tiene_limite_por_correo_y_por_ip(): void
    {
        $email = $this->usuario()->email;

        // Límite por IP de la ruta: 3 por minuto.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/resend-otp', ['email' => $email])->assertOk();
        }
        $this->postJson('/api/resend-otp', ['email' => $email])->assertStatus(429);

        // Límite por correo (5 en 15 min) aunque cambie la IP: ya se usaron 3 de 5.
        foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/resend-otp', ['email' => $email])->assertOk();
        }
        $r = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->postJson('/api/resend-otp', ['email' => $email])->assertStatus(429);
        $this->assertStringContainsString('minuto', $r->json('message'));
    }

    public function test_el_dispositivo_de_confianza_sigue_entrando_sin_segundo_factor(): void
    {
        $u = $this->usuario();
        DB::table('trusted_devices')->insert(['user_id' => $u->id, 'device_id' => 'dispositivo-1', 'created_at' => now(), 'updated_at' => now()]);

        // call(): método, ruta, parámetros, cookies, archivos, variables del servidor.
        $r = $this->call('POST', '/api/login', ['email' => $u->email, 'password' => self::CLAVE], ['trusted_device_id' => 'dispositivo-1'], [], ['HTTP_ACCEPT' => 'application/json']);
        $r->assertOk();

        $this->assertTrue($r->json('skip_otp'));
        $this->assertNotEmpty($r->json('token'));
    }

    // ================================================================= rostro como segundo factor

    public function test_verify_face_ya_no_acepta_solo_correo_y_descriptor(): void
    {
        $u = $this->conRostro($this->usuario());

        // Contrato anterior: correo + descriptor -> ya no entrega token.
        $r = $this->postJson('/api/verify-face', ['email' => $u->email, 'descriptor' => $this->descriptor()])->assertStatus(422);

        $this->assertArrayNotHasKey('token', $r->json());
        $r->assertJsonValidationErrors('login_token');
    }

    public function test_el_rostro_exige_primero_la_contrasena(): void
    {
        $u = $this->conRostro($this->usuario());

        $r = $this->login($u->email, 'mala', ['metodo' => 'rostro'])->assertStatus(401);

        $this->assertArrayNotHasKey('login_token', $r->json());
    }

    public function test_con_la_contrasena_correcta_se_entrega_un_ticket_y_no_se_envia_otp(): void
    {
        $u = $this->conRostro($this->usuario());

        $r = $this->loginConRostro($u)->assertOk();

        $this->assertTrue($r->json('requiere_rostro'));
        $this->assertSame(64, strlen($r->json('login_token')));
        $this->assertArrayNotHasKey('token', $r->json());
        $this->assertSame('', $this->otpDe($u)); // el rostro reemplaza al OTP
    }

    public function test_rostro_correcto_da_acceso_y_nunca_devuelve_la_distancia(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');

        $r = $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertOk();

        $this->assertTrue($r->json('match'));
        $this->assertNotEmpty($r->json('token'));
        $this->assertStringNotContainsStringIgnoringCase('distan', $r->getContent());
    }

    public function test_el_ticket_es_de_un_solo_uso(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');

        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertOk();
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertStatus(422);
    }

    public function test_un_rostro_distinto_se_rechaza_sin_exponer_la_distancia_y_con_intentos_limitados(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');
        $otro = $this->descriptor(0.9);

        $r1 = $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $otro])->assertStatus(401);
        $this->assertFalse($r1->json('match'));
        $this->assertSame(2, $r1->json('intentos_restantes'));
        $this->assertStringNotContainsStringIgnoringCase('distan', $r1->getContent());

        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $otro])->assertStatus(401);
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $otro])->assertStatus(401);

        // 4.º intento: bloqueado, y el ticket queda invalidado aunque ahora el rostro sea el correcto.
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $otro])->assertStatus(429);
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertStatus(422);
    }

    public function test_sin_rostro_registrado_se_usa_el_otp(): void
    {
        $u = $this->usuario(); // sin rostro

        $r = $this->loginConRostro($u)->assertOk();

        $this->assertSame('otp', $r->json('metodo'));
        $this->assertFalse($r->json('rostro_disponible'));
        $this->assertSame(6, strlen($this->otpDe($u)));
    }

    public function test_si_el_rostro_falla_se_puede_pasar_al_otp_sin_repetir_la_contrasena(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');

        $r = $this->postJson('/api/verify-face/usar-otp', ['login_token' => $ticket])->assertOk();

        $this->assertSame('otp', $r->json('metodo'));
        $this->assertSame(6, strlen($this->otpDe($u)));
        // El ticket ya se consumió: el rostro ya no sirve.
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertStatus(422);
    }

    public function test_un_usuario_desactivado_despues_de_recibir_el_ticket_no_entra(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');
        $u->update(['activo' => false]);

        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => $this->descriptor()])->assertStatus(403);
    }

    public function test_valida_el_formato_del_descriptor(): void
    {
        $u = $this->conRostro($this->usuario());
        $ticket = $this->loginConRostro($u)->json('login_token');

        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => array_slice($this->descriptor(), 0, 100)])
            ->assertStatus(422)->assertJsonValidationErrors('descriptor');
        $this->postJson('/api/verify-face', ['login_token' => $ticket, 'descriptor' => array_fill(0, 128, 'abc')])
            ->assertStatus(422);
        $this->postJson('/api/verify-face', ['login_token' => 'corto', 'descriptor' => $this->descriptor()])
            ->assertStatus(422)->assertJsonValidationErrors('login_token');
    }

    public function test_un_ticket_inventado_no_sirve(): void
    {
        $this->conRostro($this->usuario());

        $this->postJson('/api/verify-face', ['login_token' => str_repeat('a', 64), 'descriptor' => $this->descriptor()])
            ->assertStatus(422);
    }

    public function test_la_contrasena_nueva_se_guarda_cifrada_y_permite_entrar(): void
    {
        $u = $this->usuario();
        $this->assertTrue(Hash::check(self::CLAVE, $u->password));
        $this->assertNotSame(self::CLAVE, $u->password);
    }
}
