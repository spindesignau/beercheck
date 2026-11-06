@extends('layouts.app', ['title' => 'Log in'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-4 text-2xl font-black">Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <x-input label="Email or username" name="login" required autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Log in</button>
    </form>
    <p class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="underline">Forgot password?</a>
        <a href="{{ route('register') }}" class="font-semibold underline">Create an account</a>
    </p>
</div>
@endsection
