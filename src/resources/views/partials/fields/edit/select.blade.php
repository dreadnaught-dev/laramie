@extends('laramie::partials.fields.edit._base')

@php
    $isMultiple = object_get($field, 'isMultiple', false);
    $tmp = object_get($item, $fieldKey, null);
    $selectedValues = array_filter(is_array($tmp) ? $tmp : [$tmp]);
@endphp

@section('input')
    <div class="select {{$isMultiple ? 'is-multiple' : ''}}">
        <select id="{{ $field->id }}" name="{{ $field->id . ($isMultiple ? '[]' : '') }}" {{ $field->extra }} {{ $field->required ? 'required' : '' }} {{ $isMultiple ? 'multiple' : '' }}>
            @if (!$isMultiple && !$field->required)
                <option value="">Select {{ strtolower($field->label) }}...</option>
            @endif
            @foreach (object_get($field, 'options') as $option)
                <option value="{{ $option }}" {!! in_array($option, $selectedValues) ? 'selected="selected"' : '' !!}>{{ $option }}</option>
            @endforeach
        </select>
    </div>
@overwrite
