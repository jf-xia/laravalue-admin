<?php

namespace Vreap\Lav;

use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Class Eav.
 */
class Lav
{
    /**
     * @var Navbar
     */
    protected $navbar;

    /**
     * @var array
     */
    public static $script = [];

    /**
     * @var array
     */
    public static $css = [];

    /**
     * @var array
     */
    public static $js = [];

    /**
     * @var array
     */
    public static $extensions = [];

    /**
     * @param $model
     *
     * @return mixed
     */
    public function getModel($model)
    {
        if ($model instanceof EloquentModel) {
            return $model;
        }

        if (is_string($model) && class_exists($model)) {
            return $this->getModel(new $model());
        }

        throw new InvalidArgumentException("$model is not a valid model");
    }

    /**
     * Register the auth routes.
     *
     * @return void
     */
    public function registerAuthRoutes()
    {
        $attributes = [
            'prefix'     => config('lva.route.prefix'),
            'namespace'  => 'Vreap\Controllers',
            'middleware' => config('lva.route.middleware'),
        ];

        Route::group($attributes, function ($router) {

            /* @var \Illuminate\Routing\Router $router */
            $router->group([], function ($router) {

                /* @var \Illuminate\Routing\Router $router */
//                $router->resource('auth/users', 'UserController');
//                $router->resource('auth/roles', 'RoleController');
//                $router->resource('auth/permissions', 'PermissionController');
//                $router->resource('auth/menu', 'MenuController', ['except' => ['create']]);
//                $router->resource('auth/logs', 'LogController', ['only' => ['index', 'destroy']]);
            });

        });
    }

    /**
     * Extend a extension.
     *
     * @param string $name
     * @param string $class
     *
     * @return void
     */
    public static function extend($name, $class)
    {
        static::$extensions[$name] = $class;
    }
}
