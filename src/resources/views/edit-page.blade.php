@extends('laramie::edit-layout')

@section('content')
    <div class="column">
        <div class="columns">
            <div class="column">
                <h1 class="title">{{ $model->isSingular ? $model->name : $model->namePlural }} <?php if (!$model->isSingular): ?><a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="fas fa-plus"></i>&nbsp;Add new</a><?php endif; ?></h1>

                @include('laramie::partials.alert')

                @include('laramie::partials.edit.edit-form')
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

