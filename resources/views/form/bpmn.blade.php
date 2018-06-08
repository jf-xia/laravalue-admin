@include('admin::form.error')

<div class="content" id="js-drop-zone">
    <div class="message error"></div>
    <div class="canvas box-body" id="js-canvas" style="width: 100%;height: 600px" ></div>
    <div class="properties-panel-parent" id="js-properties-panel"></div>

    <div class="entry" style="float: right;position: relative;bottom: 80px;width: 70px;
right: 11px;z-index: 200;background: white;">
        <a href data-download download="diagram.bpmn"><i class="fa fa-save fa-5x"></i></a>
        <input type="file" data-open-file value="open" name="fileXml" />
        <input type="hidden" id="{{$name}}" name="{{$name}}" value="{{$value}}" class="{{$class}}" {!! $attributes !!} />
    </div>
</div>

@include('admin::form.help-block')
