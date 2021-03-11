@php
    $canSave = ($item->isNew() && $user->hasAccessToLaramieModel($model->_type, 'create')) ||
        ($item->isUpdate() && $user->hasAccessToLaramieModel($model->_type, 'update'));
@endphp

<form id="edit-form" class="edit-container {{ $selectedTab !== '_main' ? 'has-tab-selected' : '' }}" action="{{ url()->full() }}" method="post" enctype="multipart/form-data" data-item-id="{{ $item->id ?: 'new' }}">
    {!! !$canSave ? '<fieldset disabled>' : '' !!}
    {{ csrf_field() }}
    <input type="hidden" name="_metaId" value="{{ $metaId }}">
    <input type="hidden" name="_selectedTab" value="{{ $selectedTab }}">
    <input type="submit" style="position: absolute; left: -9999px; width: 1px; height: 1px;" tabindex="-1" />

    @php
        $tabbedAggregates = collect(data_get($model, 'fields', []))->filter(function($item){ return $item->isEditable && $item->type == 'aggregate' && object_get($item, 'asTab', false); });
        $hasTabs = count($tabbedAggregates) > 0;
    @endphp

    @if ($hasTabs)
        <div id="edit-tabs" class="tabs is-toggle is-toggle-rounded is-small">
          <ul>
            <li {!! $selectedTab == '_main' ? 'class="is-active"' : '' !!}>
              <a data-tab="_main">
                {{ data_get($model, 'mainTabLabel', 'Main') }}
              </a>
            </li>
            @foreach ($tabbedAggregates as $aggregate)
                <li {!! $selectedTab == \Str::slug($aggregate->label) ? 'class="is-active"' : '' !!}>
                  <a data-tab="{{ \Str::slug($aggregate->label) }}">
                    {{ $aggregate->isRepeatable ? $aggregate->labelPlural : $aggregate->label }}
                  </a>
                </li>
            @endforeach
          </ul>
        </div>
    @endif

    @foreach (data_get($model, 'fields') as $fieldKey => $field)
        @if ($field->isEditable)
            @php
                $valueOrDefault = isset($item->{$field->id})
                    ? data_get($item, $field->id)
                    : data_get($field, 'default');
            @endphp
            @includeIfFallback('laramie::partials.fields.edit.'.$field->type, 'laramie::partials.fields.edit.generic')
        @endif
    @endforeach

    @stack('aggregate-scripts')
    {!! !$canSave ? '</fieldset>' : '' !!}
</form>

