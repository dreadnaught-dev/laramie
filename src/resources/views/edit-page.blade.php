@extends('laramie::edit-layout')

@section('content')
    <div class="column">
        <div class="columns">
            <div class="column">
                <h1 class="title">{{ $model->isSingular ? $model->name : $model->namePlural }} <?php if (!$model->isSingular): ?><a href="{{ route('laramie::edit', ['modelKey' => $model->_type, 'id' => 'new']) }}" class="tag is-primary is-medium"><i class="fas fa-plus"></i>&nbsp;Add new</a><?php endif; ?></h1>

                @include('laramie::partials.alert')

                @include('laramie::partials.edit.edit-form')

                @if ($item->_isUpdate)
                    <form id="delete-form" action="{{ route('laramie::delete-item', ['modelKey' => $model->_type, 'id' => $item->id]) }}" method="POST" style="display: none;">
                        <input type="hidden" name="_method" value="DELETE">
                        {{ csrf_field() }}
                    </form>
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

