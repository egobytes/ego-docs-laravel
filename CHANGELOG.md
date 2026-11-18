# Cambios

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones, [SemVer](https://semver.org/lang/es/).

## [1.0.0] - 2026-11-18

Primera versión pública.

- `@egodocs` imprime el script del widget con el token de quien tiene sesión.
- Un widget o uno por guard: `@egodocs` elige el del guard de la página y `@egodocs('guard')` lo fija.
- `EgoDocs::identificarCon()` define qué se dice de cada persona (id, nombre, roles, idioma), por guard.
- `EgoDocs::accesosCon()` registra las pantallas de un área desde el menú del propio sistema.
- `php artisan egodocs:accesos` sincroniza el catálogo de cada widget, con `--guard` y `--simular`.
- Laravel 11, 12 y 13; PHP 8.2 o superior.
