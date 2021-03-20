@php
    $hasError = $errors->has($fieldKey);
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->type }}">
    <p class="control {{ $hasError ? 'has-icons-right' : '' }}">
        <label class="checkbox">
            <input type="hidden" name="{{ $field->id }}" value="0">
            <input type="checkbox" class="is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="1" {{ data_get($item, $fieldKey) ? 'checked' : '' }} {!! $field->extra !!} {{ $field->isRequired ? 'required' : '' }}>
            {{ $field->label }}
            @if ($hasError)
            <span class="icon is-small is-right">
              <i class="fas fa-exclamation-triangle"></i>
            </span>
            @endif
        </label>
    </p>
    @include('laramie::partials.fields.edit._errors')
</div>
