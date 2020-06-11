@extends('laramie::partials.fields.edit._base')

@php
    $oldPassword = data_get($item, $field->id . '.encryptedValue');
    if ($errors->has($fieldKey)) {
        $oldPassword = null;
    }
@endphp

@section('input')
    <div class="password-wrapper{{ $oldPassword ? ' has-password' : '' }}">
        <label>
            <input type="checkbox" name="_{{ $field->id }}" {{ $oldPassword ? 'checked="checked"' : '' }} onchange="$(this).parent().next().toggle()" value="{{ $oldPassword }}"> keep existing password
        </label>
        <div class="password-input">
            <div class="columns">
                <div class="column is-half">
                    <input type="password" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" {!! $field->extra !!} {{ $field->required ? 'required' : '' }} placeholder="{{ $field->label }}">
                </div>
                <div class="column is-half">
                    <input type="password" class="input is-{{ $field->type }}" name="{{ $field->id }}_confirmation" placeholder="Confirm {{ strtolower($field->label) }}">
                </div>
            </div>
        </div>
    </div>
@overwrite

