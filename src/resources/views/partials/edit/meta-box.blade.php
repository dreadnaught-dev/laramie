<div class="card">
    @php
        $hideTags = data_get($user, 'prefs.hideTags', false) === true;
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
    <div id="tags-card-content" class="card-content meta-wrapper {{ $hideTags ? 'is-hidden' : '' }}" data-load-meta-endpoint="{{ route('laramie::load-meta', ['modelKey' => $model->getType(), 'id' => '_id_']) }}">
        @include('laramie::partials.meta-form')
    </div>
</div>

