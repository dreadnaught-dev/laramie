@extends('laramie::partials.fields.edit._base')

@section('input')
    @foreach ($field->getOptions() as $option)
        <label class="radio">
            <input type="radio" id="{{ $field->getId() }}-{{ $loop->index }}" name="{{ $field->getId() }}" value="{{ data_get($option, 'value') }}" {!! data_get($option, 'value') == data_get($item, $fieldKey) ? 'checked' : '' !!}>
            {{ data_get($option, 'text') }}
        </label>
    @endforeach
@overwrite
