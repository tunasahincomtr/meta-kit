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

        // Bind as transient (not singleton) to ensure fresh instance per request
        // This prevents state leakage between requests
        $this->app->bind(MetaKitManager::class, function ($app) {
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
        
        // Load web routes (e.g., sitemap.xml)
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load views for Blade components
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'metakit');

        $this->registerBladeDirectives();
        $this->registerMiddleware();
    }

    /**
     * Register middleware for conflict guard and alias redirect.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make('router');
        
        // Register alias redirect middleware (runs first, before conflict guard)
        if (config('metakit.alias_redirect', true)) {
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('web', \TunaSahincomtr\MetaKit\Http\Middleware\MetaKitAliasRedirect::class);
            }
        }

        // Register middleware for conflict detection
        // This will run on all web requests to detect and remove duplicate meta tags
        $conflictGuardConfig = config('metakit.conflict_guard', []);
        $isEnabled = is_array($conflictGuardConfig) 
            ? ($conflictGuardConfig['enabled'] ?? true)
            : (bool) $conflictGuardConfig;
            
        if ($isEnabled) {
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('web', \TunaSahincomtr\MetaKit\Http\Middleware\MetaKitConflictGuard::class);
            }
        }
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // Meta tags directive
        Blade::directive('metakit', function () {
            return '<?php echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->render(); ?>';
        });

        // Title directive
        Blade::directive('metakitTitle', function () {
            return '<?php echo e(app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->getTitle()); ?>';
        });

        // Meta tag directive
        Blade::directive('metakitMeta', function ($expression) {
            return "<?php echo e(app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->getMeta({$expression})); ?>";
        });

        // JSON-LD directive
        Blade::directive('metakitJsonLd', function () {
            return '<?php echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->renderJsonLd(); ?>';
        });

        // Debug directive
        Blade::directive('metakitDebug', function () {
            return '<?php if (config("app.debug")) { echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->renderDebug(); } ?>';
        });

        // Form directive - Auth protected admin form for MetaKit pages
        // Usage: @metakitform
        // Auth kontrolü config'den yönetilir: metakit.form.auth_required
        Blade::directive('metakitform', function () {
            // Get auth configuration
            $authRequired = config('metakit.form.auth_required', true);
            $authGuard = config('metakit.form.auth_guard', 'web');
            $authDeniedMessage = config('metakit.form.auth_denied_message', 'Bu sayfaya erişmek için giriş yapmanız gerekmektedir.');
            $authRedirectRoute = config('metakit.form.auth_redirect_route', null);
            
            // Auth kontrolü (sadece auth_required true ise)
            $authCheck = '';
            if ($authRequired) {
                // Check authentication with specified guard
                $guardCheck = "auth()->guard('{$authGuard}')->check()";
                
                // If redirect route is specified, redirect instead of showing message
                if ($authRedirectRoute) {
                    // Try route name first, if fails, treat as URL
                    $authCheck = "<?php if (!{$guardCheck}) { try { return redirect()->route('{$authRedirectRoute}'); } catch (\\Illuminate\\Routing\\Exceptions\\UrlGenerationException \$e) { return redirect('{$authRedirectRoute}'); } } ?>";
                } else {
                    // Show message if not authenticated
                    $message = addslashes($authDeniedMessage);
                    $authCheck = "<?php if (!{$guardCheck}) { echo '<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-triangle\"></i> {$message}</div>'; return; } ?>";
                }
            }
            
            // Render the form component with all necessary data
            // Primary color artık CSS variable'dan okunacak (--metakit-primary)
            // Config/ENV kullanılmıyor, sadece CSS'den çekiliyor
            $apiPrefix = config('metakit.api_prefix', 'api/metakit');
            $renderForm = "<?php echo view('metakit::components.form', ['apiPrefix' => '{$apiPrefix}'])->render(); ?>";
            
            return $authCheck . $renderForm;
        });
    }
}
