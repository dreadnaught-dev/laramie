@extends('laramie::partials.fields.edit._base')

@section('input')
    @foreach (data_get($field, 'options') as $option)
        <label class="radio">
            <input type="radio" id="{{ $field->id }}-{{ $loop->index }}" name="{{ $field->id }}" value="{{ data_get($option, 'value') }}" {!! object_get($option, 'value') == object_get($item, $fieldKey) ? 'checked' : '' !!}>
            {{ data_get($option, 'text') }}
        </label>
    @endforeach
@overwrite
