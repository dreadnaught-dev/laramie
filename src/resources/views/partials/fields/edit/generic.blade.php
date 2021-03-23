@extends('laramie::partials.fields.edit._base')

@php
    // List gathered from: https://www.w3schools.com/tags/att_input_type.asp
    $allowedInputTypes = [
        'color',
        'date',
        'datetime-local',
        'email',
        'month',
        'tel',
        'text',
        'time',
        'url',
        'week',
    ];

    /*
        Input types that aren't supported by the generic template, either
        because they'll get their own field type, or because it just doesn't
        make sense to have them:

        'button',
        'checkbox',
        'file',
        'hidden',
        'image',
        'number',
        'password',
        'radio',
        'range',
        'reset',
        'search',
        'submit',
    */
@endphp

@section('input')
    @if (in_array($field->getType(), $allowedInputTypes))
        <input
            type="{{ $field->getType() }}"
            class="input is-{{ $field->getType() }}"
            id="{{ $field->getId() }}"
            name="{{ $field->getId() }}"
            value="{{ $valueOrDefault }}"
            {!! $field->getExtra() !!}
            {{ $field->isRequired() ? 'required' : '' }}
        >
    @else
        Input type is is not yet implemented ({{ $field->getType() }})
    @endif
@overwrite
