<?php

namespace Vreap\Lva;

use Illuminate\Support\ServiceProvider;

class LvaServiceProvider extends ServiceProvider
{
    protected $commands = [
        \Vreap\Lva\Console\InstallCommand::class,
        \Vreap\Lva\Console\UninstallCommand::class,
    ];
    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'lva.log'        => \Vreap\Lva\Middleware\LogOperation::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'lva' => [
            'lva.log',
        ],
    ];

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lva');

        if (file_exists($routes = lva_path('routes.php'))) {
            $this->loadRoutesFrom($routes);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config' => config_path()], 'lva-config');
            $this->publishes([__DIR__.'/../resources/lang' => resource_path('lang')], 'lva-lang');
//            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/lva')],'lva-views');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'lva-migrations');
            $this->publishes([__DIR__.'/../resources/assets' => public_path('vendor/laravel-lva')], 'lva-assets');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->loadLvaAuthConfig();

        $this->registerRouteMiddleware();

        $this->commands($this->commands);
    }

    /**
     * Setup auth configuration.
     *
     * @return void
     */
    protected function loadLvaAuthConfig()
    {
        config(array_dot(config('lva.auth', []), 'auth.'));
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
