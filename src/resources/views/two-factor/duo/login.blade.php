@extends('laramie::two-factor.layout')

@section('content')
    <div class="title m-b-md">
        DUO login
    </div>
    <p class="m-b-md">Please enter a DUO passcode or "push" to receive a push notification on your phone.</p>
    <form action="" method="POST">
        {{ csrf_field() }}
        <input type="text" name="passcode" value="push" placeholder="DUO passcode or 'push'">
        <button type="submit">Login &rarr;</button>
    </form>
@endsection
