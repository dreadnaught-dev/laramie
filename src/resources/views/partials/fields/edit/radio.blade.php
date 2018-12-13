@extends('laramie::partials.fields.edit._base')

@section('input')
    @foreach (object_get($field, 'options') as $option)
        <label class="radio">
            <input type="radio" id="{{ $field->id }}-{{ $loop->index }}" name="{{ $field->id }}" value="{{ object_get($option, 'value') }}" {!! object_get($option, 'value') == object_get($item, $fieldKey) ? 'checked' : '' !!}>
            {{ object_get($option, 'text') }}
        </label>
    @endforeach
@overwrite
