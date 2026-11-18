<?php

namespace EgoBytes\EgoDocs\Tests;

use EgoBytes\EgoDocs\EgoDocs;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class SincronizarAccesosTest extends TestCase
{
    protected function responder(int $creados = 1): void
    {
        Http::fake(['docs.ejemplo.com/*' => Http::response(['creados' => $creados, 'actualizados' => 0, 'borrados' => 0])]);
    }

    /** @return array<string, Request> por clave de widget */
    protected function enviadas(): array
    {
        return collect(Http::recorded())->map(fn ($par) => $par[0])->keyBy(fn (Request $p) => $p->header('X-Ego-Key')[0])->all();
    }

    public function test_manda_el_catalogo_de_cada_guard_a_su_widget(): void
    {
        Route::get('/admin/ventas', fn () => '')->name('admin::sales.index');
        app('router')->getRoutes()->refreshNameLookups();

        EgoDocs::accesosCon('admin', fn () => [
            ['clave' => 'admin::sales.index', 'titulo' => 'Ventas', 'descripcion' => 'Todas las ventas', 'roles' => ['Sales Representative']],
        ]);
        config(['ego-docs.accesos' => ['mechanics' => [['clave' => 'ordenes', 'ruta' => '/mechanics/orders', 'titulo' => 'Mis órdenes']]]]);
        $this->responder();

        $this->artisan('egodocs:accesos')
            ->expectsOutputToContain('widget «admin»: 1 creados')
            ->expectsOutputToContain('widget «mechanics»: 1 creados')
            ->assertSuccessful();

        $enviadas = $this->enviadas();
        $admin = $enviadas['pk_admin'];

        $this->assertSame('PUT', $admin->method());
        $this->assertSame('https://docs.ejemplo.com/api/widget/accesos', $admin->url());
        $this->assertSame('Bearer '.self::SECRETO_ADMIN, $admin->header('Authorization')[0]);
        // La ruta sale del nombre de la ruta de Laravel.
        $this->assertSame([[
            'clave' => 'admin::sales.index',
            'ruta' => '/admin/ventas',
            'titulo' => 'Ventas',
            'descripcion' => 'Todas las ventas',
            'roles' => ['Sales Representative'],
        ]], $admin['accesos']);

        $this->assertSame('Bearer '.self::SECRETO_MECANICOS, $enviadas['pk_mecanicos']->header('Authorization')[0]);
    }

    public function test_un_guard_sin_accesos_manda_la_lista_vacia(): void
    {
        // Así se borra lo que el sistema había mandado antes.
        $this->responder(0);

        $this->artisan('egodocs:accesos', ['--guard' => ['mechanics']])->assertSuccessful();

        $this->assertSame([], $this->enviadas()['pk_mecanicos']['accesos']);
    }

    public function test_puede_limitarse_a_un_guard(): void
    {
        $this->responder();

        $this->artisan('egodocs:accesos', ['--guard' => ['admin']])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertArrayHasKey('pk_admin', $this->enviadas());
    }

    public function test_simular_no_manda_nada(): void
    {
        config(['ego-docs.accesos' => ['admin' => [['clave' => 'a', 'ruta' => '/a', 'titulo' => 'A', 'roles' => ['X', 'Y']]]]]);
        Http::fake();

        $this->artisan('egodocs:accesos', ['--simular' => true])
            ->expectsOutputToContain('widget «admin»: 1 accesos')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_avisa_si_un_acceso_no_tiene_ruta_ni_es_una_ruta_de_laravel(): void
    {
        config(['ego-docs.accesos' => ['admin' => [['clave' => 'no.existe', 'titulo' => 'X']]]]);
        $this->responder();

        $this->artisan('egodocs:accesos', ['--guard' => ['admin']])
            ->expectsOutputToContain('no.existe')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_muestra_lo_que_rechazo_ego_docs(): void
    {
        config(['ego-docs.accesos' => ['admin' => [['clave' => 'mal', 'ruta' => 'https://otro.com', 'titulo' => 'Mal']]]]);
        Http::fake(['*' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['accesos.0.ruta' => ['El formato de ruta no es válido.']],
        ], 422)]);

        $this->artisan('egodocs:accesos', ['--guard' => ['admin']])
            ->expectsOutputToContain('EGO docs respondió 422')
            ->expectsOutputToContain('accesos.0.ruta: El formato de ruta no es válido.')
            ->assertFailed();
    }

    public function test_sin_configurar_falla_con_un_mensaje_claro(): void
    {
        config(['ego-docs.widgets' => []]);

        $this->artisan('egodocs:accesos')->expectsOutputToContain('EGODOCS_URL')->assertFailed();
    }

    public function test_un_widget_sin_secreto_se_salta_avisando(): void
    {
        config(['ego-docs.widgets.admin.secreto' => null]);
        $this->responder();

        $this->artisan('egodocs:accesos')
            ->expectsOutputToContain('widget «admin»: falta el secreto.')
            ->assertFailed();

        $this->assertSame(['pk_mecanicos'], array_keys($this->enviadas()));
    }
}
