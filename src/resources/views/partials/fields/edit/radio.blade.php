@extends('laramie::partials.fields.edit._base')

@section('input')
    @foreach (object_get($field, 'options') as $option)
        <label class="radio">
            <input type="radio" id="{{ $field->id }}-{{ $loop->index }}" name="{{ $field->id }}" value="{{ $option }}" {!! $option == object_get($item, $fieldKey) ? 'checked' : '' !!}>
            {{ $option }}
        </label>
    @endforeach
@overwrite
