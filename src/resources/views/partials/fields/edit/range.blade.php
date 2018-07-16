@extends('laramie::partials.fields.edit._base')

@php
    $min = object_get($field, 'min', 0);
    $max = object_get($field, 'max', 100);
    $step = object_get($field, 'step', 1);
@endphp

@section('input')
    <div class="columns">
        <div class="column is-5">
            <input type="{{ $field->type }}" oninput="$(this).closest('.field').find('.value-display').text($(this).val())" onchange="$(this).closest('.field').find('.value-display').text($(this).val())" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="is-fullwidth input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ object_get($item, $fieldKey) }}" {{ $field->extra }} {{ $field->required ? 'required' : '' }}>
        </div>
        <div class="column is-1">
            <span class="value-display tag is-medium is-light">{{ object_get($item, $fieldKey, '--') }}</span>
        </div>
    </div>
@overwrite
