<?php

namespace EgoBytes\EgoDocs\Tests;

use EgoBytes\EgoDocs\EgoDocs;
use EgoBytes\EgoDocs\EgoDocsServiceProvider;
use Orchestra\Testbench\TestCase as Base;

abstract class TestCase extends Base
{
    public const SECRETO_ADMIN = 'sk_secreto_del_admin_para_pruebas_0123456789';

    public const SECRETO_MECANICOS = 'sk_secreto_de_mecanicos_para_pruebas_98765';

    protected function getPackageProviders($app): array
    {
        return [EgoDocsServiceProvider::class];
    }

    /**
     * Un sistema como Blackbox: un panel administrativo y una app de
     * mecánicos, cada uno con su guard y su widget.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.guards.mechanics', ['driver' => 'session', 'provider' => 'users']);

        // La ruta para renovar el token pasa por el grupo `web`, que cifra la sesión.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        $app['config']->set('ego-docs.url', 'https://docs.ejemplo.com/');
        $app['config']->set('ego-docs.widgets', [
            'admin' => ['clave' => 'pk_admin', 'secreto' => self::SECRETO_ADMIN],
            'mechanics' => ['clave' => 'pk_mecanicos', 'secreto' => self::SECRETO_MECANICOS],
        ]);
    }

    protected function tearDown(): void
    {
        // Es estado estático: que no pase de una prueba a otra.
        EgoDocs::olvidar();

        parent::tearDown();
    }

    /**
     * Verifica un token como lo hace EGO docs y devuelve lo que dice. Falla
     * la prueba si la firma no corresponde a ese secreto.
     *
     * @return array<string, mixed>
     */
    protected function verificar(string $token, string $secreto): array
    {
        [$cabecera, $carga, $firma] = explode('.', $token);

        $esperada = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$cabecera}.{$carga}", $secreto, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($esperada, $firma), 'La firma no corresponde a ese secreto.');

        $decodificar = fn (string $parte) => json_decode(base64_decode(strtr($parte, '-_', '+/')), true);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $decodificar($cabecera));

        return $decodificar($carga);
    }

    protected function tokenDe(string $html): ?string
    {
        return preg_match('/data-token="([^"]+)"/', $html, $m) ? $m[1] : null;
    }
}
