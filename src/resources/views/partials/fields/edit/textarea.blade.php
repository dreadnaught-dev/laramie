@extends('laramie::partials.fields.edit._base')

@section('input')
    <textarea
        class="textarea"
        id="{{ $field->getId() }}"
        name="{{ $field->getId() }}"
        {!! $field->getExtra() !!}
        {{ $field->isRequired() ? 'required' : '' }}
    >{{ $valueOrDefault }}</textarea>
@overwrite
