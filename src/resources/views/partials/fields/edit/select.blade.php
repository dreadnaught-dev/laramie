@extends('laramie::partials.fields.edit._base')

@php
    $asRadio = data_get($field, 'asRadio', false);
    $isSelect2 = data_get($field, 'isSelect2', false);
    $isMultiple = data_get($field, 'isMultiple', false);
    $selectedValues = array_filter(is_array($valueOrDefault) ? $valueOrDefault : [$valueOrDefault]);
@endphp

@section('input')
    @if ($asRadio)
        @foreach (data_get($field, 'options') as $option)
            @if ($isMultiple)
                <label class="checkbox-label label has-text-weight-normal"><input type="checkbox" name="{{ $field->id . '[]' }}" value="{{ data_get($option, 'value') }}" {!! in_array(object_get($option, 'value'), $selectedValues) ? 'checked="checked"' : '' !!}>&nbsp;{{ object_get($option, 'text') }}</label>
            @else
                <label class="radio-label label has-text-weight-normal"><input type="radio" name="{{ $field->id }}" value="{{ data_get($option, 'value') }}" {!! in_array(object_get($option, 'value'), $selectedValues) ? 'checked="checked"' : '' !!}>&nbsp;{{ object_get($option, 'text') }}</label>
            @endif
        @endforeach
    @else
        <div class="select {{$isMultiple ? 'is-multiple is-fullwidth' : ''}}">
            <select id="{{ $field->id }}" name="{{ $field->id . ($isMultiple ? '[]' : '') }}" {!! $isSelect2 ? 'class="select2"' : '' !!} {!! $field->extra !!} {{ $field->required ? 'required' : '' }} {{ $isMultiple ? 'multiple' : '' }}>
                @if (!$isMultiple && !$field->required)
                    <option value="">Select {{ strtolower($field->label) }}...</option>
                @endif
                @foreach (data_get($field, 'options') as $option)
                    <option value="{{ data_get($option, 'value') }}" {!! in_array(object_get($option, 'value'), $selectedValues) ? 'selected="selected"' : '' !!}>{{ object_get($option, 'text') }}</option>
                @endforeach
            </select>
        </div>
    @endif
@overwrite
