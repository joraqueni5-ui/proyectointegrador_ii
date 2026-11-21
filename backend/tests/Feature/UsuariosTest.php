<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Usuarios: contraseña segura, celular boliviano, correo único sin importar mayúsculas, CI con
 * extensión, y que nadie se desactive ni se cambie el rol a sí mismo.
 */
class UsuariosTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearRolesYUsuarios();
    }

    private function rolId(string $nombre): int
    {
        return (int) DB::table('roles')->where('nombre', $nombre)->value('id');
    }

    private function payload(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Marcelo', 'paterno' => 'Quispe', 'materno' => 'Mamani', 'ci' => '6543210', 'ci_extension' => 'LP',
            'sexo' => 'Masculino', 'email' => 'marcelo.quispe@proinpa.test', 'celular' => '71234567',
            'cargo' => 'Técnico de campo', 'area' => 'Investigación', 'oficina_regional' => 'La Paz',
            'password' => 'Clave-Nueva-9!', 'id_rol' => $this->rolId('Técnico'),
        ];
    }

    private function crear(array $cambios = [], string $rol = 'Administrador')
    {
        Sanctum::actingAs($this->usuarios[$rol]);

        return $this->postJson('/api/users', $this->payload($cambios));
    }

    // ------------------------------------------------------------------ alta válida y permisos

    public function test_un_administrador_crea_un_usuario_valido(): void
    {
        $r = $this->crear()->assertCreated();

        $this->assertSame('marcelo.quispe@proinpa.test', $r->json('email'));
        $this->assertTrue(Hash::check('Clave-Nueva-9!', User::find($r->json('id'))->password));
    }

    public function test_sin_permiso_de_gestion_de_usuarios_responde_403_antes_de_validar(): void
    {
        foreach (['Técnico', 'Agrónomo', 'Agricultor', 'Comercial'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);
            $this->postJson('/api/users', [])->assertForbidden();
        }
    }

    // ------------------------------------------------------------------ contraseña

    public function test_la_contrasena_debe_ser_segura(): void
    {
        foreach (['corta1!A' => false, 'sinnumeros!A' => true, 'SINMINUSCULA1!' => true, 'sinmayuscula1!' => true, 'SinSimbolo123' => true, 'Ab1!' => true] as $clave => $debeFallar) {
            $r = $this->crear(['password' => $clave, 'email' => 'p'.md5($clave).'@proinpa.test', 'ci' => (string) random_int(1000000, 9999999)]);

            if ($debeFallar) {
                $r->assertStatus(422)->assertJsonValidationErrors('password');
                $this->assertStringContainsString('mayúscula', $r->json('errors.password.0'));
            }
        }

        // Válida: 8+ caracteres con mayúscula, minúscula, número y símbolo
        $this->crear(['password' => 'Abcdef1!'])->assertCreated();
    }

    public function test_la_contrasena_no_puede_superar_72_caracteres(): void
    {
        $this->crear(['password' => str_repeat('Aa1!', 19)])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // ------------------------------------------------------------------ celular boliviano

    public function test_el_celular_debe_tener_8_digitos_y_empezar_con_6_o_7(): void
    {
        foreach (['51234567', '81234567', '7123456', '712345678', 'abc12345'] as $malo) {
            $this->crear(['celular' => $malo, 'email' => 'c'.$malo.'@proinpa.test', 'ci' => (string) random_int(1000000, 9999999)])
                ->assertStatus(422)->assertJsonValidationErrors('celular');
        }

        $this->crear(['celular' => '61234567', 'email' => 'seis@proinpa.test', 'ci' => '1111111'])->assertCreated();
        $this->crear(['celular' => '71234567', 'email' => 'siete@proinpa.test', 'ci' => '2222222'])->assertCreated();
    }

    public function test_el_celular_es_obligatorio(): void
    {
        $p = $this->payload();
        unset($p['celular']);
        Sanctum::actingAs($this->usuarios['Administrador']);

        $this->postJson('/api/users', $p)->assertStatus(422)->assertJsonValidationErrors('celular');
    }

    // ------------------------------------------------------------------ correo y CI

    public function test_el_correo_es_unico_sin_importar_mayusculas(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['email' => 'MARCELO.QUISPE@PROINPA.TEST', 'ci' => '7654321'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        // También frente a un correo guardado con mayúsculas (datos antiguos).
        DB::table('users')->where('id', $this->usuarios['Técnico']->id)->update(['email' => 'Antiguo@Proinpa.Test']);
        $this->crear(['email' => 'antiguo@proinpa.test', 'ci' => '8765432'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_el_ci_no_se_puede_repetir_y_exige_extension(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['email' => 'otro@proinpa.test'])->assertStatus(422)->assertJsonValidationErrors('ci');
        $this->crear(['email' => 'otro2@proinpa.test', 'ci' => '9999999', 'ci_extension' => null])
            ->assertStatus(422)->assertJsonValidationErrors('ci_extension');
        $this->crear(['email' => 'otro3@proinpa.test', 'ci' => '9999998', 'ci_extension' => 'XX'])
            ->assertStatus(422)->assertJsonValidationErrors('ci_extension');
    }

    // ------------------------------------------------------------------ edición

    public function test_editar_aplica_las_mismas_reglas(): void
    {
        $id = $this->crear()->json('id');
        Sanctum::actingAs($this->usuarios['Administrador']);

        $this->putJson("/api/users/{$id}", ['celular' => '51234567'])->assertStatus(422)->assertJsonValidationErrors('celular');
        $this->putJson("/api/users/{$id}", ['password' => 'debil'])->assertStatus(422)->assertJsonValidationErrors('password');
        // Conservar su propio correo (aunque cambie la capitalización) no es un duplicado.
        $this->putJson("/api/users/{$id}", ['email' => 'Marcelo.Quispe@Proinpa.test'])->assertOk();
        $this->putJson("/api/users/{$id}", ['celular' => '69999999', 'password' => 'Otra-Clave-7?'])->assertOk();

        $this->assertTrue(Hash::check('Otra-Clave-7?', User::find($id)->password));
    }

    // ------------------------------------------------------------------ nadie se quita nada a sí mismo

    public function test_nadie_puede_desactivarse_a_si_mismo(): void
    {
        $yo = $this->usuarios['Administrador'];
        Sanctum::actingAs($yo);

        $this->deleteJson("/api/users/{$yo->id}")->assertForbidden();
        $this->putJson("/api/users/{$yo->id}", ['activo' => false])->assertForbidden();
        $this->assertTrue((bool) $yo->fresh()->activo);
    }

    public function test_nadie_puede_cambiarse_su_propio_rol(): void
    {
        $yo = $this->usuarios['Administrador'];
        Sanctum::actingAs($yo);

        $this->putJson("/api/users/{$yo->id}", ['id_rol' => $this->rolId('Técnico')])
            ->assertForbidden()->assertJsonPath('message', 'No puedes cambiar tu propio rol. Pídeselo a otro administrador.');

        $this->assertSame($this->rolId('Administrador'), (int) $yo->fresh()->id_rol);
    }

    public function test_si_puede_cambiar_el_rol_de_otro_usuario(): void
    {
        Sanctum::actingAs($this->usuarios['Administrador']);
        $otro = $this->usuarios['Técnico'];

        $this->putJson("/api/users/{$otro->id}", ['id_rol' => $this->rolId('Agrónomo')])->assertOk();

        $this->assertSame($this->rolId('Agrónomo'), (int) $otro->fresh()->id_rol);
    }

    public function test_un_administrador_no_asigna_el_rol_superadmin(): void
    {
        $this->crear(['id_rol' => $this->rolId('Superadmin')])->assertForbidden();
    }

    public function test_al_desactivar_a_otro_se_cierran_sus_sesiones(): void
    {
        $otro = $this->usuarios['Técnico'];
        $otro->createToken('sesion-1');
        $otro->createToken('sesion-2');
        $this->assertSame(2, $otro->tokens()->count());

        Sanctum::actingAs($this->usuarios['Administrador']);
        $this->deleteJson("/api/users/{$otro->id}")->assertOk();

        $this->assertFalse((bool) $otro->fresh()->activo);
        $this->assertSame(0, $otro->tokens()->count());
    }

    public function test_tampoco_se_desactiva_al_ultimo_administrador(): void
    {
        // Solo hay un Administrador activo: lo intenta desactivar el Superadmin.
        Sanctum::actingAs($this->usuarios['Superadmin']);

        $this->deleteJson('/api/users/'.$this->usuarios['Administrador']->id)->assertForbidden();
    }
}
