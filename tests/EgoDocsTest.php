<?php

namespace EgoBytes\EgoDocs\Tests;

use EgoBytes\EgoDocs\EgoDocs;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;

class EgoDocsTest extends TestCase
{
    protected function usuario(array $datos = []): GenericUser
    {
        return new GenericUser(['id' => 15, 'name' => 'Ana', ...$datos]);
    }

    /** Lo que deja el middleware `auth:{guard}` en una ruta protegida. */
    protected function enArea(string $guard, ?GenericUser $usuario = null): void
    {
        $this->actingAs($usuario ?? $this->usuario(), $guard);
        auth()->shouldUse($guard);
    }

    public function test_cada_area_muestra_el_widget_de_su_guard(): void
    {
        $this->enArea('admin');
        $enAdmin = Blade::render('@egodocs');

        $this->enArea('mechanics');
        $enMecanicos = Blade::render('@egodocs');

        $this->assertStringContainsString('data-clave="pk_admin"', $enAdmin);
        $this->assertStringContainsString('data-clave="pk_mecanicos"', $enMecanicos);

        // Cada token, firmado con el secreto de su widget y atado a él.
        $this->assertSame('pk_admin', $this->verificar($this->tokenDe($enAdmin), self::SECRETO_ADMIN)['aud']);
        $this->assertSame('pk_mecanicos', $this->verificar($this->tokenDe($enMecanicos), self::SECRETO_MECANICOS)['aud']);
    }

    public function test_el_script_tiene_la_forma_esperada(): void
    {
        $this->enArea('admin');
        app()->setLocale('es');

        $html = Blade::render('@egodocs');

        $this->assertMatchesRegularExpression(
            '#^<script src="https://docs\.ejemplo\.com/widget/egodocs\.js" data-clave="pk_admin" data-token="[^"]+" data-idioma="es" defer></script>$#',
            $html,
        );
    }

    public function test_un_guard_sin_widget_no_imprime_nada(): void
    {
        // El login, la tienda: el guard por defecto no tiene bot.
        auth()->shouldUse('web');

        $this->assertSame('', Blade::render('@egodocs'));
    }

    public function test_el_guard_se_puede_fijar_en_la_directiva(): void
    {
        auth()->shouldUse('admin');

        $this->assertStringContainsString('data-clave="pk_mecanicos"', Blade::render("@egodocs('mechanics')"));
    }

    public function test_un_solo_widget_sale_en_cualquier_guard(): void
    {
        config(['ego-docs.widgets' => ['*' => ['clave' => 'pk_unico', 'secreto' => 'sk_unico']]]);

        auth()->shouldUse('web');
        $this->assertStringContainsString('data-clave="pk_unico"', Blade::render('@egodocs'));

        $this->enArea('mechanics');
        $this->assertSame('15', $this->verificar($this->tokenDe(Blade::render('@egodocs')), 'sk_unico')['sub']);
    }

    public function test_sin_sesion_va_sin_token(): void
    {
        auth()->shouldUse('admin');

        $html = Blade::render('@egodocs');

        $this->assertStringContainsString('data-clave="pk_admin"', $html);
        $this->assertNull($this->tokenDe($html));
    }

    public function test_sin_url_o_sin_widgets_no_imprime_nada(): void
    {
        $this->enArea('admin');

        config(['ego-docs.url' => null]);
        $this->assertSame('', Blade::render('@egodocs'));

        config(['ego-docs.url' => 'https://docs.ejemplo.com', 'ego-docs.widgets' => []]);
        $this->assertSame('', Blade::render('@egodocs'));
    }

    public function test_lo_que_viene_del_usuario_va_escapado(): void
    {
        config(['ego-docs.widgets.admin.clave' => 'pk_"><script>']);
        $this->enArea('admin', $this->usuario(['name' => '"><b>Ana</b>']));

        $html = Blade::render('@egodocs');

        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertStringContainsString('data-clave="pk_&quot;&gt;&lt;script&gt;"', $html);
    }

    public function test_por_defecto_manda_el_id_y_el_nombre(): void
    {
        $this->enArea('admin');

        $datos = $this->verificar(EgoDocs::token(), self::SECRETO_ADMIN);

        $this->assertSame('15', $datos['sub']);
        $this->assertSame('Ana', $datos['nombre']);
        $this->assertArrayNotHasKey('roles', $datos);
    }

    public function test_la_identidad_se_define_por_guard(): void
    {
        EgoDocs::identificarCon(fn ($usuario, string $guard) => [
            'sub' => $usuario->id,
            'roles' => $guard === 'admin' ? ['Sales Representative'] : [],
            'locale' => 'en',
        ]);

        $this->enArea('admin');
        $admin = $this->verificar(EgoDocs::token(), self::SECRETO_ADMIN);

        $this->enArea('mechanics');
        $mecanico = $this->verificar(EgoDocs::token(), self::SECRETO_MECANICOS);

        $this->assertSame(['Sales Representative'], $admin['roles']);
        $this->assertSame([], $mecanico['roles']);
        $this->assertSame('en', $admin['locale']);
    }

    public function test_el_token_vence_segun_la_vigencia(): void
    {
        config(['ego-docs.vigencia' => 600]);
        $this->enArea('admin');

        $datos = $this->verificar(EgoDocs::token(), self::SECRETO_ADMIN);

        $this->assertEqualsWithDelta(time(), $datos['iat'], 5);
        $this->assertSame(600, $datos['exp'] - $datos['iat']);
    }

    public function test_sin_secreto_no_hay_token(): void
    {
        config(['ego-docs.widgets.admin.secreto' => null]);
        $this->enArea('admin');

        $this->assertNull(EgoDocs::token());
        $this->assertStringContainsString('data-clave="pk_admin"', Blade::render('@egodocs'));
    }

    public function test_los_accesos_registrados_le_ganan_a_la_configuracion(): void
    {
        config(['ego-docs.accesos' => ['admin' => [['clave' => 'de.config', 'ruta' => '/c', 'titulo' => 'C']]]]);

        $this->assertSame('de.config', EgoDocs::accesos('admin')[0]['clave']);

        EgoDocs::accesosCon('admin', fn () => [['clave' => 'del.menu', 'ruta' => '/m', 'titulo' => 'M']]);

        $this->assertSame('del.menu', EgoDocs::accesos('admin')[0]['clave']);
        $this->assertSame([], EgoDocs::accesos('mechanics'));
    }

    public function test_una_lista_sin_guard_es_la_del_widget_unico(): void
    {
        config(['ego-docs.accesos' => [['clave' => 'unica', 'ruta' => '/u', 'titulo' => 'U']]]);

        $this->assertSame('unica', EgoDocs::accesos(EgoDocs::CUALQUIERA)[0]['clave']);
        $this->assertSame([], EgoDocs::accesos('admin'));
    }

    public function test_la_configuracion_publicada_lee_las_variables_de_siempre(): void
    {
        putenv('EGODOCS_CLAVE=pk_env');
        putenv('EGODOCS_SECRETO=sk_env');

        try {
            $config = require __DIR__.'/../config/ego-docs.php';
        } finally {
            putenv('EGODOCS_CLAVE');
            putenv('EGODOCS_SECRETO');
        }

        $this->assertSame(['*' => ['clave' => 'pk_env', 'secreto' => 'sk_env']], $config['widgets']);
    }

    public function test_sin_variables_no_queda_ningun_widget(): void
    {
        $config = require __DIR__.'/../config/ego-docs.php';

        $this->assertSame([], $config['widgets']);
    }
}
