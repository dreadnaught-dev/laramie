@extends('laramie::partials.fields.edit._base')

@section('input')
    <input type="hidden" name="{{ $field->id }}" id="{{ $field->id }}" value="{{ object_get($item, $field->id) }}">
    <trix-editor input="{{ $field->id }}" class="trix-content"></trix-editor>
@overwrite
