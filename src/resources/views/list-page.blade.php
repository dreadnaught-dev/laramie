@extends('laramie::layout')

@php
    $activeSort = request()->get('sort', $model->getDefaultSort());
    $activeSortDirection = request()->get('sort-direction', $model->getDefaultSortDirection());
    $invertActiveSortDirection = $activeSortDirection === 'desc' ? 'asc' : 'desc';
    $quickSearch = request()->get('quick-search');

    $filterableFields = collect($model->getFieldsSpecs())
        ->filter(function($item){
            return $item->isSearchable()
                || (
                    $item->isListable()
                    && $item->isMetaField() !== true
                    && $item->isSearchable()
                );
        })
        ->sortBy('label');

    $metaFields = collect($model->getFieldsSpecs())
        ->filter(function($item){
            return $item->isMetaField()
                && $item->isSearchable();
        })
        ->sortBy('label');
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
                                        <option value="{{ $key }}" {{ $model->getAlias() == $key ? 'selected' : '' }}>{{ $field->getLabel() }}</option>
                                    @endforeach
                                </optgroup>
                                @if ($metaFields->count() > 0)
                                    <optgroup label="Meta">
                                        @foreach ($metaFields as $key => $field)
                                            <option value="{{ $key }}" {{ $model->getAlias() == $key ? 'selected' : '' }}>{{ $field->getLabel() }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
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
                        <button class="button is-light">Go</button>
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
        <form id="save-list-prefs-form" onsubmit="return false;" autocomplete="off">
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
                                            <i class="g-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                            </i>
                                            <input type="hidden" name="_lf_{{ $fieldKey }}" value="0">
                                            <input type="checkbox" name="_lf_{{ $fieldKey }}" value="1" {{ array_key_exists($fieldKey, $listFields) ? 'checked' : '' }}>
                                            {{ $field->getLabel() }}
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
            <section class="modal-card-body meta-wrapper" data-load-meta-endpoint="{{ route('laramie::load-meta', ['modelKey' => $model->getType(), 'id' => '_id_']) }}">
                @include('laramie::partials.meta-form')
            </section>
            <footer class="modal-card-foot">
                <a class="button js-meta">Cancel</a>
            </footer>
        </div>
        </form>
    </div>

    @include('laramie::handlebars.meta-tags-comments')

    {!! implode('', $model->getListJs()) !!}
@endpush

@section('content')
    <div class="column is-12-touch is-10-desktop">

        @include('laramie::partials.alert')

        <div class="level is-mobile is-spaced">
            <div class="level-left">
                <div class="level-item">
                    <h1 class="title">{{ $model->getNamePlural() }}</h1>
                </div>
                <div class="level-item">
                    @if ($model->isEditable())
                        <a href="{{ route('laramie::edit', ['modelKey' => $model->getType(), 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></i>&nbsp;Add new</a>
                    @else
                        <span class="subtitle">(items of this type may not be edited)</span>
                    @endif
                </div>
            </div>
            <div class="level-right">
                <div class="level-item">
                    <button class="button js-toggle-page-settings"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19.43 12.98c.04-.32.07-.64.07-.98 0-.34-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.09-.16-.26-.25-.44-.25-.06 0-.12.01-.17.03l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.06-.02-.12-.03-.18-.03-.17 0-.34.09-.43.25l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98 0 .33.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.09.16.26.25.44.25.06 0 .12-.01.17-.03l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.06.02.12.03.18.03.17 0 .34-.09.43-.25l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zm-1.98-1.71c.04.31.05.52.05.73 0 .21-.02.43-.05.73l-.14 1.13.89.7 1.08.84-.7 1.21-1.27-.51-1.04-.42-.9.68c-.43.32-.84.56-1.25.73l-1.06.43-.16 1.13-.2 1.35h-1.4l-.19-1.35-.16-1.13-1.06-.43c-.43-.18-.83-.41-1.23-.71l-.91-.7-1.06.43-1.27.51-.7-1.21 1.08-.84.89-.7-.14-1.13c-.03-.31-.05-.54-.05-.74s.02-.43.05-.73l.14-1.13-.89-.7-1.08-.84.7-1.21 1.27.51 1.04.42.9-.68c.43-.32.84-.56 1.25-.73l1.06-.43.16-1.13.2-1.35h1.39l.19 1.35.16 1.13 1.06.43c.43.18.83.41 1.23.71l.91.7 1.06-.43 1.27-.51.7 1.21-1.07.85-.89.7.14 1.13zM12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg></i><span class="is-hidden-mobile">&nbsp;Page settings</span></button>
                </div>
            </div>
        </div>

        <form id="list-form" method="get" action="{{ route('laramie::list', ['modelKey' => $model->getType()]) }}" data-bulk-action="{{ route('laramie::bulk-action-handler', ['modelKey' => $model->getType()]) }}" data-save-report-action="{{ route('laramie::save-report', ['modelKey' => $model->getType()]) }}" data-save-list-prefs-action="{{ route('laramie::save-list-prefs', ['modelKey' => $model->getType()]) }}">
            <input type="hidden" name="_token" class="post-only" value="{!! csrf_token() !!}">
            <input type="hidden" name="sort" value="{{ $activeSort }}">
            <input type="hidden" name="sort-direction" value="{{ $activeSortDirection }}">
            <input type="hidden" id="bulk-action-all-selected" name="bulk-action-all-selected" class="post-only" value="">
            <input type="hidden" id="is-filtering" class="post-only" value="1">

            <div class="field is-grouped is-spaced">
                @if ($quickSearch)
                    <p class="control">
                        <button type="button" class="button is-white has-text-primary js-clear-search" title="Clear quick search">
                            <span class="icon"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg></i></span>
                        </button>
                    </p>
                @endif
                <p class="control">
                    <input class="input" type="text" name="quick-search" id="quick-search" placeholder="Quick Search" title="Quickly search by {{ implode(', ', $model->getQuickSearch()) }}" value="{{ $quickSearch }}">
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

            @include('laramie::list-table')
        </form>
    </div>
@endsection
