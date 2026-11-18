<?php

return [

    /* Dónde está EGO docs. */
    'url' => env('EGODOCS_URL'),

    /*
     * Los widgets de este sistema: clave pública y secreto de firma de cada
     * uno, tal como los muestra la página del widget en el panel de EGO docs.
     *
     * Un sistema con un solo widget usa EGODOCS_CLAVE y EGODOCS_SECRETO, y
     * ese widget sale en cualquier parte (`*`).
     *
     * Un sistema con varias áreas (un panel administrativo y una app de
     * mecánicos, con manuales distintos) declara un widget por guard. El
     * script elige solo el del guard de la página que se está viendo:
     *
     *   'admin' => ['clave' => env('EGODOCS_ADMIN_CLAVE'), 'secreto' => env('EGODOCS_ADMIN_SECRETO')],
     *   'mechanics' => ['clave' => env('EGODOCS_MECHANICS_CLAVE'), 'secreto' => env('EGODOCS_MECHANICS_SECRETO')],
     *
     * El secreto es privado: vive solo aquí, en el servidor. Nunca en el
     * HTML, en JavaScript ni en el repositorio.
     */
    'widgets' => array_filter([
        '*' => ['clave' => env('EGODOCS_CLAVE'), 'secreto' => env('EGODOCS_SECRETO')],
    ], fn (array $widget) => filled($widget['clave'] ?? null)),

    /*
     * Cuánto vale el token de identidad, en segundos. Se firma uno nuevo en
     * cada carga de página, así que puede ser corto. EGO docs rechaza tokens
     * que venzan a más de 24 horas.
     */
    'vigencia' => (int) env('EGODOCS_VIGENCIA', 3600),

    /*
     * Las pantallas a las que el bot puede llevar a una persona, por guard.
     * Se mandan a EGO docs con `php artisan egodocs:accesos`.
     *
     * Si el sistema ya tiene un catálogo de pantallas (el menú), conviene
     * registrarlas desde ahí con `EgoDocs::accesosCon()` en vez de copiarlas
     * aquí: así no se desactualizan.
     *
     * Si la clave es el nombre de una ruta sin parámetros, la ruta se toma de
     * ahí y `ruta` se puede omitir. `titulo` y `descripcion` aceptan un texto
     * o uno por idioma. `roles` vacío = todos.
     *
     *   'admin' => [
     *       [
     *           'clave' => 'facturas.crear',
     *           'titulo' => ['es' => 'Nueva factura', 'en' => 'New invoice'],
     *           'descripcion' => 'Emitir una factura electrónica.',
     *           'roles' => ['facturador', 'admin'],
     *       ],
     *   ],
     *
     * Con un solo widget, la lista puede ir directo, sin el guard.
     */
    'accesos' => [],

];
