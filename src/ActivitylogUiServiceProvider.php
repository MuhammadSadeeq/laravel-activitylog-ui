<?php

namespace MuhammadSadeeq\ActivitylogUi;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ActivitylogUiServiceProvider extends ServiceProvider
{
    /**
     * Package version.
     */
    public const VERSION = '1.1.0';
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/config/activitylog-ui.php',
            'activitylog-ui'
        );

        // Register services
        $this->app->singleton(\MuhammadSadeeq\ActivitylogUi\Services\ActivitylogService::class);
        $this->app->singleton(\MuhammadSadeeq\ActivitylogUi\Services\AnalyticsService::class);
        $this->app->singleton(\MuhammadSadeeq\ActivitylogUi\Services\ExportService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'activitylog-ui');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        // Define Gate for package access control
        Gate::define('viewActivityLogUi', function ($user = null) {
            // Check if user has permission to view activity log UI
            // You can customize this logic based on your needs
            if ($user === null) {
                return false;
            }

            // Check config for allowed users/roles
            $allowedUsers = config('activitylog-ui.access.allowed_users', []);
            $allowedRoles = config('activitylog-ui.access.allowed_roles', []);

            // If no restrictions are set, allow all authenticated users
            if (empty($allowedUsers) && empty($allowedRoles)) {
                return true;
            }

            // Check if user is in allowed users list
            if (!empty($allowedUsers) && in_array($user->email, $allowedUsers)) {
                return true;
            }

            // Check if user has any of the allowed roles
            if (!empty($allowedRoles) && method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole($allowedRoles);
            }

            // Check if user has role method and any allowed role
            if (!empty($allowedRoles) && method_exists($user, 'hasRole')) {
                foreach ($allowedRoles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            }

            return false;
        });

        // Register publishable resources
        $this->registerPublishing();

        // Register middleware if needed
        $this->registerMiddleware();

        // Register commands if any
        $this->registerCommands();
    }

    /**
     * Register middleware for the package.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('activitylog-access', \MuhammadSadeeq\ActivitylogUi\Http\Middleware\ActivityLogAccessMiddleware::class);
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            // Register commands here if any
            // $this->commands([
            //     \MuhammadSadeeq\ActivitylogUi\Console\Commands\InstallCommand::class,
            // ]);
        }
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/config/activitylog-ui.php' => config_path('activitylog-ui.php'),
        ], 'activitylog-ui-config');

        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/activitylog-ui'),
        ], 'activitylog-ui-views');

        // Publish images (logo, favicon, etc.)
        $this->publishes([
            __DIR__ . '/resources/images' => public_path('vendor/activitylog-ui/images'),
        ], 'activitylog-ui-assets');

        // Publish CSS assets if they exist
        if (is_dir(__DIR__ . '/resources/css')) {
            $this->publishes([
                __DIR__ . '/resources/css' => public_path('vendor/activitylog-ui/css'),
            ], 'activitylog-ui-assets');
        }

        // Publish JS assets if they exist
        if (is_dir(__DIR__ . '/resources/js')) {
            $this->publishes([
                __DIR__ . '/resources/js' => public_path('vendor/activitylog-ui/js'),
            ], 'activitylog-ui-assets');
        }
    }
}
