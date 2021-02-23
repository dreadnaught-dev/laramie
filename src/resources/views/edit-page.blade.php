@extends('laramie::edit-layout')

@section('content')
    <div class="column">
        <div class="columns">
            <div class="column">
                <h1 class="title">{{ $model->isSingular ? $model->name : $model->namePlural }} <?php if (!$model->isSingular): ?><a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="fas fa-plus"></i>&nbsp;Add new</a><?php endif; ?></h1>

                @include('laramie::partials.alert')

                @include('laramie::partials.edit.edit-form')

                @if (data_get($model, 'refs', null))
                <hr class="hr">
                @foreach (data_get($model, 'refs') as $ref)
                    <div class="reference-panel" data-type="{{ $model->_type }}" data-lookup-type="{{ $ref->type }}" data-field="{{ $ref->field }}">
                        <h4 class="title is-4">{{ $ref->label }}</h4>
                        <p class="control">
                            <input class="input keywords" type="text" placeholder="Quick Search" title="Quickly search by {{ $ref->quickSearch }}">
                        </p>
                        <br>
                        <table class="table is-fullwidth is-hoverable">
                            <thead>
                                <tr>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="alias">{{ data_get($model, 'alias') }}</a></th>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="created_at">Created</a></th>
                                    <th><a href="javascript:void(0);" class="js-sort" data-field="exists">Belongs to {{ $model->name }}?</a></th>
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
