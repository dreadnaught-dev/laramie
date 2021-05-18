@extends('laramie::mfa.layout')

@section('content')
    <p class="title has-text-centered">Complete login</p>

    <form action="{{ route('laramie::mfa-recovery') }}" method="post">
        @csrf
        <div class="field">
            <label class="label">Recovery Code</label>
            <div class="control">
                <input class="input {{$errors->any() ? 'is-danger' : ''}}" type="text" name="recovery-code" placeholder="123456">
            </div>
            @if ($errors->any())
            <p class="help is-danger">{{ $errors->first() }}</p>
            @endif
        </div>

        <div class="field is-grouped">
            <div class="control">
                <button class="button is-link">Verify</button>
            </div>
        </div>
    </form>
@endsection
