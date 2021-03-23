<div class="field" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->getType() }}">
    <label class="label" for="{{ $fieldKey }}">{{ $field->getLabel() }} <span class="computed-value has-text-weight-normal">{{ data_get($item, $field->getId(), '--') }}</span></label>
    @include('laramie::partials.fields.edit._help-text')
</div>
