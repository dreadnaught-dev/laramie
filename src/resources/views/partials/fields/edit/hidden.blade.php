@if ($field->isVisibleOnEdit())
    <div class="field" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}">
        <label class="label" for="{{ $fieldKey }}">{{ $field->getLabel() }} <span class="hidden-value has-text-weight-normal">{{ data_get($item, $field->getId(), '--') }}</span></label>
        <input type="hidden" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}" onchange="$(this).closest('.field').find('.hidden-value').text($(this).val())">
        @include('laramie::partials.fields.edit._help-text')
    </div>
@else
    <input type="hidden" id="{{ $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}">
@endif
