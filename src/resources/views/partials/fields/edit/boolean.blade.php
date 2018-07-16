@extends('laramie::partials.fields.edit._base')

@php
    $isTrue = object_get($item, $fieldKey) === true;
    $isFalse = object_get($item, $fieldKey) === false;
@endphp

@section('input')
    <label class="radio">
        <input type="radio" id="{{ $field->id }}-yes" name="{{ $field->id }}" value="1" {{ $isTrue ? 'checked' : '' }}>
        Yes
    </label>
    <label class="radio">
        <input type="radio" id="{{ $field->id }}-no" name="{{ $field->id }}" value="0" {{ $isFalse ? 'checked' : '' }}>
        No
    </label>
@overwrite

