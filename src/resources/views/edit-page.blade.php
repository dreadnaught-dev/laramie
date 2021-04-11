@extends('laramie::edit-layout')

@section('content')
    <div class="column">
        <div class="columns">
            <div class="column">
                <h1 class="title">{{ $model->isSingular() ? $model->getName() : $model->getNamePlural() }} <?php if (!$model->isSingular() && $user->hasAccessToLaramieModel($model->getType(), 'create')): ?><a href="{{ route('laramie::edit', ['modelKey' => $model->getType(), 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></i>&nbsp;Add new</a><?php endif; ?></h1>

                @include('laramie::partials.alert')

                @include('laramie::partials.edit.edit-form')

                @if ($refs = $model->getRefs())
                <hr class="hr">
                @foreach ($refs as $ref)
                    <div class="reference-panel" data-type="{{ $model->getType() }}" data-lookup-type="{{ $ref->getType() }}" data-field="{{ $ref->getField() }}">
                        <h4 class="title is-4">{{ $ref->getLabel() }}</h4>
                        <p class="control">
                            <input class="input keywords" type="text" placeholder="Quick Search" title="Quickly search by {{ implode(', ', $ref->getQuickSearch()) }}">
                        </p>
                        <br>
                        <table class="table is-fullwidth is-hoverable">
                            <thead>
                                <tr>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="alias">{{ $model->getAlias() }}</a></th>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="created_at">Created</a></th>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="exists">Belongs to {{ $model->getName() }}?</a></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                @endforeach
                @endif
            </div>
            <div class="column is-narrow edit-sidebar">
                @foreach ($sidebars as $sidebar => $data)
                    @if ($data)
                        @include($sidebar, $data)
                    @else
                        @include($sidebar)
                    @endif

                    @if (!$loop->last)
                        <hr>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection
