@php
    $canSave = ($item->isNew() && $user->hasAccessToLaramieModel($model->getType(), 'create')) ||
        ($item->isUpdating() && $user->hasAccessToLaramieModel($model->getType(), 'update'));
@endphp

<form id="edit-form" class="edit-container {{ $selectedTab !== '_main' ? 'has-tab-selected' : '' }}" action="{{ url()->full() }}" method="post" enctype="multipart/form-data" data-item-id="{{ $item->id ?: 'new' }}">
    {!! !$canSave ? '<fieldset disabled>' : '' !!}
    {{ csrf_field() }}
    <input type="hidden" name="_metaId" value="{{ $metaId }}">
    <input type="hidden" name="_selectedTab" value="{{ $selectedTab }}">
    <input type="submit" style="position: absolute; left: -9999px; width: 1px; height: 1px;" tabindex="-1" />

    @php
        $tabbedAggregates = collect($model->getFieldsSpecs())->filter(function($item){ return $item->isEditable() && $item->getType() == 'aggregate' && $item->asTab(); });
        $hasTabs = count($tabbedAggregates) > 0;
    @endphp

    @if ($hasTabs)
        <div id="edit-tabs" class="tabs is-toggle is-toggle-rounded is-small">
          <ul>
            <li {!! $selectedTab == '_main' ? 'class="is-active"' : '' !!}>
              <a data-tab="_main">
                {{ $model->getMainTabLabel() }}
              </a>
            </li>
            @foreach ($tabbedAggregates as $aggregate)
                <li {!! $selectedTab == \Str::slug($aggregate->getLabel()) ? 'class="is-active"' : '' !!}>
                  <a data-tab="{{ \Str::slug($aggregate->getLabel()) }}">
                    {{ $aggregate->isRepeatable() ? $aggregate->getLabelPlural() : $aggregate->getLabel() }}
                  </a>
                </li>
            @endforeach
          </ul>
        </div>
    @endif

    @foreach ($model->getFieldsSpecs() as $fieldKey => $field)
        @if ($field->isEditable())
            @php
                $valueOrDefault = isset($item->{$field->getId()})
                    ? data_get($item, $field->getId())
                    : $field->getDefault();
            @endphp
            @includeIfFallback('laramie::partials.fields.edit.'.$field->getType(), 'laramie::partials.fields.edit.generic')
        @endif
    @endforeach

    @stack('aggregate-scripts')
    {!! !$canSave ? '</fieldset>' : '' !!}
</form>

