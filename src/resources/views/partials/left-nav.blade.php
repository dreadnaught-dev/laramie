<aside class="menu">
    <p class="menu-label">Content Types</p>
    <ul class="menu-list">
        @php
            $tmp = new \Laramie\Lib\MenuHelper($menu, $user);
            $tmp->printMenu();
        @endphp

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
