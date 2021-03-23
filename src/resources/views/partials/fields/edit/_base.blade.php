@php
    $hasError = $errors->has($field->getId());
    $isFullwidthSelect = $field->isMultiple();
    $showWhen = $field->getShowWhen();
@endphp

<div class="field {{ $hasError ? 'is-danger' : '' }}" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}" {!! $showWhen ? 'data-show-when="'.preg_replace('/[^_]+$/', $showWhen, $field->getId()).'"' : '' !!}>
    <label class="label" for="{{ $fieldKey }}">{!! $field->getLabel() !!}</label>
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
