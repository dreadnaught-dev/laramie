@php
    $hasError = $errors->has($fieldKey);
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}">
    <p class="control {{ $hasError ? 'has-icons-right' : '' }}">
        <label class="checkbox">
            <input type="hidden" name="{{ $field->getId() }}" value="0">
            <input type="checkbox" class="is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="1" {{ data_get($item, $fieldKey) ? 'checked' : '' }} {!! $field->getExtra() !!} {{ $field->isRequired() ? 'required' : '' }}>
            {{ $field->getLabel() }}
            @if ($hasError)
            <span class="icon is-small is-right">
              <i class="fas fa-exclamation-triangle"></i>
            </span>
            @endif
        </label>
    </p>
    @include('laramie::partials.fields.edit._errors')
</div>
