@extends('laramie::layout')

@php
    function recursivelyPrintMenus($node, $depth, $user, $dataService)
    {
        foreach ($node as $friendlyName => $modelKeyOrChild) {
            if (!is_string($modelKeyOrChild)) {
                echo '<h4 class="title is-4">'.$friendlyName.'</h4><div class="columns is-multiline">';
                recursivelyPrintMenus($modelKeyOrChild, $depth + 1, $user, $dataService);
                echo '</div>';
            } else {
                $hasAccess = $user->hasAccessToLaramieModel($modelKeyOrChild, \Laramie\Globals::AccessTypes['read']);
                if (!$hasAccess) {
                    continue;
                }
                $prefix = $suffix = '';
                if ($depth === 0) {
                    $prefix = '<div class="columns">';
                    $suffix = '</div>';
                }

                $meta = $dataService->getMetaInformation($modelKeyOrChild);

                $lastModified = '';
                if ($meta->updatedAt) {
                    $lastModified = sprintf('<p><small>Last modified by %s on %s</small></p>',
                        $meta->user ?: '??',
                        \Carbon\Carbon::parse($meta->updatedAt)->toDayDateTimeString());
                }

                $numItems = $meta->count . ' ' . \Str::plural('item', $meta->count);
                $listLink = route('laramie::list', ['modelKey' => $modelKeyOrChild]);
                $addLink = '';
                $model = $dataService->getModelByKey($modelKeyOrChild);

                if ($model->isEditable()) {
                    $addLink = '<a class="button is-light is-pulled-right" href="'.route('laramie::edit', ['modelKey' => $modelKeyOrChild, 'id' => 'new']).'"><span class="icon"><i class="g-icon">'. ($model->isSingular() ? '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M14.06 9.02l.92.92L5.92 19H5v-.92l9.06-9.06M17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75z"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>') .'</i></span></a>';
                }

                if (!$model->isSingular()) {
                    $numItems = " ($numItems)";
                } else {
                    $numItems = '';
                }

                echo <<<EOT
$prefix
<div class="column is-one-third-desktop is-one-half-tablet">
    <div class="box">
        <div class="columns is-vcentered is-gapless is-marginless is-mobile">
            <div class="column is-10">
                <h5 class="title is-5"><a href="$listLink">$friendlyName</a><em class="subtitle is-6 has-text-grey">$numItems</em></h5>
            </div>
            <div class="column is-2">
                $addLink
            </div>
        </div>
        <div class="columns"><div class="column is-10">
            $lastModified
        </div></div>
    </div>
</div>
$suffix
EOT;
            }
        }
    }
@endphp

@section('content')
    <div class="column">
        <h2 class="title is-2">Dashboard</h2>
        @php
            $alert = session('alert', null);
        @endphp
        @include('laramie::partials.alert', ['alert' => $alert])
        <hr class="hr">
        {{ recursivelyPrintMenus($menu, 0, $user, $dataService) }}
    </div>
@endsection
