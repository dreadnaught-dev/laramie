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
    @if (in_array($field->type, $allowedInputTypes))
        <input type="{{ $field->type }}" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ object_get($item, $field->id) }}" {!! $field->extra !!} {{ $field->required ? 'required' : '' }}>
    @else
        Input type is is not yet implemented ({{ $field->type }})
    @endif
@overwrite
