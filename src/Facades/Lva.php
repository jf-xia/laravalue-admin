<?php

namespace Vreap\Lva\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Lva.
 *
 * @method static \Vreap\Lva\Grid grid($model, \Closure $callable)
 * @method static \Vreap\Lva\Form form($model, \Closure $callable)
 * @method static \Vreap\Lva\Tree tree($model, \Closure $callable = null)
 * @method static \Vreap\Lva\Layout\Content content(\Closure $callable = null)
 * @method static \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void css($css = null)
 * @method static \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void js($js = null)
 * @method static \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void script($script = '')
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 * @method static string title()
 * @method static void navbar(\Closure $builder = null)
 * @method static void registerAuthRoutes()
 * @method static void extend($name, $class)
 */
class Lva extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Vreap\Lva\Lva::class;
    }
}
