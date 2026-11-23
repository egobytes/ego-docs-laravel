<?php

namespace EgoBytes\EgoDocs;

use EgoBytes\EgoDocs\Console\SincronizarAccesos;
use EgoBytes\EgoDocs\Http\RenovarToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EgoDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ego-docs.php', 'ego-docs');
    }

    public function boot(): void
    {
        // @egodocs → el widget del guard de la página; @egodocs('mechanics') → el de ese guard.
        Blade::directive('egodocs', fn (string $guard) => '<?php echo \\'.EgoDocs::class.'::script('.($guard !== '' ? $guard : 'null').'); ?>');

        // Donde el widget pide un token nuevo cuando el suyo vence.
        if (filled(config('ego-docs.ruta_token'))) {
            Route::middleware('web')->get((string) config('ego-docs.ruta_token'), RenovarToken::class)->name('egodocs.token');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/ego-docs.php' => config_path('ego-docs.php')], 'ego-docs');
            $this->commands([SincronizarAccesos::class]);
        }
    }
}
