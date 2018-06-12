<?php

namespace Vreap\Lva\Middleware;

use Vreap\Lva\Auth\Database\OperationLog as OperationLogModel;
use Vreap\Lva\Facades\Lva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Str;

class LogOperation
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
//        $debug = \DB::listen(function($sql, $bindings, $time) {
//            echo ('SQL语句执行：'.$sql.'，参数：'.json_encode($bindings).',耗时：'.$time.'ms');
//        });
//        $debug2 = \Event::listen('illuminate.query', function ($query) {
//            \Log::debug($query);
//        });
        if (Input::get('debug')){
            \DB::enableQueryLog();
        }
        if ($this->shouldLogOperation($request)) {
            $log = [
                'user_id' => Lva::user()->id,
                'path'    => $request->path(),
                'method'  => $request->method(),
                'ip'      => $request->getClientIp(),
                'input'   => json_encode($request->input()),
            ];

            OperationLogModel::create($log);
        }

        return $next($request);
    }

    public function terminate($request, $response)
    {
        // Store or dump the log data...
//        \Log::debug(
//            \DB::getQueryLog()
//        );
//        \Log::debug($request->all());
        if (Input::get('debug')){
            $queryLog = \DB::getQueryLog();
            if ($queryLog){
                foreach ($queryLog as $logs){
                    $logs['route'] = $request->path();
                    $logs['created_at'] = date('Y-m-d H:i:s');
                    $logs['bindings'] = isset($logs['bindings']) ? implode(',',$logs['bindings']) : '';
                    \DB::table('log_db')->insert($logs);
                }
            }
        }
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    protected function shouldLogOperation(Request $request)
    {
        return config('lva.operation_log.enable')
            && !$this->inExceptArray($request)
            && Lva::user();
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach (config('lva.operation_log.except') as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            $methods = [];

            if (Str::contains($except, ':')) {
                list($methods, $except) = explode(':', $except);
                $methods = explode(',', $methods);
            }

            $methods = array_map('strtoupper', $methods);

            if ($request->is($except) &&
                (empty($methods) || in_array($request->method(), $methods))) {
                return true;
            }
        }

        return false;
    }
}
