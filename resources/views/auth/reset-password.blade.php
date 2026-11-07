@extends('layouts.app', ['title' => 'Choose a new password'])

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input label="Email" name="email" type="email" :value="$email" required />
        <x-input label="New password" name="password" type="password" required minlength="8" autocomplete="new-password" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <button class="btn-beer w-full">Save new password</button>
    </form>
</x-auth-shell>
@endsection
