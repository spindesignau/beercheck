@extends('layouts.app', ['title' => 'Choose a new password'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-4 text-2xl font-black">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input label="Email" name="email" type="email" :value="$email" required />
        <x-input label="New password" name="password" type="password" required minlength="8" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required />
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Reset password</button>
    </form>
</div>
@endsection
