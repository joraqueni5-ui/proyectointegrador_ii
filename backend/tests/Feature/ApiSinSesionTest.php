<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las rutas api/* responden siempre en JSON, aunque la petición no envíe "Accept: application/json".
 */
class ApiSinSesionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ruta_protegida_sin_sesion_y_sin_accept_responde_401_json(): void
    {
        $respuesta = $this->call('GET', '/api/profile'); // sin cabecera Accept

        $respuesta->assertStatus(401)->assertExactJson(['message' => 'No autenticado.']);
        $this->assertStringContainsString('application/json', $respuesta->headers->get('Content-Type'));
    }

    public function test_con_token_invalido_y_sin_accept_tambien_responde_401_json(): void
    {
        $respuesta = $this->call('GET', '/api/buscar', ['q' => 'juan'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer token-falso']);

        $respuesta->assertStatus(401)->assertJson(['message' => 'No autenticado.']);
    }

    public function test_un_error_de_validacion_sin_accept_responde_422_json_y_no_redirige(): void
    {
        $respuesta = $this->call('POST', '/api/login', []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_una_ruta_api_inexistente_sin_accept_responde_404_json(): void
    {
        $respuesta = $this->call('GET', '/api/no-existe');

        $respuesta->assertStatus(404);
        $this->assertStringContainsString('application/json', $respuesta->headers->get('Content-Type'));
    }
}
