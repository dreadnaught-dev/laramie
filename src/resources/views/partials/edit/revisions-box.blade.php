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
                <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->_type, 'revisionId' => $item->id, 'is-child' => 1]) }}" target="_blank" class="js-compare-revisions">View changes</a>
            </div>
        </div>
        @foreach ($revisions as $revision)
            <div class="revision-item {{ $loop->index >= config('laramie.visible_revisions') ? 'show-more' : '' }}">
                <hr style="margin: .5rem 0;">
                <i class="fas fa-book" style="line-height: inherit; font-size: inherit"></i>&nbsp;<span title="by: {{ $revision->user }}">{{ \Carbon\Carbon::parse($revision->updated_at)->toDayDateTimeString() }}</span>
                <div>
                    <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->_type, 'revisionId' => $revision->id, 'is-child' => 1]) }}" target="_blank" class="js-compare-revisions">View changes</a> |
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

