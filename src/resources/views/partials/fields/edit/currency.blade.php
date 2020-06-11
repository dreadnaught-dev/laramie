@php
    $hasError = $errors->has($field->id);

    $isIntegerOnly = data_get($field, 'isIntegerOnly', false);
    $min = data_get($field, 'min', null);
    $max = data_get($field, 'max', null);
    $step = $isIntegerOnly ? 1 : data_get($field, 'step', null);

    $sign = data_get($field, 'sign', 'dollar');
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->type }}">
    <label class="label" for="{{ $fieldKey }}">{!! $field->label !!}</label>
    <div class="control has-icons-left {{ $hasError ? 'has-icons-right' : '' }}">
        <input type="number" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ $valueOrDefault }}" {!! $field->extra !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->required ? 'required' : '' !!}>
        <span class="icon is-small is-left">
            <i class="fas fa-{{ $sign }}-sign"></i>
        </span>
        @if ($hasError)
        <span class="icon is-small is-right">
            <i class="fas fa-exclamation-triangle"></i>
        </span>
        @endif
    </div>
    @include('laramie::partials.fields.edit._help-text')
    @include('laramie::partials.fields.edit._errors')
</div>

