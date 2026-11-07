@extends('layouts.app', ['title' => 'Reset password'])

@section('content')
<x-auth-shell title="Forgot your password?" subtitle="We'll email you a link to set a new one.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" required autocomplete="email" />
        <button class="btn-beer w-full">Email me a reset link</button>
    </form>
</x-auth-shell>
@endsection
