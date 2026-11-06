@extends('layouts.app', ['title' => 'Reset password'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-4 text-2xl font-black">Reset your password</h1>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-3">
        @csrf
        <x-input label="Email" name="email" type="email" required />
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Email me a reset link</button>
    </form>
</div>
@endsection
