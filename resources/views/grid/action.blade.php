
<div class="bg-info hidden assign_to">
    <div class="callout callout-info col-sm-12">
        <div class="form-group col-sm-6">
            <div class="input-group">
                <div class="input-group-addon">
                    <span class="label label-success countSelect"></span></div>
                <input name="title" basevalue="@lang('task.Submit'){{ $action['title'] }}: " class="form-control title_assign" />
            </div>
        </div>

        <div class="form-group col-sm-6">
            <div class="input-group">
                <select class="form-control assignableUser" name="assignableUser" >
                    @foreach($action['assignableUser'] as $userId => $userName)
                        <option value="{{ $userId }}" {{ $action['assigned_to']==$userId ? 'selected':'' }}>
                            {{ $userName }}
                        </option>
                    @endforeach
                </select>
                <div class="input-group-addon btn btn-default assign-submit">@lang('task.Submit')</div>
            </div>
        </div>
    </div>
</div>