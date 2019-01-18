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
    @yield('content')
@endsection

