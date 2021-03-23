@extends('laramie::partials.fields.edit._base')

@php
    $oldPassword = data_get($item, $field->getId() . '.encryptedValue');
    if ($errors->has($fieldKey)) {
        $oldPassword = null;
    }
@endphp

@section('input')
    <div class="password-wrapper{{ $oldPassword ? ' has-password' : '' }}">
        <label>
            <input type="checkbox" name="_{{ $field->getId() }}" {{ $oldPassword ? 'checked="checked"' : '' }} onchange="$(this).parent().next().toggle()" value="keep"> keep existing password
        </label>
        <div class="password-input">
            <div class="columns">
                <div class="column is-half">
                    <input type="password" autocomplete="new-password" class="input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" {!! $field->getExtra() !!} {{ $field->isRequired() ? 'required' : '' }} placeholder="{{ $field->getLabel() }}">
                </div>
                <div class="column is-half">
                    <input type="password" autocomplete="new-password" class="input is-{{ $field->getType() }}" name="{{ $field->getId() }}_confirmation" placeholder="Confirm {{ strtolower($field->getLabel()) }}">
                </div>
            </div>
        </div>
    </div>
@overwrite

