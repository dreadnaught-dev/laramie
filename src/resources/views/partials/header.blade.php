<?php
    function headerMenu($node, $currentRoute, $user)
    {
        foreach ($node as $friendlyName => $modelKeyOrChild) {
            if (!is_string($modelKeyOrChild)) {
                echo '<a class="navbar-item is-hidden-desktop navbar-link">'.$friendlyName.'</a>';
                headerMenu($modelKeyOrChild, $currentRoute, $user);
            } else {
                $hasAccess = $user->isSuperAdmin()
                   || $user->isAdmin()
                   || $user->hasAbility($modelKeyOrChild);
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
                <a class="navbar-link" href="{{ route('laramie::list', ['modelKey' => 'laramieAlert']) }}" title="Notifications">
                    <span class="icon" style="font-size: 1.2em;">
                        <span class="fa-layers fa-fw">
                            <i class="far fa-bell"></i>
                            @if (count($alerts))
                                <i class="fas fa-circle" style="color: tomato" data-fa-transform="shrink-8 up-5 right-5"></i>
                            @endif
                        </span>
                    </span>
                </a>
                <div class="navbar-dropdown is-right">
                    @forelse ($alerts as $alert)
                    <div class="navbar-item">
                        <article class="clearfix media margin-bottom is-small">
                            <figure class="media-left">
                                <span class="tag is-rounded is-medium" style="background-color: {{ $alert->_color}};">{{$alert->_userFirstInitial}}</span>
                            </figure>
                            <div class="media-content">
                                <div class="content">
                                    <p class="is-size-7 is-marginless">
                                        <strong>{{$alert->_user}}</strong> {{$alert->lastModified}}
                                        @if (data_get($alert, 'metaId'))
                                            <a class="is-italic" href="{{ route('laramie::alert-redirector', ['id' => $alert->metaId]) }}">view in context &rarr;</a>
                                        @endif
                                    </p>
                                    {!!$alert->html!!}
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
                <a class="navbar-link">
                    Hi, {{ $user->user }}
                </a>
                <div class="navbar-dropdown is-right">
                    <a class="navbar-item" href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                 document.getElementById('logout-form').submit();">
                        <span class="icon"><i class="fas fa-lg fa-sign-out-alt"></i></span>&nbsp;Sign out
                    </a>
                    <hr class="navbar-divider">
                    <div class="navbar-item is-hidden-touch is-pulled-right">
                        <a href="https://github.com/laramie-cms/laramie" target="_blank"><span class="icon" style="color: #333;"><i class="fab fa-lg fa-github"></i></span></a>
                    </div>
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
