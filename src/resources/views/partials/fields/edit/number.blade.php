@extends('laramie::partials.fields.edit._base')

@php
    $isIntegerOnly = object_get($field, 'isIntegerOnly', false);
    $min = object_get($field, 'min', null);
    $max = object_get($field, 'max', null);
    $step = $isIntegerOnly ? 1 : object_get($field, 'step', null);
@endphp

@section('input')
    <input type="{{ $field->type }}" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ object_get($item, $field->id) }}" {!! $field->extra !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->required ? 'required' : '' !!}>
@overwrite

