<?php

namespace TunaSahincomtr\MetaKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
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
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'metakit');
        
        // Debug: Log view path to ensure it's correct
        if (config('app.debug', false)) {
            Log::info('MetaKit View Path', [
                'view_path' => $viewPath,
                'view_exists' => file_exists($viewPath),
                'form_view_exists' => file_exists($viewPath . '/components/form.blade.php'),
                'base_path' => base_path(),
            ]);
        }

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
return
'<?php if (config("app.debug")) { echo app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class)->renderDebug(); } ?>';
});

// Form directive - Auth protected admin form for MetaKit pages
// Usage: @metakitform
// Auth kontrolü config'den yönetilir: metakit.form.auth_required
Blade::directive('metakitform', function () {
// Get auth configuration with safe defaults
$authRequired = config('metakit.form.auth_required', false);
$authGuard = config('metakit.form.auth_guard', 'web');
$authDeniedMessage = config('metakit.form.auth_denied_message', 'Bu sayfaya erişmek için giriş yapmanız
gerekmektedir.');
$authRedirectRoute = config('metakit.form.auth_redirect_route', null);

// Render the form component with all necessary data
$apiPrefix = config('metakit.api_prefix', 'api/metakit');

// Debug logging
Log::info('MetaKit Form Directive Called', [
'auth_required' => $authRequired,
'auth_guard' => $authGuard,
'api_prefix' => $apiPrefix,
'config_exists' => config('metakit.form') !== null,
]);

// Build PHP code for the directive with logging
$logCode = "\\Illuminate\\Support\\Facades\\Log::info('MetaKit Form Directive Executing', ['auth_required' => " .
($authRequired ? 'true' : 'false') . "]);";

// If auth is required, check and handle accordingly
if ($authRequired && $authRedirectRoute) {
// Redirect case - use app() to handle redirect properly
$redirectRoute = addslashes($authRedirectRoute);
return
"<?php {$logCode} if (!auth()->guard('{$authGuard}')->check()) { \\Illuminate\\Support\\Facades\\Log::info('MetaKit Form: Auth failed, redirecting'); try { \$redirect = redirect()->route('{$redirectRoute}'); } catch (\\Illuminate\\Routing\\Exceptions\\UrlGenerationException \$e) { \$redirect = redirect('{$redirectRoute}'); } \$redirect->send(); exit; } \\Illuminate\\Support\\Facades\\Log::info('MetaKit Form: Rendering view'); try { echo view('metakit::components.form', ['apiPrefix' => '{$apiPrefix}'])->render(); } catch (\\Exception \$e) { \\Illuminate\\Support\\Facades\\Log::error('MetaKit Form View Error', ['message' => \$e->getMessage(), 'trace' => \$e->getTraceAsString()]); echo '<div class=\"alert alert-danger\">Form yüklenirken hata oluştu: ' . e(\$e->getMessage()) . '</div>'; } ?>";
} elseif ($authRequired) {
// Show message if not authenticated
$message = addslashes($authDeniedMessage);
return
"<?php {$logCode} if (!auth()->guard('{$authGuard}')->check()) { \\Illuminate\\Support\\Facades\\Log::info('MetaKit Form: Auth failed, showing message'); echo '<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-triangle\"></i> {$message}</div>'; } else { \\Illuminate\\Support\\Facades\\Log::info('MetaKit Form: Rendering view'); try { echo view('metakit::components.form', ['apiPrefix' => '{$apiPrefix}'])->render(); } catch (\\Exception \$e) { \\Illuminate\\Support\\Facades\\Log::error('MetaKit Form View Error', ['message' => \$e->getMessage(), 'trace' => \$e->getTraceAsString()]); echo '<div class=\"alert alert-danger\">Form yüklenirken hata oluştu: ' . e(\$e->getMessage()) . '</div>'; } } ?>";
} else {
// No auth required - just render the form
return
"<?php {$logCode} \\Illuminate\\Support\\Facades\\Log::info('MetaKit Form: No auth required, rendering view'); try { echo view('metakit::components.form', ['apiPrefix' => '{$apiPrefix}'])->render(); } catch (\\Exception \$e) { \\Illuminate\\Support\\Facades\\Log::error('MetaKit Form View Error', ['message' => \$e->getMessage(), 'trace' => \$e->getTraceAsString()]); echo '<div class=\"alert alert-danger\">Form yüklenirken hata oluştu: ' . e(\$e->getMessage()) . '</div>'; } ?>";
}
});
}
}
