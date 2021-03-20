@extends('laramie::partials.fields.edit._base')

@section('input')
    <textarea
        class="textarea"
        id="{{ $field->id }}"
        name="{{ $field->id }}"
        {!! $field->extra !!}
        {{ $field->isRequired ? 'required' : '' }}
    >{{ $valueOrDefault }}</textarea>
@overwrite
