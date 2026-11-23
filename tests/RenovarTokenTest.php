<?php

namespace EgoBytes\EgoDocs\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * En una aplicación de una sola página el token del script vence sin que la
 * página se recargue: el widget pide uno nuevo a esta ruta, con la sesión
 * del propio sistema.
 */
class RenovarTokenTest extends TestCase
{
    protected function usuario(): GenericUser
    {
        return new GenericUser(['id' => 15, 'name' => 'Ana']);
    }

    public function test_firma_un_token_nuevo_para_quien_tiene_sesion(): void
    {
        $respuesta = $this->actingAs($this->usuario(), 'admin')->getJson('/egodocs/token?guard=admin');

        $respuesta->assertOk();
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $datos = $this->verificar($respuesta->json('token'), self::SECRETO_ADMIN);
        $this->assertSame('15', $datos['sub']);
        $this->assertSame('pk_admin', $datos['aud']);
    }

    public function test_cada_guard_firma_con_el_secreto_de_su_widget(): void
    {
        $token = $this->actingAs($this->usuario(), 'mechanics')->getJson('/egodocs/token?guard=mechanics')->json('token');

        $this->assertSame('pk_mecanicos', $this->verificar($token, self::SECRETO_MECANICOS)['aud']);
    }

    public function test_sin_sesion_no_hay_token(): void
    {
        $this->getJson('/egodocs/token?guard=admin')->assertUnauthorized()->assertJson(['error' => 'sin_sesion']);
    }

    public function test_la_sesion_de_otro_guard_no_sirve(): void
    {
        // Con sesión de mecánico no se consigue un token del panel administrativo.
        $this->actingAs($this->usuario(), 'mechanics')->getJson('/egodocs/token?guard=admin')->assertUnauthorized();
    }

    public function test_un_guard_que_no_existe_o_sin_widget_responde_404(): void
    {
        $this->getJson('/egodocs/token?guard=inventado')->assertNotFound();
        $this->getJson('/egodocs/token')->assertNotFound();
        // `web` existe, pero no tiene widget.
        $this->actingAs($this->usuario(), 'web')->getJson('/egodocs/token?guard=web')->assertNotFound();
    }

    public function test_el_script_dice_donde_renovar_solo_si_hay_sesion(): void
    {
        auth()->shouldUse('admin');
        $this->assertStringNotContainsString('data-renovar', Blade::render('@egodocs'));

        $this->actingAs($this->usuario(), 'admin');
        $this->assertStringContainsString('data-renovar="/egodocs/token?guard=admin"', Blade::render('@egodocs'));
    }

    #[DefineEnvironment('sinRuta')]
    public function test_la_ruta_se_puede_apagar(): void
    {
        $this->actingAs($this->usuario(), 'admin');

        $this->assertStringNotContainsString('data-renovar', Blade::render('@egodocs'));
        $this->getJson('/egodocs/token?guard=admin')->assertNotFound();
    }

    protected function sinRuta($app): void
    {
        $app['config']->set('ego-docs.ruta_token', '');
    }
}
