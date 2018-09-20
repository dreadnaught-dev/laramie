@extends('laramie::mfa.layout')

@section('content')
    <p class="title has-text-centered">Complete login</p>
    <p>Provide your multifactor code below. This code will come from <a
    target="_blank" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en_US">
    Google Authenticator</a> or whatever OTP software you initially registered your multifactor auth with.</p>

    <form action="{{ route('laramie::mfa-login') }}" method="post">
        @csrf
        <div class="field">
            <label class="label">Multifactor Code</label>
            <div class="control">
                <input class="input {{$errors->any() ? 'is-danger' : ''}}" type="text" name="mfa" placeholder="123456">
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
