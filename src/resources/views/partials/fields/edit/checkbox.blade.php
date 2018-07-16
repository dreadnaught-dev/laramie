@php
    $hasError = $errors->has($fieldKey);
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}">
    <p class="control {{ $hasError ? 'has-icon has-icon-right' : '' }}">
        <label class="checkbox">
            <input type="checkbox" class="is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" value="1" {{ object_get($item, $fieldKey) ? 'checked' : '' }} {{ $field->extra }} {{ $field->required ? 'required' : '' }}>
            {{ $field->label }}
            @if ($hasError)
            <span class="icon is-small">
              <i class="fas fa-exclamation-triangle"></i>
            </span>
            @endif
        </label>
    </p>
    @include('laramie::partials.fields.edit._errors')
</div>
