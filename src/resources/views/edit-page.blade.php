@extends('laramie::layout')

@push('extra-header')
    <link href="/laramie/admin/css/trix.css" rel="stylesheet">
    {!! object_get($model, 'editCss', '') !!}
@endpush

@push('scripts')
    <script>
        globals.metaId = '{{ $metaId }}';
        globals.errorMessages = {!! json_encode($errorMessages) !!};
    </script>

    <script src="/laramie/admin/js/edit.js"></script>
    <script src="/laramie/admin/js/trix.js"></script>

    <script id="reference-single-option" type="text/x-handlebars-template">
        @{{#each data}}
            <label class="panel-block option"><div class="columns is-gapless" style="width:100%"><div class="column is-1"><input type="radio" name="@{{ name }}" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}></div><div class="column is-11">@{{{ label }}}<br><em class="is-small" style="color: #ddd;">(@{{ id }})</em></div></div></label>
        @{{else}}
            <div class="panel-block option">No results found</div>
        @{{/each}}
    </script>

    <script id="reference-many-option" type="text/x-handlebars-template">
        @{{#each data}}
            <label class="panel-block option"><div class="columns is-gapless" style="width:100%"><div class="column is-1"><input type="checkbox" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}></div><div class="column is-11">@{{{ label }}}<br><em class="is-small" style="color: #ddd;">(@{{ id }})</em></div></div></label>
        @{{else}}
            <div class="panel-block option">No results found</div>
        @{{/each}}
    </script>

    @include('laramie::handlebars.meta-tags-comments')

    {!! object_get($model, 'editJs', '') !!}
@endpush

@section('content')
    <div class="modal">
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Compare {{ $model->name }} Revisions</p>
                <button class="delete js-hide-modal" onclick="return false;"></button>
            </header>
            <div id="revision-diff" class="modal-card-body">
                <p>Loading...</p>
            </div>
            <footer class="modal-card-foot">
                <a class="button js-hide-modal">Close</a>
            </footer>
        </div>
    </div>

    <div class="column is-12-touch is-10-desktop">
        <div class="columns is-tablet">
            <div class="column is-7-tablet is-8-fullhd">
                @php
                    $tabbedAggregates = collect(object_get($model, 'fields', []))->filter(function($item){ return $item->isEditable && $item->type == 'aggregate' && object_get($item, 'asTab', false); });
                    $hasTabs = count($tabbedAggregates) > 0;
                @endphp
                <form id="edit-form" class="{{ $selectedTab !== '_main' ? 'has-tab-selected' : '' }}" action="{{ url()->current() }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <input type="hidden" name="_metaId" value="{{ $metaId }}">
                    <input type="hidden" name="_selectedTab" value="{{ $selectedTab }}">
                    <input type="submit" style="position: absolute; left: -9999px; width: 1px; height: 1px;" tabindex="-1" />

                    @include('laramie::partials.alert')

                    <h1 class="title">{{ $model->isSingular ? $model->name : $model->namePlural }} <?php if (!$model->isSingular): ?><a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="fas fa-plus"></i>&nbsp;Add new</a><?php endif; ?></h1>

                    @if ($hasTabs)
                        <div id="edit-tabs" class="tabs is-toggle is-toggle-rounded is-small">
                          <ul>
                            <li {!! $selectedTab == '_main' ? 'class="is-active"' : '' !!}>
                              <a data-tab="_main">
                                {{ object_get($model, 'mainTabLabel', 'Main') }}
                              </a>
                            </li>
                            @foreach ($tabbedAggregates as $aggregate)
                                <li {!! $selectedTab == str_slug($aggregate->label) ? 'class="is-active"' : '' !!}>
                                  <a data-tab="{{ str_slug($aggregate->label) }}">
                                    {{ $aggregate->isRepeatable ? $aggregate->labelPlural : $aggregate->label }}
                                  </a>
                                </li>
                            @endforeach
                          </ul>
                        </div>
                    @endif

                    @foreach (object_get($model, 'fields') as $fieldKey => $field)
                        @if ($field->isEditable)
                            @includeIfFallback('laramie::partials.fields.edit.'.$field->type, 'laramie::partials.fields.edit.generic')
                        @endif
                    @endforeach
                </form>

                @if ($item->_isUpdate)
                    <form id="delete-form" action="{{ route('laramie::delete-item', ['modelKey' => $model->_type, 'id' => $item->id]) }}" method="POST" style="display: none;">
                        <input type="hidden" name="_method" value="DELETE">
                        {{ csrf_field() }}
                    </form>
                @endif
            </div>
            <div class="column is-5-tablet is-4-fullhd">
                <div class="card save-box">
                    <header class="card-header">
                        <p class="card-header-title">Save</p>
                    </header>
                    <div class="card-content">
                        <div class="content">
                            @if ($item->_isNew)
                                This is a <em><strong>new</strong></em> item and hasn't been saved yet.
                            @else
                                Last updated {{ \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString() }}
                                @if (object_get($lastUserToUpdate, 'id'))
                                    by
                                    @if ($user->isSuperAdmin || $user->isAdmin)
                                        <a href="{{ route('laramie::edit', ['modelKey' => 'LaramieUser', 'id' => $lastUserToUpdate->id]) }}">{{ $lastUserToUpdate->user }}</a>
                                    @else
                                        {{ $lastUserToUpdate->user }}
                                    @endif
                                @endif
                            @endif
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="card-footer-item">
                            <div class="field is-grouped" style="width:100%">
                                <p class="control is-expanded">
                                    <a href="javascript:void(0);" class="button is-primary js-save is-fullwidth">Save{{ $item->_isUpdate ? ' changes' : ''}}</a>
                                </p>
                                <p class="control {{ $item->_isNew ? 'is-expanded' : '' }}">
                                    <a href="javascript:void(0);" class="button is-light js-cancel">Cancel</a>
                                </p>
                                @if ($item->_isUpdate)
                                    <p class="control">
                                        <a href="javascript:void(0);" class="button is-text has-text-danger js-delete">Delete</a>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </footer>
                </div>

                <hr>

                <div class="card">
                    @php
                        $hideTags = object_get($user, 'prefs.hideTags', false) === true;
                    @endphp
                    <header class="card-header">
                        <p class="card-header-title">Tags / Comments</p>
                        <a href="javascript:void(0);" class="card-header-icon" aria-label="more options">
                            <span class="icon js-toggle-prefs {{ $hideTags ? 'closed' : 'open' }}">
                                <i class="fas fa-lg fa-angle-down" data-pref="tags" aria-hidden="true"></i>
                                <i class="fas fa-lg fa-angle-up" data-pref="tags" aria-hidden="true"></i>
                            </span>
                        </a>
                    </header>
                    <div id="tags-card-content" class="card-content meta-wrapper {{ $hideTags ? 'is-hidden' : '' }}" data-load-meta-endpoint="{{ route('laramie::load-meta', ['modelKey' => $model->_type, 'id' => '_id_']) }}">
                        @include('laramie::partials.meta-form')
                    </div>
                </div>

                @if (count($revisions))
                <hr>

                <div class="card revision-history">
                    @php
                        $hideRevisions = object_get($user, 'prefs.hideRevisions', false) === true;
                    @endphp
                    <header class="card-header">
                        <p class="card-header-title">Revision History</p>
                        <a href="javascript:void(0);" class="card-header-icon" aria-label="more options">
                            <span class="icon js-toggle-prefs {{ $hideRevisions ? 'closed' : 'open' }}">
                                <i class="fas fa-lg fa-angle-down" data-pref="tags" aria-hidden="true"></i>
                                <i class="fas fa-lg fa-angle-up" data-pref="tags" aria-hidden="true"></i>
                            </span>
                        </a>
                    </header>
                    <div id="revisions-card-content" class="card-content {{ $hideRevisions ? 'is-hidden' : '' }}">
                        <div class="revision-item">
                            <span title="by: {{ object_get($lastEditor, 'user', '--') }}">Current <em><small>({{ \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString() }})</small></em></span>
                            <div>
                                <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->_type, 'revisionId' => $item->id]) }}" target="_blank" class="js-compare-revisions">View changes</a>
                            </div>
                        </div>
                        @foreach ($revisions as $revision)
                            <div class="revision-item {{ $loop->index >= config('laramie.visible_revisions') ? 'show-more' : '' }}">
                                <hr style="margin: .5rem 0;">
                                <i class="fas fa-book" style="line-height: inherit; font-size: inherit"></i>&nbsp;<span title="by: {{ $revision->user }}">{{ \Carbon\Carbon::parse($revision->updated_at)->toDayDateTimeString() }}</span>
                                <div>
                                    <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->_type, 'revisionId' => $revision->id]) }}" target="_blank" class="js-compare-revisions">View changes</a> |
                                    <a href="javascript:void(0);" class="js-restore-revision">Restore</a> |
                                    <a href="{{ route('laramie::trash-revision', ['modelKey' => $model->_type, 'revisionId' => $revision->id]) }}" class="js-delete-revision" onclick="return false;">Trash</a>
                                </div>
                                <form class="restore-revision-form" action="{{ route('laramie::restore-revision', ['modelKey' => $model->_type, 'revisionId' => $revision->id]) }}" method="post">
                                    {{ csrf_field() }}
                                </form>
                            </div>
                        @endforeach
                        @if (count($revisions) >= config('laramie.visible_revisions'))
                            <div class="show-more-link">
                                <hr style="margin: .5rem 0;">
                                <a href="javascript:void(0);" class="show-more-link">View more...</a>
                            </div>
                            <div class="show-less-link">
                                <hr style="margin: .5rem 0;">
                                <a href="javascript:void(0);" class="show-less-link">View less...</a>
                            </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection

