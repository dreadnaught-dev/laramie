@php
    $activeSort = request()->get('sort', $model->getDefaultSort());
    $activeSortDirection = request()->get('sort-direction', $model->getDefaultSortDirection());
    $invertActiveSortDirection = $activeSortDirection === 'desc' ? 'asc' : 'desc';
    $quickSearch = request()->get('quick-search');
@endphp

<div id="list-table-wrapper">
    <div class="is-spaced-sm">
        <em>Viewing <span id="viewing-start">{{ number_format($models->firstItem()) }}</span> - <span id="viewing-end">{{ number_format($models->lastItem()) }}</span> of <span id="viewing-total">{{ number_format($models->total()) }}</span></em>
    </div>

    <div id="bulk-action-helper" class="is-spaced-sm notification is-warning" data-has-additional-pages="{{ $models->hasMorePages() ? '1' : '' }}">
        <p class="selection-count">
            All {{ $models->count() }} {{ strtolower($model->getNamePlural()) }} on this page are selected.
            <a class="js-bulk-select-all" href="javascript:void(0)">Select all {{ number_format($models->total()) }} {{ strtolower($model->getNamePlural()) }}.</a>
        </p>
        <p class="selection-total">
            All {{ number_format($models->total()) }} {{ strtolower($model->getNamePlural()) }} are selected.
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
                            @if ($field->isSortable())
                                <div class="is-pulled-left" title="Click to sort by {{ strtolower($field->getLabel()) }}">
                                    <a href="{{ $viewHelper->getCurrentUrlWithModifiedQS(['sort' => $field->getSortBy(), 'sort-direction' => ($field->getSortBy() == $activeSort ? $invertActiveSortDirection : 'asc'), 'page' => 1]) }}">{{ $field->getLabel() }}</a>
                                </div>
                                <div class="is-pulled-right">
                                    @if ($field->getSortBy() == $activeSort)
                                        <a href="{{ $viewHelper->getCurrentUrlWithModifiedQS(['sort' => $field->getSortBy(), 'sort-direction' => ($field->getSortBy() == $activeSort ? $invertActiveSortDirection : 'asc'), 'page' => 1]) }}">
                                            <span class="icon">
                                                <i class="g-icon" title="Sorting {{ $activeSortDirection == 'desc' ? 'descending' : 'ascending' }}. Click to toggle">
                                                    @if ($activeSortDirection === 'desc')
                                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M24 24H0V0h24v24z" fill="none" opacity=".87"/><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6-1.41-1.41z"/></svg>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 8l-6 6 1.41 1.41L12 10.83l4.59 4.58L18 14l-6-6z"/></svg>
                                                    @endif
                                                </i>
                                            </span>
                                        </a>
                                    @endif
                                </div>
                            @else
                                <label>{{ $field->getLabel() }}</label>
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
                        @php $displayValue = $viewHelper->formatListValue($field, data_get($m, $key)); @endphp
                        <td{!! $loop->first ? ' class="first-td"' : '' !!}>
                        @if ($loop->first)
                            <strong>
                                @if ($model->isEditable())
                                    <a href="{{ route('laramie::edit', ['modelKey' => $model->getType(), 'id' => $m->id]) }}">{!! $displayValue !!}</a>
                                @else
                                    {!! $displayValue !!}
                                @endif
                            </strong>
                            <div class="is-invisible">
                                @if ($model->isEditable())
                                    <span><a href="{{ route('laramie::edit', ['modelKey' => $model->getType(), 'id' => $m->id]) }}">Edit</a> |</span>
                                @endif
                                <span><a href="javascript:void(0);" class="js-delete" data-action="{{ route('laramie::delete-item', ['modelKey' => $model->getType(), 'id' => $m->id]) }}">Trash</a></span>
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

    @if ($bulkActions = $model->getBulkActions())
    <div class="level">
        <div class="level-left">
            <div class="field">
                <p class="control">
                    <span class="select">
                        <select id="bulk-action-operation" name="bulk-action-operation" disabled>
                            <option value="" id="null-bulk-action">With selected...</option>
                            @foreach ($bulkActions as $bulkAction)
                            <option value="{{ $bulkAction }}">{{ $bulkAction }}</option>
                            @endforeach
                        </select>
                    </span>
                </p>
            </div>
        </div>
    </div>
    @endif

    {{ $models->links('laramie::partials.pagination.bulma-paginator') }}
</div>
