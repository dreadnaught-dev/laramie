@php
    $aggregateFieldKey = $fieldKey;
    $aggregateField = $field;

    $isRepeatable = $field->isRepeatable();
    $aggregateDepth = isset($aggregateDepth) ? $aggregateDepth + 1 : 1;

    $showWhen = $field->getShowWhen();

    if ($showWhen) {
        $parts = array_filter(array_slice(explode('}}_', $aggregateField->getId()), 0, -1));
        $parts[] = $aggregateField->getShowWhen();
        $showWhen = implode('}}_', $parts);
    }
@endphp

<div class="aggregate-outer-wrapper has-margin-bottom {{ $aggregateField->asTab() ? 'is-tab tab-'.\Str::slug($aggregateField->getLabel()) . ($selectedTab == \Str::slug($aggregateField->getLabel()) ? ' is-active' : '') : '' }}"
    {!! $showWhen ? 'data-show-when="'.$showWhen.'"' : '' !!}
>
    @if ($aggregateField->isHideLabel()) !== true)
        <h4 class="title is-4" style="margin: 1.5rem 0 .75rem">
            {{ $aggregateField->isRepeatable() ? $aggregateField->getLabelPlural() : $aggregateField->getLabel() }}
            @if ($isRepeatable)
                &nbsp;&nbsp;<a class="tag is-primary js-add-aggregate" data-type="{{ $field->getFieldName() }}">Add {{ preg_match('/^[aeiou]/i', $field->getLabel()) ? 'an' : 'a'}} {{ $field->getLabel() }}</a>
            @endif
        </h4>
    @endif

    <div class="aggregate-holder padded content {{ $aggregateField->isUnwrap() ? 'unwrapped' : 'wrapped' }}" data-type="{{ $field->getFieldName() }}" data-template="{{ $metaId . $field->getTemplate() }}" data-is-repeatable="{{ $field->isRepeatable() ? '1' : '0' }}" data-min-items="{{ $field->getMinItems() }}" data-max-items="{{ $field->getMaxItems() }}" data-empty-message="No {{ $aggregateField->getLabelPlural() }} added yet">
        @if ($isRepeatable)
            <?php /*<p>No {{ $aggregateField->labelPlural }} added yet</p>*/ ?>
        @endif

        @if ($aggregateDepth == 1)
            <script>
                window.globals.aggregates = window.globals.aggregates || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}'] = window.globals.aggregates['{{ $item->id ?: 'new' }}'] || {};
                window.globals.aggregates['{{ $item->id ?: 'new' }}']['{{ $aggregateField->getFieldName() }}'] = {!! json_encode(data_get($item, $fieldKey, $isRepeatable ? [] : (object) [])) !!};
            </script>
        @endif

        @push('aggregate-scripts')
            <script id="{{ $metaId . $field->getTemplate() }}" type="text/x-handlebars-template">
                <div class="media field">
                    @if ($isRepeatable)
                        <figure class="media-left">
                            <span class="icon drag-{{ $field->getFieldName() }}">
                                <i class="fas fa-grip-vertical"></i>
                            </span>
                        </figure>
                    @endif
                    <div class="media-content">
                        @foreach ($aggregateField->getFieldsSpecs() as $fieldKey => $field)
                            @php
                                $valueOrDefault = isset($item->{$field->getId()})
                                    ? data_get($item, $field->getId())
                                    : $field->getDefault();
                            @endphp
                            @if ($field->isEditable())
                                @includeIfFallback('laramie::partials.fields.edit.'.$field->getType(), 'laramie::partials.fields.edit.generic')
                            @endif
                        @endforeach
                    </div>
                    @if ($isRepeatable)
                        <figure class="media-right">
                            <a class="delete js-remove-aggregate" title="Remove this {{ $aggregateField->getLabel() }}"></a>
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

