<?php

namespace Encore\Admin\Controllers;

use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Models\Task\Attribute;
use Encore\Admin\Models\Task\Report;
use Encore\Admin\Models\Task\Status;
use Encore\Admin\Models\Task\Type;
use Encore\Admin\Models\Task\Task;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Grid\Exporters\TaskExporter;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Exception;

class TaskController extends Controller
{
    use ModelForm;

    public $type = null;

    public $task = null;

    public $isComplete = null;

    public $lastTasks = [];

    public function __construct(Task $task, Type $type)
    {
        $this->isComplete=Input::get('complete');
        $this->task=$task;
        $this->type=$type->find(Input::get('type'));
        if (!$this->type){
            $this->type=$type;
        }
        if (isset(\Route::current()->parameters()['task'])){
            $this->task=$task->find(\Route::current()->parameters()['task']);
            $this->type=$this->task ? $this->task->type : null;
            $this->displayLastTask($this->task);
        }
    }

    public function test(Request $request)
    {
        dd(Admin::user()->name);
        //wechat/login?oid=1&url=/admin/tasks/42627/edit
//        dd(Task::with('value')->get()->toArray());
//        $task = Task::find(42517);
//        dd($task->toArray());
//        \DB::enableQueryLog();
//        dd($value, \DB::getQueryLog());
//        dd($updateCre->toArray(),$taskList->toArray(),\DB::getQueryLog());
    }

    public function workflow($id, Request $request)
    {
        $input = $request->all();
//        \Log::debug($input);
        $title = isset($input['title']) ? $input['title'] : $id;
        if(!isset($input['assignableUser'])){
            return response()->json([
                'status'  => false,
                'message' => trans('task.Action').trans('task.Error').'! '.trans('task.No assignable User Selected!'),
            ]);
        }
        $user_id = $input['assignableUser'];
        $complateTasks = [];
        $errorTasks = [];
        $ids = explode(',', $id);
        $tasks = Task::find($ids);
        foreach ($tasks as $task) {
            if ($task->next && $task->next->status_id==5){
                $complateTasks[] = $task->id;
            }

            if (!$task->saveAssign($user_id,$title)){
                $errorTasks[] = $task->id;
            }
        }

        if ($complateTasks || $errorTasks){
            $message = $errorTasks ? trans('task.Action').trans('task.Error').'('.implode(', ',$complateTasks).')! ' : '';
            $message .= $complateTasks ? trans('task.The following tasks have submited with Complated status which have been ignore:').implode(', ',$complateTasks).'. ' : '';
            return response()->json([
                'status'  => false,
                'message' => $message,
            ]);
        }else{
            return response()->json([
                'status'  => true,
                'message' => trans('task.Action').trans('task.Success').'! ',
            ]);
        }
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {
            $typeName=$this->type->name ? $this->type->name : trans('task.Tasks');
            $content->header($typeName);
            $content->description('...');
            $content->body($this->grid()->render());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Task::class, function (Grid $grid) {
            if ($this->isComplete==5){
                $grid->model()->where('status_id','=',5);
            } else {
                $grid->model()->where('status_id','<>',5);
            }
            if (!Admin::user()->isAdministrator()){
                $userIds = Administrator::where('leader_id',Admin::user()->id)->get()->pluck('id')->toArray();
                $userIds[] = Admin::user()->id;
                $grid->model()->whereIn('user_id',$userIds);
            }
            $this->getColumns($grid);
            $this->getActions($grid);
            $this->getTools($grid);
            $this->getFilter($grid);
            $grid->disableExport();
        });
    }

    public function getColumns($grid)
    {
//        $grid->id('ID')->sortable();
        $grid->column('status.name',trans('task.status_id'));//->sortable();

        if ($this->isComplete<>5){
            $grid->column('assigned',trans('task.Current Task'))->display(function($value) {
                return $this->next_id ? '已派'.$this->next->type->name.'('.$this->next->user->name.')' : '待分派';
            });
        }
        $grid->column('title',trans('task.title'))->limit(50);//->editable('text')
        $grid->column('end_at',trans('task.end_at'))->sortable();//->editable('datetime')
        if ($this->type && $this->type->id){
            $this->getColumnEAV($grid);
        }else{
            $grid->column('type.name',trans('task.type_id'));
        }
//        $grid->column('time_limit',trans('task.time_limit'))->sortable();
        if (Admin::user()->can('tasks.price')){
            $grid->column('price',trans('task.price'))->sortable();
        }
        if (Admin::user()->isAdministrator() || Admin::user()->isLeader()){
            $grid->column('user.name',trans('task.user_id'));
        }
//        $grid->column('created_at',trans('task.created_at'))->sortable();
//        $grid->column('updated_at',trans('task.updated_at'))->sortable();
    }

    public function getColumnEAV($grid)
    {
        $attributes=Attribute::where('type_id','=',$this->type->id)
            ->orWhere('type_id','=',$this->type->root_id)->get();
        $grid->model()->where('type_id','=',$this->type->id);
        foreach ($attributes as $attribute) {
            if (!$attribute->not_list){
                $gData=$grid->column($attribute->frontend_label)
                    ->display(function () use ($attribute) {
                        $values = $this->value->merge($this->rootValue)->where('attribute_id',$attribute->id);
//                        dd($this->value->merge($this->rootValue)->where('attribute_id',536)->first()->getFieldHtml('sdsa'));
                        $value = $values->first() ? $values->first()->getFieldHtml($attribute->list_field_html) : '';
                        return $value;
                    });//->editable($attribute->frontend_input)
                if ($attribute->frontend_input=='text' && !$attribute->list_field_html){
                    $gData->limit(50);
                }
            }
        }
    }

    public function getActions($grid)
    {
        $grid->disableCreateButton();
        if(!Admin::user()->isAdministrator()){
            $grid->actions(function ($actions) {
                $actions->disableDelete();
            });
        }
    }

    public function getTools($grid)
    {
        if(!Admin::user()->isAdministrator()){
            $grid->tools(function ($tools) {
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        }
        if ($this->type && $this->type->next){
            $assigned_to = (Admin::user()->assignableUser());
            unset($assigned_to[Admin::user()->id]);
            $userAss = Administrator::find($this->type->assigned_to);
            if ($userAss){
                $assigned_to[$userAss->id] = $userAss->name;
            }
            $grid->setActionAttrs($this->type->next->name,$assigned_to,$this->type->assigned_to);
            $grid->tools(function ($tools) {
                $tools->batch(function ($batch) {
                    $batch->add($this->type->next->name, new Grid\Tools\BatchWorkflow($this->type->next->id));
                });
            });
        }
    }

    public function getFilter($grid)
    {
        $grid->filter(function ($filter)  {
            $filter->disableIdFilter();
            $filter->like('title',trans('task.title'));
            if (!$this->type){
                $filter->in('type_id',trans('task.type_id'))->multipleSelect(Type::where('root_id',Input::get('type'))->pluck('name','id'));
            }
            $filter->in('status_id',trans('task.status_id'))->multipleSelect(Status::all()->pluck('name','id'));
            $filter->in('user_id',trans('task.user_id'))->multipleSelect(Admin::user()->assignableUser());
            $filter->between('end_at',trans('task.end_at'))->datetime();
            $filter->between('created_at',trans('task.created_at'))->datetime();
            $filter->between('updated_at',trans('task.updated_at'))->datetime();
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {
//            $typeName=trans('task.Tasks');
            $typeName=$this->task->type->name;
            $this->type=$this->task->type;
            $content->header(trans('task.Edit').$typeName);
            $content->description('...');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create(Request $request)
    {
        $typeId = (int)$request->input('type');
        return Admin::content(function (Content $content) use ($typeId) {
//            $typeName=trans('task.Tasks');
            $typeName=$this->type->name;
            $content->header(trans('task.Create').$typeName);
            $content->description('...');
            $content->body($this->form());
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Task::class, function (Form $form) {
//            $form->display('id', 'ID');
            $this->getFieldForm($form);
            $form->builder()->getTools()->disableListButton();
            $this->task ? $this->getOnSaveForm($form) : null;
        });
    }

    public function getFieldForm($form)
    {
        $form = $form->tab($this->type->name, function ($form) {
            if(!$this->task){
                $form->hidden('user_id', trans('task.user_id'))->value(Admin::user()->id);
            }
            $form->hidden('type_id', trans('task.type_id'))->value($this->type->id);
            $form->hidden('type', trans('task.type_id'))->value($this->type->id);
            $form->text('title', trans('task.title'))->attribute('required','required')
                ->placeholder(trans('task.Please Enter...'))->rules('required');
            $form->decimal('time_limit', trans('task.time_limit'));
            if (Admin::user()->can('tasks.price')){
                $form->currency('price', trans('task.price'))->symbol('￥');
            }
            $form->datetime('end_at', trans('task.end_at'))->default(Carbon::now())->rules('required');
            $form->display('created_at', trans('task.created_at'));
            $isReadOnly = false;//$this->task ? !(Admin::user()->id==$this->task->user_id || Admin::user()->isAdministrator()):false;
            $this->getEAVFieldForm($form,$this->task,$this->type,$isReadOnly);
            $this->getStatusField($form);
        });
        foreach ($this->lastTasks as $lastTask) {
            if ($lastTask->value->toArray()){
                $form->tab($lastTask->type->name, function ($form) use ($lastTask) {
                    $this->getEAVFieldForm($form,$lastTask,$lastTask->type,true);
                });
            }
        }
    }

    public function getStatusField($form)
    {
        $form->divide();
        if($this->task){// && $this->type->is_approvable
            $form->display('status_id', trans('task.status_id'))->with(function ($value) {
                $status = Status::find($value);
                return $status ? $status->name : '';
            });
            if ($this->task && Admin::user()->id==$this->task->user_id && !$this->type->next_id){
                $statusLabel = trans('task.Submit');
                $states = ['on' => ['value' => 2, 'text' => trans('task.Processing'), 'color' => 'warning'],
                    'off'  => ['value' => 5, 'text' => trans('task.Complete'), 'color' => 'success'],];
            }elseif ($this->task && Admin::user()->id==$this->task->user_id && $this->type->is_approvable){
                $statusLabel = trans('task.Submit').trans('task.leader').trans('task.Review');
                $states = ['on'  => ['value' => 8, 'text' => trans('task.Review'), 'color' => 'success'],
                    'off' => ['value' => 2, 'text' => trans('task.TempSave'), 'color' => 'warning'],];
            }elseif ($this->task && Admin::user()->id==$this->task->user_id && !$this->type->is_approvable && $this->type->assigned_to){
                $statusLabel = trans('task.Submit');
                $states = ['on' => ['value' => 2, 'text' => trans('task.Processing'), 'color' => 'warning'],
                    'off'  => ['value' => 6, 'text' => trans('task.Approve'), 'color' => 'success'],];
            }elseif ($this->task && Admin::user()->id==$this->task->user_id && !$this->type->is_approvable){
                $statusLabel = trans('task.Submit');
                $states = ['on' => ['value' => 4, 'text' => trans('task.Cancel'), 'color' => 'danger'],
                    'off'  => ['value' => 2, 'text' => trans('task.Processing'), 'color' => 'success'],];
            }else{
                $statusLabel = trans('task.Review');
                $states = ['on'  => ['value' => 7, 'text' => trans('task.Disapprove'), 'color' => 'danger'],
                    'off' => ['value' => 6, 'text' => trans('task.Approve'), 'color' => 'success'],];
            }
            $form->switch('status_id', $statusLabel)->states($states)->value($states['off']['value']);
        } else {
//            $form->select('status_id', trans('task.status_id'))->options(Status::all()->pluck('name','id'))->rules('required')->attribute('required','required');
            $form->hidden('status_id', trans('task.status_id'))->value(1);
        }
        if ($this->type->is_custom_assignable){
            $form->select('custom_assigned_to', trans('task.assigned_to'))->options(Admin::user()->assignableUser())->attribute('required','required');
            $form->ignore(['custom_assigned_to']);
        }
    }

    public function getOnSaveForm($form)
    {
        $form->saving(function ($form) {
            if ($form->model()->status_id==5){
                $error = new MessageBag([
                    'title'   => '提交失败',
                    'message' => '已完成任务无法修改，请联系系统管理员！',
                ]);
                return back()->with(compact('error'));
            }
        });
        $form->saved(function ($form) {
            $message = '';
            $input = Input::all();
            if (isset($input['custom_assigned_to'])){
                if ($form->model()->saveAssign($input['custom_assigned_to'],'提交请求')){
                    $message .= '您的任务分配成功！（'.$form->model()->type->next->name.'）';
                }else{
                    $message .= '您的任务分配失败！';
                }
            }else{
                if ($form->model()->status_id==6){
                    $message .= $form->model()->title.'当前状态为'.$form->model()->status->name;
                    if ($form->model()->type->next){
                        $form->model()->saveAssign($form->model()->type->assigned_to ? $form->model()->type->assigned_to : $form->model()->user_id,'提交请求');
                        $message .= '! 系统将自动分配到下一个任务环节（'.$form->model()->type->next->name.'）！';
                    }
                }
                if (!$form->model()->type->next_id && $form->model()->status_id==5){
                    $lastTasks = $form->model()->saveComplete($this->task);
                    $message .= '当前任务流已最终完成，相关子任务已锁定为完成状态，不可修改!';
                }
            }
            $success = new MessageBag([
                'title'   => '任务'.$form->model()->status->name.'！',
                'message' => $message,
            ]);
            if ($message){
                return back()->with(compact('success'));//redirect('/admin/users')
            }
        });
    }

    public function getEAVFieldForm($form,$task,$type,$readOnly=false)
    {
        foreach ($type->attribute->sortBy('orderby')->toArray() as $attribute) {
            if (!$readOnly){
                $form->hidden('value['.$attribute['id'].'][attribute_id]')->value($attribute['id']);
                $attField = $form->{$attribute['frontend_input']}(
                    'value['.$attribute['id'].'][task_value]',$attribute['frontend_label']);
                if($attribute['frontend_input'] == 'select') {
                    $option = explode('|',$attribute['option']);
                    $attField = $attField->options(array_combine($option,$option));
                }
                if($attribute['is_required']) {
                    $attField = $attField->attribute('required','required');
                }
                if($task){
                    $value=$task->value->where('attribute_id','=',$attribute['id'])->first();
                    $attField = $value && $value->task_value ? $attField->value($value->task_value) : $attField;
                }
            }else{
                $value=$task->value->where('attribute_id','=',$attribute['id'])->first();
                $displayValue = $form->display('value'.$attribute['id'],$attribute['frontend_label']);
                if ($value){
                    $value = $value->getFieldHtml($attribute['form_field_html']);
                    $displayValue->with(function () use ($value) {
                        return $value;
                    });
                }
            }
        }
    }

    public function displayLastTask($task)
    {
        if ($task && $task->root_id && $task->last){
            $this->lastTasks[] = $task->last;
            return $this->displayLastTask($task->last);
        }
    }

    public function reportEav()
    {
        return Admin::content(function (Content $content) {
            $content->header(trans('task.Reports'));
            $content->description('...');
            $content->body(Admin::grid(Task::class, function (Grid $grid) {
                if (!Admin::user()->isAdministrator()){
                    $userIds = Administrator::where('leader_id',Admin::user()->id)->get()->pluck('id')->toArray();
                    $userIds[] = Admin::user()->id;
                    $grid->model()->whereIn('user_id',$userIds);
                }
                $this->getReportColumns($grid);
                $grid->disableRowSelector();
                $grid->disableCreateButton();
                $grid->disableActions();
                $this->getFilter($grid);
            }));
        });
    }

    public function getReportColumns($grid)
    {
        $typeId = Input::get('type');
        $grid->id('ID')->sortable();
        $grid->column('status.name',trans('task.status_id'));//->sortable();
        $grid->column('current.type_id','当前阶段')->display(function ($type_id) {
            $type = $this->type->find($type_id);
            return $type ? $type->name : $this->type->find(Input::get('type'))->name;
        });
        if (Admin::user()->can('tasks.price')){
            $grid->column('price',trans('task.price'))->sortable();
        }
        if (Admin::user()->isAdministrator() || Admin::user()->isLeader()){
            $grid->column('user.name',trans('task.user_id'));
        }
        $grid->column('created_at',trans('task.created_at'))->sortable();
        $grid->column('updated_at',trans('task.updated_at'))->sortable();

        $attributes=Attribute::whereIn('type_id',($this->type->where('root_id',$typeId)->get(['id'])->pluck('id')))->orderBy('orderby')->get();
        $grid->model()->whereNull('root_id')->where('type_id','=',$typeId);
        foreach ($attributes as $attribute) {
            if (!$attribute->not_list){
                $gData=$grid->column($attribute->frontend_label)
                    ->display(function () use ($attribute) {
                        $values = $this->allValue->where('attribute_id',$attribute->id);
                        $value = $values->first() ? $values->first()->getFieldHtml($attribute->list_field_html) : '';
                        return $value;
                    });
                if ($attribute->frontend_input=='text' && !$attribute->list_field_html){
                    $gData->limit(50);
                }
            }
        }
    }

    public function reportStatistic()
    {
        $this->updateSchema(Input::get('type'));
//        dd(Carbon::now()->startOfYear()->toDateTimeString());
        return Admin::content(function (Content $content) {
            $typeId = Input::get('type');
            $from = Input::get('from') ? Input::get('from') : date("Y-m-d", (time()-3600*24*30)).' 00:00:00';
            $to = Input::get('to') ? Input::get('to') : date("Y-m-d H:m:s", time());
//        $typeList = $this->type->where('root_id',Input::get('type'))->get()->pluck('name','id');

            $html = $this->toTable($this->statusReportSelect(
                Carbon::now()->startOfWeek()->toDateTimeString(),Carbon::now()->endOfWeek()->toDateTimeString()),'本周项目汇总：');
            $html .= $this->toTable($this->statusReportSelect(
                Carbon::now()->startOfMonth()->toDateTimeString(),Carbon::now()->endOfMonth()->toDateTimeString()),'本月项目汇总：');
            $html .= $this->toTable($this->statusReportSelect(
                Carbon::now()->startOfYear()->toDateTimeString(),Carbon::now()->endOfYear()->toDateTimeString()),'本年项目汇总：');

            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfWeek()->toDateTimeString(),Carbon::now()->endOfWeek()->toDateTimeString(),'<>'),'团队本周待完成项目：');
            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfMonth()->toDateTimeString(),Carbon::now()->endOfMonth()->toDateTimeString(),'<>'),'团队本月待完成项目：');
            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfYear()->toDateTimeString(),Carbon::now()->endOfYear()->toDateTimeString(),'<>'),'团队本年待完成项目：');

            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfWeek()->toDateTimeString(),Carbon::now()->endOfWeek()->toDateTimeString()),'团队本周完成项目：');
            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfMonth()->toDateTimeString(),Carbon::now()->endOfMonth()->toDateTimeString()),'团队本月完成项目：');
            $html .= $this->toTable($this->userReportSelect(
                Carbon::now()->startOfYear()->toDateTimeString(),Carbon::now()->endOfYear()->toDateTimeString()),'团队本年完成项目：');

            $html .= $this->toTable(DB::select('SELECT users.`name` as user_id, types.`name` AS `Current Task`, Sum(report2.price) AS price, Sum(report2.time_limit) AS time_limit, Count(report2.id) AS Sum FROM report2 INNER JOIN types ON report2.type_id = types.id INNER JOIN users ON users.id = report2.user_id WHERE 	report2.status_id <> 5 GROUP BY 	report2.user_id, report2.type_id ORDER BY report2.user_id '),'未完成项目所处阶段：');


            $content->header(trans('task.Reports').trans('task.Statistics'));
            $content->description('...');
            $content->body($html);
        });
    }

    public function statusReportSelect($from,$to)
    {
        return DB::select('SELECT statuses.`name` as status_id, Sum(report2.price) AS price, Sum(report2.time_limit) AS time_limit, Count(report2.id) AS Sum FROM report2 INNER JOIN statuses ON statuses.id = report2.status_id WHERE report2.created_at >= \''.$from.'\' and report2.created_at < \''.$to.'\' group by report2.type_id');
    }

    public function userReportSelect($from,$to,$done='=')
    {
        return DB::select('SELECT admin_users.`name` as user_id, Sum(report2.price) AS price, Sum(report2.time_limit) AS time_limit, Count(report2.id) AS Sum FROM report2 INNER JOIN admin_users ON admin_users.id = report2.user_id  WHERE report2.status_id'.$done.'5 and report2.created_at >= \''.$from.'\' and report2.created_at < \''.$to.'\' group by report2.user_id');
    }

    public function toTable($taskArray,$title='')
    {
        $tableHtml = '';
        if ($taskArray){
            $tableHtml .= '<div class="col-md-4"><div class="box"><div class="box-header"><h3 class="box-title">'.$title.'</h3></div><div class="box-body no-padding">';
            $tableHtml .= '<table class="table"><thead><tr>';
            foreach($taskArray[0] as $tableHeader=>$notUsed){
                $header = \Lang::has('task.'.$tableHeader) ? trans('task.'.$tableHeader) : $tableHeader;
                $tableHtml .= '<th>'.$header.'</th>';
            }
            $tableHtml .= '</tr></thead>';

            foreach($taskArray as $tableData){
                $tableHtml .= '<tr>';
                foreach($tableData as $tkey=>$tValue){
                    $tableHtml .= '<td>'.$tValue.'</td>';
                }
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</table></div></div></div>';
        }
        return $tableHtml;
    }

    public function reportSchema()
    {
//        dd(Carbon::now()->diffInDays(Carbon::parse('2018-05-25 18:23:59')));
        return Admin::content(function (Content $content) {
            $typeName=$this->type->name ? $this->type->name : trans('task.Tasks');
            $content->header($typeName.trans('task.Reports'));
            $content->description('...');
            $content->body(Admin::grid(Report::class, function (Grid $grid) {
                $typeId = Input::get('type');
                $this->updateSchema($typeId);
                $adminUser = Admin::user();
                if (!$adminUser->isAdministrator()){
                    $userIds = Administrator::where('leader_id',$adminUser->id)->get()->pluck('id')->toArray();
                    $userIds[] = $adminUser->id;
                    $grid->model()->whereIn('user_id',$userIds);
                }

                $grid->id('ID')->sortable();
                if ($adminUser->isAdministrator() || $adminUser->isLeader()){
                    $grid->column('user.name',trans('task.user_id'));
                }
//                $grid->column('status.name',trans('task.status_id'));//->sortable();
                $grid->column('type.name',trans('task.Current Task'))->display(function($value) {
                    return '<span class="label label-default" style="color: '.$this->type['color'].';" >'.$value.'</span>';
                });
                $grid->column('created_at',trans('task.created_at'))->sortable();
                $grid->column('time_limit',trans('task.time_limit'))->sortable();
                $grid->column('end_at',trans('task.Time').trans('task.Rate'))->display(function($value) {
                    $totalDays = Carbon::parse($this->created_at)->diffInDays($value,false);
                    $pastDays = Carbon::now()->diffInDays($this->created_at);
                    $leftDays = ($totalDays - $pastDays);
                    $leftHtml = $leftDays>0 ? '剩余'.$leftDays.'天' : '逾期'.(-$leftDays).'天';
                    $percentage = number_format($pastDays/$totalDays*100);
                    $percentage = $percentage>100 ? 100 : $percentage;
                    $statusHtml =  $pastDays/$totalDays>1 ? 'danger' : 'info';
                    if ($this->status_id==5){
                        $statusHtml =  'success';
                        $leftDays = trans('task.Complete');
                        $percentage = 100;
                    }
                    $progressHtml = '
                        <div class="progress">
                            <div class="progress-bar progress-bar-'.$statusHtml.'" role="progressbar"
                                 aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"
                                 style="width: '.$percentage.'%;">
                                <span class="sr-only-" title="'.$leftHtml.'">'.$leftDays.'</span>
                            </div>
                        </div>';
                    return $progressHtml;
                });
                if (Admin::user()->can('tasks.price')){
                    $grid->column('price',trans('task.price'))->sortable();
                }

                $attributes=$this->getAttrs4Report($typeId);
                foreach($attributes as $attr) {
                    if (!$attr->not_list){
                        $gData = $grid->column('attr'.$attr->id, $attr->frontend_label)->display(function ($value) use ($attr) {
                            return $attr->getListHtml($value);
                        })->sortable();
                        if ($attr->frontend_input=='text' && !$attr->list_field_html){
                            $gData->limit(50);
                        }
                    }
                }
//                $grid->column('title',trans('task.title'))->limit(30);//->editable('text')

                $grid->disableRowSelector();
                $grid->disableCreateButton();
                $grid->actions(function ($actions) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                });
//                $grid->disableActions();
                $grid->filter(function ($filter) use ($attributes)  {
                    $filter->disableIdFilter();
                    $filter->in('type_id',trans('task.type_id'))->multipleSelect(Type::where('root_id',Input::get('type'))->pluck('name','id'));
                    $filter->in('status_id',trans('task.status_id'))->multipleSelect(Status::all()->pluck('name','id'));
                    $filter->in('user_id',trans('task.user_id'))->multipleSelect(Admin::user()->assignableUser());
                    $filter->between('created_at',trans('task.created_at'))->datetime();
                    foreach($attributes->where('is_filter',1) as $attr) {
                        if($attr['frontend_input'] == 'select') {
                            $option = explode('|',$attr['option']);
                            $filter->equal('attr'.$attr->id, $attr->frontend_label)->select(array_combine($option,$option));
                        } elseif ($attr['frontend_input'] == 'date') {
                            $filter->between('attr'.$attr->id, $attr->frontend_label)->datetime();
                        } else {
                            $filter->like('attr'.$attr->id, $attr->frontend_label);
                        }
                    }
                });
                $grid->exporter(new TaskExporter());
            }));
        });
//        \DB::enableQueryLog();
//        dd(Report::all()->toArray());
//        $dbh->query("DESCRIBE tablename")->fetchAll();
    }

    public function updateSchema($typeId)
    {
        $tableName = 'report'.$typeId;

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function($table) use ($typeId)
            {
                $table->increments('id');
                $table->string('title')->comment(trans('task.title'));
                $table->decimal('time_limit')->nullable()->comment(trans('task.time_limit'));
                $table->decimal('price')->nullable()->comment(trans('task.price'));
                $table->dateTime('end_at')->nullable()->comment(trans('task.end_at'));
                $table->integer('user_id')->comment(trans('task.user_id'));
                $table->integer('status_id')->comment(trans('task.status_id'));
                $table->integer('type_id')->comment(trans('task.type_id'));
                $table->timestamps();
                $attributes=$this->getAttrs4Report($typeId);
                foreach($attributes as $attr) {
                    if (!$attr->not_report){
                        $table->string('attr'.$attr->id)->nullable()->comment($attr->frontend_label);
                    }
                }
            });
        } else {
            Schema::table($tableName, function($table) use ($typeId)
            {
                $attributes=$this->getAttrs4Report($typeId);
                foreach($attributes as $attr) {
                    if (!$attr->not_report && !Schema::hasColumn($table->getTable(),'attr'.$attr->id)){
                        $table->string('attr'.$attr->id)->nullable()->comment($attr->frontend_label);
                    }
                }
            });
        }
        $this->setReportData($typeId);
    }

    public function setReportData($typeId)
    {
        $tableName = 'report'.$typeId;
        DB::table($tableName)->whereIn('id',Task::onlyTrashed()->where('type_id',$typeId)->get(['id'])->pluck('id')->toArray())->delete();
        $hasTasks = DB::table($tableName)->join('tasks', $tableName.'.id', '=', 'tasks.id')
            ->whereRaw($tableName.'.updated_at>tasks.updated_at')->get([$tableName.'.id'])->pluck('id');
        $tasks = $this->task->whereNull('root_id')->where('type_id','=',$typeId)->whereNotIn('id',$hasTasks)->with('current')
            ->with('allValue')->get(['id','title','time_limit','end_at','price','user_id','status_id','type_id','created_at'])->toArray();
        foreach ($tasks as $key=>$task) {
            if (isset($task['all_value'])) {
                foreach ($task['all_value'] as $item) {
                    $tasks[$key]['attr'.$item['attribute_id']]=$item['task_value'];
                }
                $tasks[$key]['updated_at'] = Carbon::now();
                $tasks[$key]['type_id'] = isset($tasks[$key]['current']) ? $tasks[$key]['current']['type_id'] : $tasks[$key]['type_id'];
                unset($tasks[$key]['all_value']);
                unset($tasks[$key]['current']);
            }
            DB::table($tableName)->updateOrInsert(['id'=>$tasks[$key]['id']],$tasks[$key]);
        }
    }

    public function getAttrs4Report($typeId)
    {
        return Attribute::whereIn('type_id',($this->type->where('root_id',$typeId)->get(['id'])->pluck('id')))->orderBy('orderby')->get();
    }

    public function destroy($id)
    {
        if ($this->delete($id)) {
            return response()->json([
                'status'  => true,
                'message' => trans('admin.delete_succeeded'),
            ]);
        } else {
            return response()->json([
                'status'  => false,
                'message' => trans('admin.delete_failed'),
            ]);
        }
    }

    public function delete($id)
    {
        $ids = explode(',', $id);

        foreach ($ids as $id) {
            if (empty($id)) {
                continue;
            }
            $values = $this->task->find($id)->value;
            foreach($values as $value){
                $value->delete();
            }
            $this->task->find($id)->delete();
        }

        return true;
    }
}
