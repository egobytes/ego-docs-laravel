<?php

namespace EgoBytes\EgoDocs\Console;

use EgoBytes\EgoDocs\EgoDocs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Throwable;

/**
 * Manda a EGO docs el catálogo de pantallas de cada widget del sistema.
 *
 * Deja el catálogo de cada bot igual a su lista: lo que ya no está se borra
 * (solo lo que llegó por aquí; lo cargado a mano en el panel no se toca).
 * Conviene correrlo en cada despliegue, después de las migraciones.
 */
class SincronizarAccesos extends Command
{
    protected $signature = 'egodocs:accesos
        {--guard=* : Solo los widgets de estos guards}
        {--simular : Muestra lo que se mandaría, sin mandarlo}';

    protected $description = 'Sincroniza los accesos directos de este sistema con EGO docs';

    public function handle(): int
    {
        $url = rtrim((string) config('ego-docs.url'), '/');
        $widgets = EgoDocs::widgets();

        if ($only = $this->option('guard')) {
            $widgets = array_intersect_key($widgets, array_flip($only));
        }

        if ($url === '' || ! $widgets) {
            $this->error('Falta EGODOCS_URL o no hay widgets configurados (EGODOCS_CLAVE, o uno por guard en config/ego-docs.php).');

            return self::FAILURE;
        }

        $resultado = self::SUCCESS;

        foreach ($widgets as $guard => $widget) {
            $nombre = $guard === EgoDocs::CUALQUIERA ? 'widget' : "widget «{$guard}»";

            if (blank($widget['secreto'] ?? null)) {
                $this->error("{$nombre}: falta el secreto.");
                $resultado = self::FAILURE;

                continue;
            }

            try {
                $accesos = array_map($this->preparar(...), EgoDocs::accesos($guard));
            } catch (Throwable $e) {
                $this->error("{$nombre}: {$e->getMessage()}");
                $resultado = self::FAILURE;

                continue;
            }

            if ($this->option('simular')) {
                $this->info("{$nombre}: ".count($accesos).' accesos');
                $this->table(['Clave', 'Ruta', 'Roles'], array_map(fn ($a) => [$a['clave'], $a['ruta'], implode(', ', $a['roles'] ?? []) ?: 'todos'], $accesos));

                continue;
            }

            if (! $this->mandar($url, $widget, $accesos, $nombre)) {
                $resultado = self::FAILURE;
            }
        }

        return $resultado;
    }

    /**
     * @param  array{clave: string, secreto: string}  $widget
     * @param  array<int, array<string, mixed>>  $accesos
     */
    protected function mandar(string $url, array $widget, array $accesos, string $nombre): bool
    {
        $respuesta = Http::withToken($widget['secreto'])
            ->withHeaders(['X-Ego-Key' => $widget['clave']])
            ->acceptJson()
            ->timeout(30)
            ->put("{$url}/api/widget/accesos", ['accesos' => $accesos]);

        if ($respuesta->failed()) {
            $this->error("{$nombre}: EGO docs respondió {$respuesta->status()}: ".($respuesta->json('error.mensaje') ?? $respuesta->json('message') ?? $respuesta->body()));

            foreach ((array) $respuesta->json('errors') as $campo => $errores) {
                $this->line("  {$campo}: ".implode(' ', (array) $errores));
            }

            return false;
        }

        $this->info(sprintf(
            '%s: %d creados, %d actualizados, %d borrados.',
            $nombre, $respuesta->json('creados'), $respuesta->json('actualizados'), $respuesta->json('borrados'),
        ));

        return true;
    }

    /**
     * Si falta la ruta y la clave es el nombre de una ruta de Laravel, se toma
     * de ahí: así el catálogo no se desactualiza cuando cambia una URL.
     *
     * @param  array<string, mixed>  $acceso
     * @return array<string, mixed>
     */
    protected function preparar(array $acceso): array
    {
        $clave = (string) ($acceso['clave'] ?? '');

        if (empty($acceso['ruta'])) {
            if (! Route::has($clave)) {
                throw new InvalidArgumentException("el acceso «{$clave}» no tiene ruta y no hay una ruta de Laravel con ese nombre.");
            }

            $acceso['ruta'] = route($clave, [], false);
        }

        return array_filter([
            'clave' => $clave,
            'ruta' => $acceso['ruta'],
            'titulo' => $acceso['titulo'] ?? null,
            'descripcion' => $acceso['descripcion'] ?? null,
            'roles' => $acceso['roles'] ?? null,
        ], fn ($valor) => $valor !== null);
    }
}
