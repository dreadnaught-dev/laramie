<aside class="menu" style="width: 200px;">
    <p class="menu-label">Content Types</p>
    <ul class="menu-list">
        @php
            $tmp = new \Laramie\Lib\MenuHelper($menu, $user);
            $tmp->printMenu();
        @endphp

        @if ($user->isSuperAdmin() || $user->isAdmin() || $user->hasAbility('laramieUpload'))
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'laramieUpload']) }}">Uploads</a>
            </li>
        @endif
    </ul>
    <hr>
    @if ($user->isSuperAdmin() || $user->isAdmin())
        <p class="menu-label">System</p>
        <ul class="menu-list">
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'laramieUser']) }}">Users</a>
            </li>
            <li>
                <a href="{{ route('laramie::list', ['modelKey' => 'laramieRole']) }}">Roles</a>
            </li>
        </ul>
    @endif
</aside>
