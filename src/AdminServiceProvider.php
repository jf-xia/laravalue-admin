<?php

namespace Vreap\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    protected $commands = [
        \Vreap\Admin\Console\InstallCommand::class,
        \Vreap\Admin\Console\UninstallCommand::class,
    ];
    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'admin.log'        => \Encore\Admin\Middleware\LogOperation::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'admin' => [
            'admin.log',
        ],
    ];

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin');

        if (file_exists($routes = admin_path('routes.php'))) {
            $this->loadRoutesFrom($routes);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config' => config_path()], 'laravalue-admin-config');
            $this->publishes([__DIR__.'/../resources/lang' => resource_path('lang')], 'laravalue-admin-lang');
//            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/admin')],           'laravel-admin-views');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'laravalue-admin-migrations');
            $this->publishes([__DIR__.'/../resources/assets' => public_path('vendor/laravel-admin')], 'laravalue-admin-assets');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->loadAdminAuthConfig();

        $this->registerRouteMiddleware();

        $this->commands($this->commands);
    }

    /**
     * Setup auth configuration.
     *
     * @return void
     */
    protected function loadAdminAuthConfig()
    {
        config(array_dot(config('admin.auth', []), 'auth.'));
    }

    /**
     * Register the route middleware.
     *
     * @return void
     */
    protected function registerRouteMiddleware()
    {
        // register route middleware.
        foreach ($this->routeMiddleware as $key => $middleware) {
            app('router')->aliasMiddleware($key, $middleware);
        }

        // register middleware group.
        foreach ($this->middlewareGroups as $key => $middleware) {
            app('router')->middlewareGroup($key, $middleware);
        }
    }
}
