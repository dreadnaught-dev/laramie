<?php
    function recursivelyPrintMenu($node, $currentRoute, $user)
    {
        foreach ($node as $friendlyName => $modelKeyOrChild) {
            if (!is_string($modelKeyOrChild)) {
                echo '<li><a>'.$friendlyName.'</a><ul>';
                recursivelyPrintMenu($modelKeyOrChild, $currentRoute, $user);
                echo '</ul></li>';
            } else {
                $hasAccess = $user->isSuperAdmin
                   || $user->isAdmin
                   || in_array($modelKeyOrChild, $user->abilities);
                if (!$hasAccess) {
                    continue;
                }
                $isActive = $currentRoute->hasParameter('modelKey')
                    && $currentRoute->parameter('modelKey') == $modelKeyOrChild;
                echo '<li><a class="'.($isActive ? 'is-active' : '').'" href="'.route('laramie::list', ['modelKey' => $modelKeyOrChild]).'">'.$friendlyName.'</a></li>';
            }
        }
    }
?>
<aside class="menu">
    <p class="menu-label">Content Types</p>
    <ul class="menu-list">
        {{ recursivelyPrintMenu($menu, Route::current(), $user) }}
        @if ($user->isSuperAdmin || $user->isAdmin || in_array('LaramieUpload', $user->abilities))
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'LaramieUpload']) }}">Uploads</a>
            </li>
        @endif
    </ul>
    <hr>
    @if ($user->isSuperAdmin || $user->isAdmin)
        <p class="menu-label">System</p>
        <ul class="menu-list">
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'LaramieUser']) }}">Users</a>
            </li>
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'LaramieRole']) }}">Roles</a>
            </li>
        </ul>
    @endif
</aside>
