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
          <i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/></svg></i>
        </span>
        @endif
    </div>
    @include('laramie::partials.fields.edit._help-text')
    @include('laramie::partials.fields.edit._errors')
</div>
