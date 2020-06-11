@php
    $isVisibleOnEdit = data_get($field, 'isVisibleOnEdit') !== false;
@endphp

@if ($isVisibleOnEdit)
    <div class="field" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->type }}">
        <label class="label" for="{{ $fieldKey }}">{{ $field->label }} <span class="hidden-value has-text-weight-normal">{{ data_get($item, $field->id, '--') }}</span></label>
        <input type="hidden" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ $valueOrDefault }}" onchange="$(this).closest('.field').find('.hidden-value').text($(this).val())">
        @include('laramie::partials.fields.edit._help-text')
    </div>
@else
    <input type="hidden" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ $valueOrDefault }}">
@endif
