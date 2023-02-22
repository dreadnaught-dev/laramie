@php
    $aggregateFieldKey = $fieldKey;
    $aggregateField = $field;

    $isRepeatable = $field->isRepeatable;
    $aggregateDepth = isset($aggregateDepth) ? $aggregateDepth + 1 : 1;

    $showWhen = null;

    if (data_get($field, 'showWhen')) {
        $parts = array_filter(array_slice(explode('}}_', $aggregateField->id), 0, -1));
        $parts[] = data_get($aggregateField, 'showWhen');
        $showWhen = implode('}}_', $parts);
    }
@endphp

<div class="aggregate-outer-wrapper has-margin-bottom {{ data_get($aggregateField, 'asTab') ? 'is-tab tab-'.\Str::slug($aggregateField->label) . ($selectedTab == \Str::slug($aggregateField->label) ? ' is-active' : '') : '' }}"
    {!! $showWhen ? 'data-show-when="'.$showWhen.'"' : '' !!}
>
    @if (data_get($aggregateField, 'hideLabel') !== true)
        <h4 class="title is-4" style="margin: 1.5rem 0 .75rem">
            {{ $aggregateField->isRepeatable ? $aggregateField->labelPlural : $aggregateField->label }}
            @if ($isRepeatable)
                &nbsp;&nbsp;<a class="tag is-primary js-add-aggregate" data-type="{{ $field->_fieldName }}">Add {{ preg_match('/^[aeiou]/i', $field->label) ? 'an' : 'a'}} {{ $field->label }}</a>
            @endif
        </h4>
    @endif

    <div class="aggregate-holder padded content {{ data_get($aggregateField, 'unwrap') === true ? 'unwrapped' : 'wrapped' }}" data-type="{{ $field->_fieldName }}" data-template="{{ $metaId . $field->_template }}" data-is-repeatable="{{ $field->isRepeatable ? '1' : '0' }}" data-min-items="{{ data_get($field, 'minItems') }}" data-max-items="{{ data_get($field, 'maxItems') }}" data-empty-message="No {{ $aggregateField->labelPlural }} added yet">
        @if ($isRepeatable)
            <?php /*<p>No {{ $aggregateField->labelPlural }} added yet</p>*/ ?>
        @endif

        @if ($aggregateDepth == 1)
            <script>
                window.globals.aggregates = window.globals.aggregates || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}'] = window.globals.aggregates['{{ $item->id ?: 'new' }}'] || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}']['{{ $aggregateField->_fieldName }}'] = {!! json_encode(data_get($item, $fieldKey, $isRepeatable ? [] : (object) [])) !!};
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
                        @foreach (data_get($aggregateField, 'fields') as $fieldKey => $field)
                            @php
                                $valueOrDefault = isset($item->{$field->id})
                                    ? data_get($item, $field->id)
                                    : data_get($field, 'default');
                            @endphp
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

