<div class="card revision-history">
    @php
        $hideRevisions = data_get($user, 'prefs.hideRevisions', false) === true;
    @endphp
    <header class="card-header">
        <p class="card-header-title">Revision History</p>
        <a href="javascript:void(0);" class="card-header-icon" aria-label="more options">
            <span class="icon js-toggle-prefs {{ $hideRevisions ? 'closed' : 'open' }}">
                <i class="g-icon" data-pref="tags" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M24 24H0V0h24v24z" fill="none" opacity=".87"/><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6-1.41-1.41z"/></svg></i>
                <i class="g-icon" data-pref="tags" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 8l-6 6 1.41 1.41L12 10.83l4.59 4.58L18 14l-6-6z"/></svg></i>
            </span>
        </a>
    </header>
    <div id="revisions-card-content" class="card-content {{ $hideRevisions ? 'is-hidden' : '' }}">
        <div class="revision-item">
            <span title="by: {{ data_get($lastUserToUpdate, config('laramie.username'), '--') }}">Current <em><small>({{ \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString() }})</small></em></span>
            <div>
                <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->getType(), 'revisionId' => $item->id, 'is-child' => 1]) }}" target="_blank" class="js-compare-revisions">View changes</a>
            </div>
        </div>
        @foreach ($revisions as $revision)
            <div class="revision-item {{ $loop->index >= config('laramie.visible_revisions') ? 'show-more' : '' }}">
                <hr style="margin: .5rem 0;">
                <i class="g-icon" style="line-height: inherit; font-size: inherit"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12zM10 9h8v2h-8zm0 3h4v2h-4zm0-6h8v2h-8z"/></svg></i>&nbsp;<span title="by: {{ $revision->user }}">{{ \Carbon\Carbon::parse($revision->updated_at)->toDayDateTimeString() }}</span>
                <div>
                    <a href="{{ route('laramie::compare-revisions', ['modelKey' => $model->getType(), 'revisionId' => $revision->id, 'is-child' => 1]) }}" target="_blank" class="js-compare-revisions">View changes</a> |
                    <a href="javascript:void(0);" class="js-restore-revision">Restore</a> |
                    <a href="{{ route('laramie::trash-revision', ['modelKey' => $model->getType(), 'revisionId' => $revision->id]) }}" class="js-delete-revision" onclick="return false;">Trash</a>
                </div>
                <form class="restore-revision-form" action="{{ route('laramie::restore-revision', ['modelKey' => $model->getType(), 'revisionId' => $revision->id]) }}" method="post">
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

