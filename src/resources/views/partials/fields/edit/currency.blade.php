@php
    $hasError = $errors->has($field->getId());

    $isIntegerOnly = $field->isIntegerOnly();
    $min = $field->getMin();
    $max = $field->getMax();
    $step = $isIntegerOnly ? 1 : $field->getStep();

    $sign = $field->getSign() ?: 'dollar';
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}">
    <label class="label" for="{{ $fieldKey }}">{!! $field->getLabel() !!}</label>
    <div class="control has-icons-left {{ $hasError ? 'has-icons-right' : '' }}">
        <input type="number" class="input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}" {!! $field->getExtra() !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->isRequired() ? 'required' : '' !!}>
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

