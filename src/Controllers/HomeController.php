<?php

namespace Encore\Admin\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Exception;
use Vreap\Lva\Layout\Content;

class TaskController extends Controller
{
    public function __construct()
    {

    }

    public function test(Request $request)
    {
        dd(111111111);
        //wechat/login?oid=1&url=/admin/tasks/42627/edit
//        dd(Task::with('value')->get()->toArray());
//        $task = Task::find(42517);
//        dd($task->toArray());
//        \DB::enableQueryLog();
//        dd($value, \DB::getQueryLog());
//        dd($updateCre->toArray(),$taskList->toArray(),\DB::getQueryLog());
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Lva::content(function (Content $content) {
            $typeName= trans('task.Tasks');
            $content->header($typeName);
            $content->description('...');
            $content->body(2222222222);
        });
    }
}
