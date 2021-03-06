@extends('laramie::layout')

@section('content')
    <div class="column">
        @include('laramie::partials.alert')
        <h1 class="title">Compare {{ $model->name }} Revisions</h1>
        @include('laramie::partials.revision-comparison-table')
    </div>
@endsection
