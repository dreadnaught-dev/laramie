@extends('laramie::partials.fields.edit._base')

@php
    $asRadio = $field->asRadio();
    $isSelect2 = $field->isSelect2();
    $isMultiple = $field->isMultiple();
    $selectedValues = array_filter(is_array($valueOrDefault) ? $valueOrDefault : [$valueOrDefault]);
@endphp

@section('input')
    @if ($asRadio)
        @foreach ($field->getOptions() as $option)
            @if ($isMultiple)
                <label class="checkbox-label label has-text-weight-normal"><input type="checkbox" name="{{ $field->getId() . '[]' }}" value="{{ data_get($option, 'value') }}" {!! in_array(data_get($option, 'value'), $selectedValues) ? 'checked="checked"' : '' !!}>&nbsp;{{ data_get($option, 'text') }}</label>
            @else
                <label class="radio-label label has-text-weight-normal"><input type="radio" name="{{ $field->getId() }}" value="{{ data_get($option, 'value') }}" {!! in_array(data_get($option, 'value'), $selectedValues) ? 'checked="checked"' : '' !!}>&nbsp;{{ data_get($option, 'text') }}</label>
            @endif
        @endforeach
    @else
        <div class="select {{$isMultiple ? 'is-multiple is-fullwidth' : ''}}">
            <select id="{{ $field->getId() }}" name="{{ $field->getId() . ($isMultiple ? '[]' : '') }}" {!! $isSelect2 ? 'class="select2"' : '' !!} {!! $field->getExtra() !!} {{ $field->isRequired() ? 'required' : '' }} {{ $isMultiple ? 'multiple' : '' }}>
                @if (!$isMultiple && !$field->isRequired())
                    <option value="">Select {{ strtolower($field->getLabel()) }}...</option>
                @endif
                @foreach ($field->getOptions() as $option)
                    <option value="{{ data_get($option, 'value') }}" {!! in_array(data_get($option, 'value'), $selectedValues) ? 'selected="selected"' : '' !!}>{{ data_get($option, 'text') }}</option>
                @endforeach
            </select>
        </div>
    @endif
@overwrite
