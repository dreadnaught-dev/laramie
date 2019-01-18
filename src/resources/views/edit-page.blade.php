@extends('laramie::layout')

@push('extra-header')
    <link href="/laramie/admin/css/trix.css" rel="stylesheet">
    {!! object_get($model, 'editCss', '') !!}
@endpush

@push('scripts')
    <script>
        globals.metaId = '{{ $metaId }}';
        globals.errorMessages = {!! json_encode($errorMessages) !!};
    </script>

    <script src="/laramie/admin/js/edit.js"></script>
    <script src="/laramie/admin/js/trix.js"></script>

    @include('laramie::handlebars.reference-options')

    <div class="modal">
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Compare {{ $model->name }} Revisions</p>
                <button class="delete js-hide-modal" onclick="return false;"></button>
            </header>
            <div id="revision-diff" class="modal-card-body">
                <p>Loading...</p>
            </div>
            <footer class="modal-card-foot">
                <a class="button js-hide-modal">Close</a>
            </footer>
        </div>
    </div>

    @include('laramie::handlebars.meta-tags-comments')

    {!! object_get($model, 'editJs', '') !!}
@endpush

@section('content')
    <div class="column is-12-touch is-10-desktop">
        <div class="columns is-tablet">
            <div class="column is-7-tablet is-8-fullhd">
                @include('laramie::partials.edit.edit-form')

                @if ($item->_isUpdate)
                    <form id="delete-form" action="{{ route('laramie::delete-item', ['modelKey' => $model->_type, 'id' => $item->id]) }}" method="POST" style="display: none;">
                        <input type="hidden" name="_method" value="DELETE">
                        {{ csrf_field() }}
                    </form>
                @endif
            </div>
            <div class="column is-5-tablet is-4-fullhd">
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

