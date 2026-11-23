<?php

namespace EgoBytes\EgoDocs;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

/**
 * Lo que un sistema Laravel necesita para mostrar el asistente de EGO docs.
 *
 * En el layout:
 *
 *   @egodocs                  el widget del guard de la página actual
 *   @egodocs('mechanics')     el de un guard en particular
 *
 * Y, si el sistema tiene roles, una vez en AppServiceProvider::boot():
 *
 *   EgoDocs::identificarCon(fn ($usuario, string $guard) => [
 *       'sub' => $usuario->id,
 *       'nombre' => $usuario->name,
 *       'roles' => $usuario->getRoleNames()->all(),
 *   ]);
 */
class EgoDocs
{
    /** El widget de un sistema que tiene uno solo. */
    public const CUALQUIERA = '*';

    /** @var (Closure(Authenticatable, string): array<string, mixed>)|null */
    protected static ?Closure $identidad = null;

    /** @var array<string, Closure(): array<int, array<string, mixed>>> */
    protected static array $accesos = [];

    /**
     * Qué se dice de cada persona. Solo lo necesario: `sub` (obligatorio),
     * `nombre`, `roles` y `locale`. Los roles deciden qué documentación y
     * qué accesos ve. Recibe también el guard, porque en cada área el usuario
     * puede ser de un modelo distinto.
     *
     * @param  Closure(Authenticatable, string): array<string, mixed>  $identidad
     */
    public static function identificarCon(Closure $identidad): void
    {
        static::$identidad = $identidad;
    }

    /**
     * Las pantallas de un área, calculadas cuando se sincroniza. Es la forma
     * de sacarlas del menú del propio sistema en vez de copiarlas en config.
     *
     * @param  Closure(): array<int, array<string, mixed>>  $accesos
     */
    public static function accesosCon(string $guard, Closure $accesos): void
    {
        static::$accesos[$guard] = $accesos;
    }

    /** Vuelve a como estaba al arrancar. Para las pruebas. */
    public static function olvidar(): void
    {
        static::$identidad = null;
        static::$accesos = [];
    }

    /**
     * Los widgets configurados, por guard.
     *
     * @return array<string, array{clave: string, secreto: ?string}>
     */
    public static function widgets(): array
    {
        return array_filter((array) config('ego-docs.widgets'), fn ($widget) => filled($widget['clave'] ?? null));
    }

    /**
     * El widget que corresponde a un guard: el suyo o, si el sistema tiene uno
     * solo, ese. Sin guard, el de la petición actual (el que fijó `auth:...`).
     *
     * @return array{guard: string, clave: string, secreto: ?string}|null
     */
    public static function widget(?string $guard = null): ?array
    {
        $guard ??= auth()->getDefaultDriver();
        $widgets = static::widgets();
        $elegido = $widgets[$guard] ?? null;

        if ($elegido) {
            return ['guard' => $guard, ...$elegido];
        }

        return isset($widgets[self::CUALQUIERA]) ? ['guard' => $guard, ...$widgets[self::CUALQUIERA]] : null;
    }

    /**
     * Las pantallas de un guard: las registradas con `accesosCon()` o, si no,
     * las de la configuración.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function accesos(string $guard): array
    {
        if (isset(static::$accesos[$guard])) {
            return (static::$accesos[$guard])();
        }

        $configurados = (array) config('ego-docs.accesos');

        // Una lista sin guard es la del widget único.
        if (array_is_list($configurados)) {
            return $guard === self::CUALQUIERA ? $configurados : [];
        }

        return (array) ($configurados[$guard] ?? []);
    }

    /**
     * El token firmado de quien tiene sesión en ese guard, o nulo si no hay
     * nadie, no hay widget o falta el secreto.
     */
    public static function token(?Authenticatable $usuario = null, ?string $guard = null): ?string
    {
        $widget = static::widget($guard);
        $usuario ??= $widget ? auth($widget['guard'])->user() : null;

        if (! $widget || ! $usuario || blank($widget['secreto'])) {
            return null;
        }

        $datos = static::$identidad
            ? (static::$identidad)($usuario, $widget['guard'])
            : ['sub' => $usuario->getAuthIdentifier(), 'nombre' => $usuario->name ?? null];

        $ahora = time();

        return Firmador::firmar([
            ...$datos,
            'sub' => (string) ($datos['sub'] ?? $usuario->getAuthIdentifier()),
            'iat' => $ahora,
            'exp' => $ahora + (int) config('ego-docs.vigencia', 3600),
            // Ata el token a ese widget: no sirve en otro.
            'aud' => $widget['clave'],
        ], (string) $widget['secreto']);
    }

    /**
     * La etiqueta <script> del widget, con la identidad de quien tiene sesión.
     * Vacía si para ese guard no hay widget: el login, por ejemplo.
     */
    public static function script(?string $guard = null): HtmlString
    {
        $url = rtrim((string) config('ego-docs.url'), '/');
        $widget = static::widget($guard);

        if ($url === '' || ! $widget) {
            return new HtmlString('');
        }

        $token = static::token(null, $widget['guard']);

        $atributos = array_filter([
            'src' => "{$url}/widget/egodocs.js",
            'data-clave' => $widget['clave'],
            'data-token' => $token,
            'data-idioma' => app()->getLocale(),
            // Con sesión, dónde pedir otro token cuando este venza.
            'data-renovar' => $token && Route::has('egodocs.token')
                ? route('egodocs.token', ['guard' => $widget['guard']], false)
                : null,
        ]);

        $html = collect($atributos)->map(fn ($valor, $nombre) => $nombre.'="'.e($valor).'"')->implode(' ');

        return new HtmlString("<script {$html} defer></script>");
    }
}
