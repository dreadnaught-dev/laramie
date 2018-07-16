@extends('laramie::two-factor.layout')

@section('content')
    <div class="title m-b-md">
        DUO registration
    </div>
    <p>Before you may continue, you must first register with DUO by scanning the QR code below:</p>
    <img src="{{ object_get($registrationInfo, 'response.activation_barcode') }}" alt="Register with DUO">
    <p>Once you've scanned the QR code with DUO, <a href="{{ route('laramie::dashboard') }}">click here</a> to access the admin.</p>
@endsection
