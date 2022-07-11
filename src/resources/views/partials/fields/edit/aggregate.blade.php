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
                                <i class="g-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                </i>
                            </span>
                        </figure>
                    @endif
                    <div class="media-content">
                        @foreach ($aggregateField->getFields() as $fieldKey => $field)
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

