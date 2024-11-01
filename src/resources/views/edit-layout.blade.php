@extends('laramie::layout')

@push('extra-header')
    <link href="/laramie/admin/css/trix.css" rel="stylesheet">
    {!! implode('', data_get($model, 'editCss', [])) !!}
@endpush

@push('scripts')
    <script>
        globals.metaId = '{{ $metaId }}';
        globals.errorMessages = {!! json_encode($errorMessages) !!};
    </script>

    <script src="/laramie/admin/js/edit.js"></script>
    <script src="/laramie/admin/js/trix.esm.js" type="module"></script>

    @include('laramie::handlebars.reference-options')

    <div id="revisions-modal" class="modal">
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

    <div id="markdown-preview-modal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Markdown Preview</p>
                <button class="delete js-hide-modal" onclick="return false;"></button>
            </header>
            <div class="modal-card-body">
                <div class="content">Loading...</div>
            </div>
            <footer class="modal-card-foot">
                <a class="button js-hide-modal">Close</a>
            </footer>
        </div>
    </div>

    @include('laramie::handlebars.meta-tags-comments')

    {!! implode('', data_get($model, 'editJs', [])) !!}

    @if ($item->_isUpdate)
        <form id="delete-form" action="{{ route('laramie::delete-item', ['modelKey' => $model->_type, 'id' => $item->id]) }}" method="POST" style="display: none;">
            <input type="hidden" name="_method" value="DELETE">
            {{ csrf_field() }}
        </form>
    @endif
@endpush

@section('content')
    @yield('content')
@endsection

