<?php

namespace TunaSahincomtr\MetaKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use TunaSahincomtr\MetaKit\Services\MetaKitManager;

class MetaKitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/metakit.php',
            'metakit'
        );

        $this->app->singleton(MetaKitManager::class, function ($app) {
            return new MetaKitManager();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/metakit.php' => config_path('metakit.php'),
        ], 'metakit-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerBladeDirectives();
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('metakit', function () {
            return '<?php echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->render(); ?>';
        });

        Blade::directive('metakitTitle', function () {
            return '<?php echo e(app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->getTitle()); ?>';
        });

        Blade::directive('metakitMeta', function ($expression) {
            return "<?php echo e(app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->getMeta({$expression})); ?>";
        });

        Blade::directive('metakitJsonLd', function () {
            return '<?php echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->renderJsonLd(); ?>';
        });

        Blade::directive('metakitDebug', function () {
            return '<?php if (config("metakit.debug_comments") && app()->isLocal()) { echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->renderDebug(); } ?>';
        });
    }
}

