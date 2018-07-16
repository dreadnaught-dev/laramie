<div class="field">
    <label class="label" for="{{ $fieldKey }}">{{ $field->label }}</label>
    <p class="control">
         <span class="hidden-value">{{ object_get($item, $field->id, '--') }}</span>
    </p>
    <input type="hidden" id="{{ $field->id }}" name="{{ $field->id }}" value="{{ object_get($item, $field->id) }}" onchange="$(this).closest('.field').find('.hidden-value').text($(this).val())">
</div>
