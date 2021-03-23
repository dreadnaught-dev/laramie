@extends('laramie::partials.fields.edit._base')

@php
    $field->set('label', $field->getLabel() . ' <a class="tag is-italic has-text-weight-normal js-set-timestamp-now">use now</a>&nbsp;<a class="tag has-text-danger is-italic has-text-weight-normal js-clear-timestamp">clear</a>');
    $min = $field->getMin() ?: 0;
    $max = $field->getMax() ?: 100;
    $step = $field->getStep() ?: 1;
    $timezones = \Laramie\Lib\LaramieHelpers::getTimezones();
    $selectedTimezone = data_get($item, $fieldKey.'.timezone');
@endphp

@section('input')
    <div class="columns">
        <div class="column is-4">
            <input type="date" class="is-fullwidth input is-date is-timestamp" id="{{ $field->getId() }}-date" name="{{ $field->getId() }}-date" value="{{ data_get($item, $fieldKey.'.date') }}" {{ $field->isRequired() ? 'required' : '' }}>
        </div>
        <div class="column is-4">
            <input type="time" class="is-fullwidth input is-time is-timestamp" id="{{ $field->getId() }}-time" name="{{ $field->getId() }}-time" value="{{ data_get($item, $fieldKey.'.time') }}" step="1" {{ $field->isRequired() ? 'required' : '' }}>
        </div>
        <div class="column is-4">
            <span class="select">
                <select class="timezone-timestamp" id="{{ $field->getId() }}-timezone" name="{{ $field->getId() }}-timezone" {{ $field->isRequired() ? 'required' : '' }}>
                    <option value="" disabled selected>Select timezone...</option>
                    {!! $timezones->map(function($timezone) use($selectedTimezone) { return sprintf('<option value="%s" %s>(%s) %s</option>', $timezone->timezone, ($timezone->timezone == $selectedTimezone ? 'selected' : ''), $timezone->prettyOffset, $timezone->timezone); })->implode('') !!}
                </select>
            </span>
        </div>
    </div>
@overwrite
