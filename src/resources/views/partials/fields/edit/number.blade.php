@extends('laramie::partials.fields.edit._base')

@php
    $isIntegerOnly = data_get($field, 'isIntegerOnly', false);
    $min = data_get($field, 'min', null);
    $max = data_get($field, 'max', null);
    $step = $isIntegerOnly ? 1 : data_get($field, 'step', null);
@endphp

@section('input')
    <input type="{{ $field->type }}" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ data_get($item, $field->id) }}" {!! $field->extra !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->required ? 'required' : '' !!}>
@overwrite

