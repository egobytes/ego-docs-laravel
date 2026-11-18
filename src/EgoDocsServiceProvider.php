<?php

namespace EgoBytes\EgoDocs;

use EgoBytes\EgoDocs\Console\SincronizarAccesos;
use Illuminate\Support\Facades\Blade;
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

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/ego-docs.php' => config_path('ego-docs.php')], 'ego-docs');
            $this->commands([SincronizarAccesos::class]);
        }
    }
}
