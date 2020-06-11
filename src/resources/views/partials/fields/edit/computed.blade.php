<div class="field" data-field-key="{{ $fieldKey }}" data-field-type="{{ $field->type }}">
    <label class="label" for="{{ $fieldKey }}">{{ $field->label }} <span class="computed-value has-text-weight-normal">{{ data_get($item, $field->id, '--') }}</span></label>
    @include('laramie::partials.fields.edit._help-text')
</div>
