<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title">tttt</h3>

        <div class="box-tools">
        </div>
    </div>
    <!-- /.box-header -->
    <!-- form start -->
        <div class="box-body">
                <div class="fields-group">
                    @foreach($form as $field)
                        {!! $field->render() !!}
                    @endforeach
                </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="col-md-">
                {{--label--}}
            </div>
            <div class="col-md-">
                {{--submitButton--}}
            </div>

        </div>
</div>

