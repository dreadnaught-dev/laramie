@php
    $hasError = $errors->has($field->getId());

    $isIntegerOnly = $field->isIntegerOnly();
    $min = $field->getMin();
    $max = $field->getMax();
    $step = $isIntegerOnly ? 1 : $field->getStep();

    $currencyCode = $field->getCurrencyCode() || 'usd';
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}">
    <label class="label" for="{{ $fieldKey }}">{!! $field->getLabel() !!}</label>
    <div class="control has-icons-left {{ $hasError ? 'has-icons-right' : '' }}">
        <input type="number" class="input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}" {!! $field->getExtra() !!} {!! $min !== null ? 'min="'.$min.'"' : '' !!} {!! $max !== null ? 'max="'.$max.'"' : '' !!} {!! $step ? 'step="'.$step.'"' : '' !!} {!! $field->isRequired() ? 'required' : '' !!}>
        <span class="icon is-small is-left">
            {{ $currencyCode }}
        </span>
        @if ($hasError)
        <span class="icon is-small is-right">
            <i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/></svg></i>
        </span>
        @endif
    </div>
    @include('laramie::partials.fields.edit._help-text')
    @include('laramie::partials.fields.edit._errors')
</div>

