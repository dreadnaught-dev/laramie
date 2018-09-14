@php
    $hasError = $errors->has($field->id);
    $isFullwidthSelect = object_get($field, 'isMultiple', false) === true;
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}">
    <label class="label" for="{{ $fieldKey }}">{!! $field->label !!}</label>
    <div class="control {{ $hasError ? 'has-icon has-icon-right' : '' }} {{ $isFullwidthSelect ? 'is-expanded' : '' }}">
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
