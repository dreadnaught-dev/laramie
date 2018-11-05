@extends('laramie::layout')

@php
    $activeSort = request()->get('sort', $model->defaultSort);
    $activeSortDirection = request()->get('sort-direction', $model->defaultSortDirection);
    $quickSearch = request()->get('quick-search');

    $filterableFields = collect($model->fields)
        ->filter(function($e){
            return $e->isListable && !object_get($e, 'isMetaField', false);
        })
        ->sortBy('label')
        ->all();
@endphp


@push('scripts')
    <script src="/laramie/admin/js/list.js"></script>
    <script>globals.filters = {!! json_encode($filters) !!};</script>

    <script id="list-filter" type="text/x-handlebars-template">
        <div class="field is-horizontal filter-set">
            <div class="field-body">
                <div class="field is-narrow">
                    <p class="control">
                        <span class="delete js-remove-filter"></span>
                    </p>
                </div>
                <div class="field is-narrow">
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="filter_@{{filterIndex}}_field">
                                <optgroup label="Fields">
                                    @foreach ($filterableFields as $key => $field)
                                        <option value="{{ $key }}" {{ object_get($model, 'alias') == $key ? 'selected' : '' }}>{{ $field->label }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Meta">
                                    <option value="_comment">Comments</option>
                                    <option value="_tag">Tags</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field is-narrow">
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="filter_@{{filterIndex}}_operation">
                                <option>contains</option>
                                <option>does not contain</option>
                                <option>is equal to</option>
                                <option>is not equal to</option>
                                <option>starts with</option>
                                <option>does not start with</option>
                                <option>is less than</option>
                                <option>is less than or equal</option>
                                <option>is greater than</option>
                                <option>is greater than or equal</option>
                                <option>is null</option>
                                <option>is not null</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field is-narrow">
                    <p class="control">
                        <input class="input" type="text" name="filter_@{{filterIndex}}_value" placeholder="value...">
                    </p>
                </div>
                <div class="field is-narrow filter-button-holder">
                    <p class="control">
                        <button class="button is-light">filter</button>
                    </p>
                </div>
                <div class="field is-narrow filter-button-holder">
                    <p class="control">
                        <a href="javascript:void(0);" class="button is-white js-add-filter">Add another filter</a>
                    </p>
                </div>
            </div>
        </div>
    </script>

    <div id="page-settings" class="modal">
        <div class="modal-background"></div>
        <form id="save-list-prefs-form" onsubmit="return false;">
            <div class="modal-card">
                <header class="modal-card-head">
                    <p class="modal-card-title">Page Settings</p>
                    <span class="delete js-toggle-page-settings" onclick="return false;"></span>
                </header>
                <div class="modal-card-body">
                    <div class="columns">
                        <div class="column">
                            <h4 class="title">Columns</h4>
                            <div id="selectable-fields">
                            @foreach ($listableFields as $fieldKey => $field)
                                <div class="field" title="click and drag to sort">
                                    <p class="control">
                                        <label class="checkbox">
                                            <i class="fas fa-grip-vertical"></i>&nbsp;
                                            <input type="hidden" name="{{ $fieldKey }}" value="0">
                                            <input type="checkbox" name="{{ $fieldKey }}" value="1" {{ array_key_exists($fieldKey, $listFields) ? 'checked' : '' }}>
                                            {{ $field->label }}
                                        </label>
                                    </p>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        <div class="column">
                            <h4 class="title">Saved reports</h4>
                            @forelse ($reports as $report)
                                <div class="field has-invisibles">
                                    <p class="control">
                                        <a href="{{ route('laramie::load-report', ['id' => $report->id]) }}">Load <em style="text-decoration: underline;">{{ $report->name }}</em></a>
                                    </p>
                                    <p class="control is-invisible">
                                        <span><a href="javascript:void(0);" class="js-set-default-report" data-action="{{ route('laramie::modify-report', ['id' => $report->id, 'type' => 'set-default']) }}">Set as default view</a> |</span>
                                        <span><a href="javascript:void(0);" class="js-delete-report" data-action="{{ route('laramie::modify-report', ['id' => $report->id, 'type' => 'delete']) }}" >Trash</a></span>
                                    </p>
                                </div>
                            @empty
                                <p>No reports saved</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <footer class="modal-card-foot">
                    <button class="button is-success">Save changes</button>
                    <a class="button js-toggle-page-settings">Cancel</a>
                </footer>
            </div>
        </form>
    </div>

    <div id="save-report-modal" class="modal">
        <div class="modal-background"></div>
        <form id="save-report-form" onsubmit="return false;">
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Save Report</p>
                <span class="delete js-toggle-save-report"></span>
            </header>
            <section class="modal-card-body">
                <p>Saving a report is a convenient way to save a group of filters for later use. Saved reports are accessible through the "Page settings" button.</p>
                <br>
                <div class="field is-horizontal">
                    <div class="field-label" style="flex-basis: inherit; flex-grow: inherit;">
                        <label class="label">Report name</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <input id="modal-report-name" name="report-name" type="text" class="input" placeholder="e.g. Orders to be invoiced">
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button type="submit" class="button is-success">Save</button>
                <a class="button js-toggle-save-report">Cancel</a>
            </footer>
        </div>
        </form>
    </div>

    <div id="meta-modal-wrapper" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">View tags / comments</p>
                <span class="delete js-meta"></span>
            </header>
            <section class="modal-card-body meta-wrapper" data-load-meta-endpoint="{{ route('laramie::load-meta', ['modelKey' => $model->_type, 'id' => '_id_']) }}">
                @include('laramie::partials.meta-form')
            </section>
            <footer class="modal-card-foot">
                <a class="button js-meta">Cancel</a>
            </footer>
        </div>
        </form>
    </div>

    @include('laramie::handlebars.meta-tags-comments')

    {!! object_get($model, 'listJs', '') !!}
@endpush

@section('content')
    <div class="column is-12-touch is-10-desktop">
        <div class="level is-mobile is-spaced">
            <div class="level-left">
                <div class="level-item">
                    <h1 class="title">{{ $model->namePlural }}</h1>
                </div>
                <div class="level-item">
                    @if ($model->isEditable)
                        <a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="fas fa-plus"></i>&nbsp;Add new</a>
                    @else
                        <span class="subtitle">(items of this type may not be edited)</span>
                    @endif
                </div>
            </div>
            <div class="level-right">
                <div class="level-item">
                    <button class="button js-toggle-page-settings"><i class="fas fa-cog"></i><span class="is-hidden-mobile">&nbsp;Page settings</span></button>
                </div>
            </div>
        </div>

        <form id="list-form" method="get" action="{{ route('laramie::list', ['modelKey' => $model->_type]) }}" data-bulk-action="{{ route('laramie::bulk-action-handler', ['modelKey' => $model->_type]) }}" data-save-report-action="{{ route('laramie::save-report', ['modelKey' => $model->_type]) }}" data-save-list-prefs-action="{{ route('laramie::save-list-prefs', ['modelKey' => $model->_type]) }}">
            <input type="hidden" name="_token" class="post-only" value="{!! csrf_token() !!}">
            <input type="hidden" name="sort" value="{{ $activeSort }}">
            <input type="hidden" name="sort-direction" value="{{ $activeSortDirection }}">
            <input type="hidden" id="bulk-action-all-selected" name="bulk-action-all-selected" class="post-only" value="">
            <input type="hidden" id="is-filtering" class="post-only" value="1">

            <div class="field is-grouped is-spaced">
                @if ($quickSearch)
                    <p class="control">
                        <button onclick="$('#quick-search').val('')" class="button is-white has-text-primary" title="Clear search">
                            <span class="icon"><i class="fas fa-broom"></i></span>
                        </button>
                    </p>
                @endif
                <p class="control">
                    <input class="input" type="text" name="quick-search" id="quick-search" placeholder="Quick Search" title="Quickly search by {{ implode(', ', object_get($model, 'quickSearch')) }}" value="{{ $quickSearch }}">
                </p>
                <p class="control">
                    <button class="button is-light">Go</button>
                </p>
                <p class="control">
                    <a href="javascript:void(0);" class="button is-white js-advanced-search">Advanced<span class="is-hidden-mobile">&nbsp;Search</span></a>
                </p>
                @if ($quickSearch || count($filters) > 0)
                <p class="control">
                    <a href="javascript:void(0);" class="is-italic button is-white js-toggle-save-report">Save<span class="is-hidden-mobile">&nbsp;search to report</span></a>
                </p>
                @endif
            </div>

            <div id="filter-holder" class="is-spaced">
                <div id="filters"></div>
            </div>

            <div class="is-spaced-sm">
            <em>Viewing {{ number_format($models->firstItem()) }} - {{ number_format($models->lastItem()) }} of {{ number_format($models->total()) }}</em>
            </div>

            <div id="bulk-action-helper" class="is-spaced-sm notification is-warning" data-has-additional-pages="{{ $models->hasMorePages() ? '1' : '' }}">
                <p class="selection-count">
                    All {{ $models->count() }} {{ strtolower($model->namePlural) }} on this page are selected.
                    <a class="js-bulk-select-all" href="javascript:void(0)">Select all {{ number_format($models->total()) }} {{ strtolower($model->namePlural) }}.</a>
                </p>
                <p class="selection-total">
                    All {{ number_format($models->total()) }} {{ strtolower($model->namePlural) }} are selected.
                    <a class="js-clear-bulk-selection" href="javascript:void(0)">Clear selection.</a>
                </p>
            </div>

            <div class="responsive-table-wrapper has-margin-bottom">
                <table id="main-list-table" class="table is-bordered is-striped is-fullwidth">
                    <thead>
                        <tr>
                            <td><input type="checkbox" aria-label="Select All" class="js-select-all"></td>
                            @foreach ($listFields as $fieldKey => $field)
                                <th>
                                    @if ($field->isSortable)
                                        <div class="is-pulled-left" title="Click to sort by {{ strtolower($field->label) }}">
                                            <a href="{{ $viewHelper->getCurrentUrlWithModifiedQS(['sort' => $field->sortBy, 'sort-direction' => ($field->sortBy == $activeSort ? $activeSortDirection : ''), 'page' => 1]) }}">{{ $field->label }}</a>
                                        </div>
                                        <div class="is-pulled-right">
                                            @if ($field->sortBy == $activeSort)
                                                <a href="{{ $viewHelper->getCurrentUrlWithModifiedQS(['sort' => $field->sortBy, 'sort-direction' => ($field->sortBy == $activeSort ? $activeSortDirection : ''), 'page' => 1]) }}">
                                                    <span class="icon">
                                                        <i class="fas fa-sort-{{ $activeSortDirection == 'desc' ? 'down' : 'up' }}" title="Sorting {{ $activeSortDirection == 'desc' ? 'descending' : 'ascending' }}. Click to toggle"></i>
                                                    </span>
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <label>{{ $field->label }}</label>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        @foreach ($models as $m)
                        <tr id="row-{{ $m->id }}" class="has-invisibles">
                            <th style="width:1px;"><input type="checkbox" name="bulk-action-ids[]" class="js-item-id" value="{{ $m->id }}"></th>
                            @foreach ($listFields as $key => $field)
                                @php $displayValue = $viewHelper->formatListValue($field, object_get($m, $key)); @endphp
                                <td{!! $loop->first ? ' class="first-td"' : '' !!}>
                                @if ($loop->first)
                                    <strong>
                                        @if ($model->isEditable)
                                            <a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => $m->id]) }}">{!! $displayValue !!}</a>
                                        @else
                                            {!! $displayValue !!}
                                        @endif
                                    </strong>
                                    <div class="is-invisible">
                                        @if ($model->isEditable)
                                            <span><a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => $m->id]) }}">Edit</a> |</span>
                                        @endif
                                        <span><a href="javascript:void(0);" class="js-delete" data-action="{{ route('laramie::delete-item', ['modelKey' => $model->_type, 'id' => $m->id]) }}">Trash</a></span>
                                    </div>
                                @else
                                    {!! $displayValue !!}
                                @endif
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="level">
                <div class="level-left">
                    <div class="field">
                        <p class="control">
                            <span class="select">
                                <select id="bulk-action-operation" name="bulk-action-operation" disabled>
                                    <option value="" id="null-bulk-action">With selected...</option>
                                    <option value="delete">Delete</option>
                                    <option value="duplicate">Duplicate</option>
                                    <option value="export">Export to CSV</option>
                                </select>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            {{ $models->links('laramie::partials.pagination.bulma-paginator') }}
        </form>
    </div>
@endsection
