
@foreach($forms as $pk => $form)
    @foreach($form->fields() as $field)
        {!! $field->render() !!}
    @endforeach
@endforeach