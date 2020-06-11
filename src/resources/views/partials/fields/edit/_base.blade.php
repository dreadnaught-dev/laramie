@php
    $hasError = $errors->has($field->id);
    $isFullwidthSelect = data_get($field, 'isMultiple', false) === true;
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->type }}" {!! data_get($field, 'showWhen') ? 'data-show-when="'.preg_replace('/[^_]+$/', $field->showWhen, $field->id).'"' : '' !!}>
    <label class="label" for="{{ $fieldKey }}">{!! $field->label !!}</label>
    <div class="control {{ $hasError ? 'has-icons-right' : '' }} {{ $isFullwidthSelect ? 'is-expanded' : '' }}">
        @yield('input')
        @if ($hasError)
        <span class="icon is-small is-right">
          <i class="fas fa-exclamation-triangle"></i>
        </span>
        @endif
    </div>
    @include('laramie::partials.fields.edit._help-text')
    @include('laramie::partials.fields.edit._errors')
</div>
