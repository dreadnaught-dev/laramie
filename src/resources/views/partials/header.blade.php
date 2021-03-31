<?php
    function headerMenu($node, $currentRoute, $user)
    {
        foreach ($node as $friendlyName => $modelKeyOrChild) {
            if (!is_string($modelKeyOrChild)) {
                echo '<a class="navbar-item is-hidden-desktop navbar-link">'.$friendlyName.'</a>';
                headerMenu($modelKeyOrChild, $currentRoute, $user);
            } else {
                $hasAccess = $user->hasAccessToLaramieModel($modelKeyOrChild, \Laramie\Globals::AccessTypes['read']);
                if (!$hasAccess) {
                    continue;
                }
                $isActive = $currentRoute->hasParameter('modelKey')
                    && $currentRoute->parameter('modelKey') == $modelKeyOrChild;
                echo '<a class="navbar-item is-hidden-desktop '.($isActive ? 'is-active' : '').'" href="'.route('laramie::list', ['modelKey' => $modelKeyOrChild]).'">'.$friendlyName.'</a>';
            }
        }
    }
?>

<nav class="navbar has-shadow">
    <div class="navbar-brand">
        <a class="navbar-item" href="{{ route('laramie::dashboard') }}">
            <span class="title is-2">{{ config('laramie.site_name') }}</span>
        </a>
        <div class="nav-toggle navbar-burger burger" data-target="navMenuExample" id="nav-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="navbar-menu" id="nav-menu">
        <div class="navbar-end">

            <div class="navbar-item has-dropdown is-hoverable">
                <div class="navbar-link" title="Notifications">
                    <span class="icon" style="font-size: 1.2em;">
                        <i class="g-icon"></i>
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/></svg>
                        </span>
                    </span>
                </div>
                <div class="navbar-dropdown is-right">
                    @forelse ($alerts as $alert)
                    <div class="navbar-item">
                        <article class="clearfix media margin-bottom is-small">
                            <figure class="media-left">
                                <span class="tag is-rounded is-medium" style="background-color: {{ $alert->getColor() }};">{{ $alert->getUserInitial() }}</span>
                            </figure>
                            <div class="media-content">
                                <div class="content">
                                    <p class="is-size-7 is-marginless">
                                        <strong>{{ $alert->getAuthorName() }}</strong> {{ $alert->getHumanReadableCreatedDate() }}
                                        @if (data_get($alert, 'metaItemId'))
                                            <a class="is-italic" href="{{ route('laramie::alert-redirector', ['id' => $alert->metaItemId]) }}">view in context &rarr;</a>
                                        @endif
                                    </p>
                                    {!! $alert->getMessage() !!}
                                </div>
                            </div>
                            <div class="media-right">
                                <button title="Dismiss alert" class="delete is-delete is-dismiss-alert" data-id="{{$alert->id}}"></button>
                            </div>
                        </article>
                    </div>
                    @empty
                    <div class="navbar-item">
                        <p>No new notifications.</p>
                    </div>
                    @endforelse
                </div>
            </div>
            <div class="navbar-item has-dropdown is-hoverable">
                <a class="navbar-link" href="{{ route('laramie::profile') }}">
                    Hi, {{ $user->getHandle() }}
                </a>
                <div class="navbar-dropdown is-right">
                    <a class="navbar-item" href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                 document.getElementById('logout-form').submit();">
                        <span class="icon"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><g><path d="M0,0h24v24H0V0z" fill="none"/></g><g><path d="M17,8l-1.41,1.41L17.17,11H9v2h8.17l-1.58,1.58L17,16l4-4L17,8z M5,5h7V3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h7v-2H5V5z"/></g></svg></i></span>&nbsp;Sign out
                    </a>
                </div>
            </div>

            <div class="navbar-item has-dropdown is-hidden-desktop">
                <a class="navbar-link is-active">Content types</a>
                <div class="navbar-dropdown">
                    {{ headerMenu($menu, Route::current(), $user) }}
                </div>
            </div>

            <div class="navbar-item has-dropdown is-hidden-desktop">
                <a class="navbar-link is-active">System</a>
                <div class="navbar-dropdown">
                    <a class="navbar-item" href="{{ route('laramie::list', ['modelKey' => 'laramieRole']) }}">Roles</a>
                    <a class="navbar-item" href="{{ route('laramie::list', ['modelKey' => 'laramieUser']) }}">Users</a>
                </div>
            </div>
        </div>
    </div>
</nav>
