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

                if ($model->isEditable) {
                    $addLink = '<a class="button is-light is-pulled-right" href="'.route('laramie::edit', ['modelKey' => $modelKeyOrChild, 'id' => 'new']).'"><span class="icon"><i class="fas fa-'. ($model->isSingular ? 'pencil-alt' : 'plus') .'"></i></span></a>';
                }

                if (!$model->isSingular) {
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
