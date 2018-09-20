@extends('laramie::mfa.layout')

@section('content')
    <p class="title has-text-centered">Setup Multifactor Authentication</p>
    <p>Scan the barcode with <a target="_blank" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en_US">
    Google Authenticator</a> (or whatever OTP software you would like to use) to setup multifactor auth.</p>

    <form action="{{ route('laramie::mfa-register') }}" method="post">
        @csrf

        <figure class="image">
            <img style="width: 200px; height: 200px; margin: 0 auto;" src="{{ $qrCodeImage }}">
        </figure>

        <p>Once you've scanned the barcode above, enter the code from the MFA application to verify your account.</p>

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
                <button class="button is-link">Complete registration</button>
            </div>
        </div>
    </form>
@endsection
