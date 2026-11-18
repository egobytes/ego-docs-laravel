# egobytes/ego-docs-laravel

[![tests](https://github.com/egobytes/ego-docs-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/egobytes/ego-docs-laravel/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/egobytes/ego-docs-laravel)](https://packagist.org/packages/egobytes/ego-docs-laravel)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> **English summary.** Laravel integration for [EGO docs](https://egobytes.com), an in-app help assistant that answers only from each system's own documentation. This package prints the widget script (`@egodocs`), signs a short-lived HS256 token with the signed-in user's id, name, roles and locale, supports one widget per auth guard, and syncs the catalog of screens the assistant may link to (`php artisan egodocs:accesos`). Requires PHP 8.2+ and Laravel 12 or 13. The rest of this document is in Spanish.

Pone el asistente de ayuda de EGO docs dentro de un sistema Laravel: el botón de chat que responde con la documentación del sistema y lleva a la persona a la pantalla donde se hace cada cosa.

**Requisitos:** PHP 8.2 o superior y Laravel 12 o 13 (Laravel 13 pide PHP 8.3). Una cuenta de EGO docs con un widget creado para el sistema.

El paquete hace tres cosas:

1. Imprime el script del widget con `@egodocs`.
2. Firma un token con quién tiene sesión (id, nombre, roles, idioma), para que el bot filtre la documentación y los accesos por rol.
3. Manda a EGO docs el catálogo de pantallas con `php artisan egodocs:accesos`.

Un sistema puede tener **un widget** o **uno por guard**: por ejemplo, un panel administrativo y una app de mecánicos, cada uno con su bot y su manual.

## Instalación

```bash
composer require egobytes/ego-docs-laravel
```

El paquete se registra solo (auto-discovery de Laravel).

### Un solo widget

En el `.env`, los valores que muestra la página del widget en el panel de EGO docs:

```dotenv
EGODOCS_URL=https://docs.egobytes.com
EGODOCS_CLAVE=pk_...
EGODOCS_SECRETO=sk_...
```

### Un widget por guard

Se publica la configuración:

```bash
php artisan vendor:publish --tag=ego-docs
```

y en `config/ego-docs.php` se declara un widget por guard:

```php
'widgets' => array_filter([
    'admin' => ['clave' => env('EGODOCS_ADMIN_CLAVE'), 'secreto' => env('EGODOCS_ADMIN_SECRETO')],
    'mechanics' => ['clave' => env('EGODOCS_MECHANICS_CLAVE'), 'secreto' => env('EGODOCS_MECHANICS_SECRETO')],
], fn (array $widget) => filled($widget['clave'] ?? null)),
```

```dotenv
EGODOCS_URL=https://docs.egobytes.com
EGODOCS_ADMIN_CLAVE=pk_...
EGODOCS_ADMIN_SECRETO=sk_...
EGODOCS_MECHANICS_CLAVE=pk_...
EGODOCS_MECHANICS_SECRETO=sk_...
```

El secreto es privado: solo en el servidor. Nunca en el HTML, en JavaScript ni en el repositorio.

## El widget

En el layout, antes de `</body>`:

```blade
@egodocs
```

Con un widget por guard, `@egodocs` elige el del guard de la página: el que dejó fijado el middleware `auth:admin` o `auth:mechanics`. En una página sin guard con widget (el login, por ejemplo) no imprime nada. Para fijarlo a mano:

```blade
@egodocs('mechanics')
```

Sin sesión iniciada se imprime el script sin token: el widget responde con la documentación pública, salvo que en el panel esté activado «Exigir identidad».

## Identidad y roles

Por defecto se manda el id y el nombre del usuario. Si el sistema tiene roles, se declaran una vez en `AppServiceProvider::boot()`. Recibe también el guard, porque en cada área el usuario puede ser de un modelo distinto:

```php
use EgoBytes\EgoDocs\EgoDocs;

EgoDocs::identificarCon(fn ($usuario, string $guard) => [
    'sub' => $usuario->id,
    'nombre' => $usuario->name,
    'roles' => method_exists($usuario, 'getRoleNames') ? $usuario->getRoleNames()->all() : [],
    'locale' => app()->getLocale(),
]);
```

Los roles se comparan con los que se asignan en EGO docs a cada documento y a cada acceso: quien no tiene el rol no ve ese contenido ni ese botón.

El token vence en una hora (`EGODOCS_VIGENCIA`, en segundos) y se firma de nuevo en cada carga de página. En una aplicación de una sola página que dure más, se puede renovar sin recargar:

```js
EgoDocs.identificar(nuevoToken)
```

## Accesos directos

Las pantallas a las que el bot puede llevar a alguien, por guard. Si el sistema ya tiene un catálogo de pantallas (su menú), conviene registrarlas desde ahí, en `AppServiceProvider::boot()`; así no se desactualizan:

```php
EgoDocs::accesosCon('admin', fn () => app(Menu::class)->paraEgoDocs());
```

Cada acceso es un arreglo con:

| Campo | |
|---|---|
| `clave` | Única. Minúsculas, números, `.`, `-`, `_` y `:` (`admin::sales.index`). Si es el nombre de una ruta sin parámetros, `ruta` se puede omitir. |
| `ruta` | Dentro del sistema, empezando con `/`. |
| `titulo` | Un texto, o uno por idioma (`['es' => …, 'en' => …]`). |
| `descripcion` | Opcional, igual que el título. |
| `roles` | Quién lo ve. Vacío = todos. |

También se pueden declarar en `config/ego-docs.php`, bajo `accesos`, con el guard como llave (o como lista directa, con un solo widget).

Se mandan a EGO docs en cada despliegue:

```bash
php artisan egodocs:accesos
```

```bash
php artisan egodocs:accesos --guard=admin --simular
```

El catálogo de cada bot queda igual a su lista. Los accesos cargados a mano en el panel de EGO docs no se tocan.

### Aplicaciones de una sola página

Al pulsar un acceso, el widget lanza el evento `egodocs:navegar` y, si nadie lo cancela, navega a la dirección. Con Inertia:

```js
import { router } from '@inertiajs/vue3'

window.addEventListener('egodocs:navegar', (evento) => {
    evento.preventDefault()
    router.visit(evento.detail.ruta)
})
```

Si la ruta no se refleja en la dirección del navegador, se le puede decir al widget dónde está la persona:

```js
EgoDocs.pantalla('/facturas/nueva')
```

## El token, para sistemas que no son Laravel

El paquete es solo para Laravel, pero integrar cualquier otro sistema no necesita paquete: basta con firmar el token con la librería de JWT de su lenguaje y poner el script.

```html
<script src="https://docs.egobytes.com/widget/egodocs.js" data-clave="pk_..." data-token="..." defer></script>
```

El token es un JWT firmado con **HS256** y el secreto del widget:

| Campo | |
|---|---|
| `sub` | Obligatorio. El id de la persona en el sistema (texto o número). |
| `exp` | Obligatorio. Vencimiento, en segundos Unix. No más de 24 horas adelante. |
| `nombre` | Opcional. Se ve en las conversaciones del panel. |
| `roles` | Opcional. Lista de textos: filtran documentación y accesos. |
| `locale` | Opcional. `es` o `en`. |
| `aud` | Opcional, recomendado. La clave pública del widget: así el token no sirve en otro. |

## Desarrollo

```bash
composer install
composer test
```

## Licencia

MIT. Ver [LICENSE](LICENSE).
