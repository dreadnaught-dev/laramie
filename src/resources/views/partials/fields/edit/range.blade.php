@extends('laramie::partials.fields.edit._base')

@php
    $min = data_get($field, 'min', 0);
    $max = data_get($field, 'max', 100);
    $step = data_get($field, 'step', 1);
@endphp

@section('input')
    <div class="columns">
        <div class="column is-5">
            <input type="{{ $field->type }}" oninput="$(this).closest('.field').find('.value-display').text($(this).val())" onchange="$(this).closest('.field').find('.value-display').text($(this).val())" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="is-fullwidth input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ data_get($item, $fieldKey) }}" {!! $field->extra !!} {{ $field->isRequired ? 'required' : '' }}>
        </div>
        <div class="column is-1">
            <span class="value-display tag is-medium is-light">{{ data_get($item, $fieldKey, '--') }}</span>
        </div>
    </div>
@overwrite
