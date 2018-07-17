@extends('laramie::partials.fields.edit._base')

@section('input')
    <span class="select">
        <select id="{{ $field->id }}" name="{{ $field->id }}" {{ $field->extra }} {{ $field->required ? 'required' : '' }}>
            @if (!$field->required)
                <option value="">Select {{ strtolower($field->label) }}...</option>
            @endif
            @foreach (object_get($field, 'options') as $option)
                <option value="{{ $option }}" {!! $option == object_get($item, $fieldKey) ? 'selected="selected"' : '' !!}>{{ $option }}</option>
            @endforeach
        </select>
    </span>
@overwrite
