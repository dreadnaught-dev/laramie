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
              <i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/></svg></i>
            </span>
            @endif
        </label>
    </p>
    @include('laramie::partials.fields.edit._errors')
</div>
