@extends('laramie::partials.fields.edit._base')

@php
    $isTrue = data_get($item, $fieldKey) === true;
    $isFalse = data_get($item, $fieldKey) === false;
@endphp

@section('input')
    <label class="radio">
        <input type="radio" id="{{ $field->getId() }}-yes" name="{{ $field->getId() }}" value="1" {{ $isTrue ? 'checked' : '' }}>
        Yes
    </label>
    <label class="radio">
        <input type="radio" id="{{ $field->getId() }}-no" name="{{ $field->getId() }}" value="0" {{ $isFalse ? 'checked' : '' }}>
        No
    </label>
@overwrite

