@extends('layouts.app', ['title' => 'Log in'])

@section('content')
<x-auth-shell title="Welcome back" subtitle="Log in to post prices and earn points.">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input label="Email or username" name="login" required autocomplete="username" autocapitalize="none" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" checked class="h-5 w-5 accent-[#B27800]"> Keep me logged in</label>
        <button class="btn-beer w-full">Log in</button>
    </form>
    <div class="mt-5 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="underline">Forgot password?</a>
        <a href="{{ route('register') }}" class="font-bold underline">Create an account</a>
    </div>
</x-auth-shell>
@endsection
