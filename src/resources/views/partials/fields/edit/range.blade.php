@extends('laramie::partials.fields.edit._base')

@php
    $min = $field->getMin() ?: 0;
    $max = $field->getMax() ?: 100;
    $step = $field->getStep() ?: 1;
@endphp

@section('input')
    <div class="columns">
        <div class="column is-5">
            <input type="{{ $field->getType() }}" oninput="$(this).closest('.field').find('.value-display').text($(this).val())" onchange="$(this).closest('.field').find('.value-display').text($(this).val())" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="is-fullwidth input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ data_get($item, $fieldKey) }}" {!! $field->getExtra() !!} {{ $field->isRequired() ? 'required' : '' }}>
        </div>
        <div class="column is-1">
            <span class="value-display tag is-medium is-light">{{ data_get($item, $fieldKey, '--') }}</span>
        </div>
    </div>
@overwrite
