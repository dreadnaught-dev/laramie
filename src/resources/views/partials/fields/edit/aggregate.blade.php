@php
    $aggregateFieldKey = $fieldKey;
    $aggregateField = $field;

    $isRepeatable = $field->isRepeatable;
    $aggregateDepth = isset($aggregateDepth) ? $aggregateDepth + 1 : 1;
@endphp

<div class="aggregate-outer-wrapper {{ object_get($aggregateField, 'asTab') ? 'is-tab tab-'.str_slug($aggregateField->label) . ($selectedTab == str_slug($aggregateField->label) ? ' is-active' : '') : '' }}" style="margin-bottom: 1.5rem;">
    <label class="label">
        {{ $aggregateField->isRepeatable ? $aggregateField->labelPlural : $aggregateField->label }}
        @if ($isRepeatable)
            &nbsp;&nbsp;<a class="tag is-primary js-add-aggregate" data-type="{{ $field->_fieldName }}">Add {{ preg_match('/^[aeiou]/i', $field->label) ? 'an' : 'a'}} {{ $field->label }}</a>
        @endif
    </label>

    <div class="aggregate-holder padded content" data-type="{{ $field->_fieldName }}" data-template="{{ $metaId . $field->_template }}" data-is-repeatable="{{ $field->isRepeatable ? '1' : '0' }}" data-min-items="{{ object_get($field, 'minItems') }}" data-max-items="{{ object_get($field, 'maxItems') }}" data-empty-message="No {{ $aggregateField->labelPlural }} added yet">
        @if ($isRepeatable)
            <?php /*<p>No {{ $aggregateField->labelPlural }} added yet</p>*/ ?>
        @endif

        @if ($aggregateDepth == 1)
            <script>
                window.globals.aggregates = window.globals.aggregates || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}'] = window.globals.aggregates['{{ $item->id ?: 'new' }}'] || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}']['{{ $aggregateField->_fieldName }}'] = {!! json_encode(object_get($item, $fieldKey, $isRepeatable ? [] : (object) [])) !!};
            </script>
        @endif

        @push('aggregate-scripts')
            <script id="{{ $metaId . $field->_template }}" type="text/x-handlebars-template">
                <div class="media field">
                    @if ($isRepeatable)
                        <figure class="media-left">
                            <span class="icon drag-{{ $field->_fieldName }}">
                                <i class="fas fa-grip-vertical"></i>
                            </span>
                        </figure>
                    @endif
                    <div class="media-content">
                        @foreach (object_get($aggregateField, 'fields') as $fieldKey => $field)
                            @if ($field->isEditable)
                                @includeIfFallback('laramie::partials.fields.edit.'.$field->type, 'laramie::partials.fields.edit.generic')
                            @endif
                        @endforeach
                    </div>
                    @if ($isRepeatable)
                        <figure class="media-right">
                            <a class="delete js-remove-aggregate" title="Remove this {{ $aggregateField->label }}"></a>
                        </figure>
                    @endif
                </div>
            </script>
        @endpush
    </div>
</div>

@php
    $fieldKey = $aggregateFieldKey;
    $field = $aggregateField;
@endphp

