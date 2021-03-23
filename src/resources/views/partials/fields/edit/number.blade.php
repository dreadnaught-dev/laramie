@extends('laramie::partials.fields.edit._base')

@php
    $isIntegerOnly = $field->isIntegerOnly();
    $min = $field->getMin();
    $max = $field->getMax();
    $step = $isIntegerOnly ? 1 : $field->getStep();
@endphp

@section('input')
    <input type="{{ $field->getType() }}" class="input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}" {!! $field->getExtra() !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->isRequired() ? 'required' : '' !!}>
@overwrite

