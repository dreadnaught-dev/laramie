@extends('laramie::partials.fields.edit._base')

@php
    $field->label = $field->label . ' <a class="tag is-italic has-text-weight-normal js-set-timestamp-now">use now</a>&nbsp;<a class="tag has-text-danger is-italic has-text-weight-normal js-clear-timestamp">clear</a>';
    $min = object_get($field, 'min', 0);
    $max = object_get($field, 'max', 100);
    $step = object_get($field, 'step', 1);
    $timezones = \Laramie\Lib\LaramieHelpers::getTimezones();
    $selectedTimezone = object_get($item, $fieldKey.'.timezone');
@endphp

@section('input')
    <div class="columns">
        <div class="column is-4">
            <input type="date" class="is-fullwidth input is-date is-timestamp" id="{{ $field->id }}-date" name="{{ $field->id }}-date" value="{{ object_get($item, $fieldKey.'.date') }}" {{ object_get($field, 'extraDate') }} {{ $field->required ? 'required' : '' }}>
        </div>
        <div class="column is-4">
            <input type="time" class="is-fullwidth input is-time is-timestamp" id="{{ $field->id }}-time" name="{{ $field->id }}-time" value="{{ object_get($item, $fieldKey.'.time') }}" step="1" {{ object_get($field, 'extraTime') }} {{ $field->required ? 'required' : '' }}>
        </div>
        <div class="column is-4">
            <span class="select">
                <select class="timezone-timestamp" id="{{ $field->id }}-timezone" name="{{ $field->id }}-timezone" {{ object_get($field, 'extraTimezone') }} {{ $field->required ? 'required' : '' }}>
                    <option value="" disabled selected>Select timezone...</option>
                    {!! $timezones->map(function($timezone) use($selectedTimezone) { return sprintf('<option value="%s" %s>(%s) %s</option>', $timezone->timezone, ($timezone->timezone == $selectedTimezone ? 'selected' : ''), $timezone->prettyOffset, $timezone->timezone); })->implode('') !!}
                </select>
            </span>
        </div>
    </div>
@overwrite
