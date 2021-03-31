<div class="card">
    @php
        $hideTags = data_get($user, 'prefs.hideTags', false) === true;
    @endphp
    <header class="card-header">
        <p class="card-header-title">Tags / Comments</p>
        <a href="javascript:void(0);" class="card-header-icon" aria-label="more options">
            <span class="icon js-toggle-prefs {{ $hideTags ? 'closed' : 'open' }}">
                <i class="g-icon" data-pref="tags" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M24 24H0V0h24v24z" fill="none" opacity=".87"/><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6-1.41-1.41z"/></svg></i>
                <i class="g-icon" data-pref="tags" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 8l-6 6 1.41 1.41L12 10.83l4.59 4.58L18 14l-6-6z"/></svg></i>
            </span>
        </a>
    </header>
    <div id="tags-card-content" class="card-content meta-wrapper {{ $hideTags ? 'is-hidden' : '' }}" data-load-meta-endpoint="{{ route('laramie::load-meta', ['modelKey' => $model->getType(), 'id' => '_id_']) }}">
        @include('laramie::partials.meta-form')
    </div>
</div>

