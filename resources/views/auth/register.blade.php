@extends('layouts.app', ['title' => 'Join'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-1 text-2xl font-black">Join Beercheck</h1>
    <p class="mb-4 text-sm text-stone-600">Free forever. You must be 18 or older.</p>
    <form method="POST" action="{{ route('register') }}" class="space-y-3">
        @csrf
        <x-input label="Full name" name="name" required autocomplete="name" />
        <x-input label="Username" name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]+" autocomplete="username" />
        <x-input label="Email" name="email" type="email" required autocomplete="email" />
        <x-input label="Mobile (optional)" name="mobile" type="tel" autocomplete="tel" placeholder="0412 345 678" />
        {{-- Client-side 18+ constraint; the server enforces it again (MinimumAge rule). --}}
        <x-input label="Date of birth" name="date_of_birth" type="date" required max="{{ $maxDob }}" min="1900-01-01" />
        <x-input label="Password" name="password" type="password" required minlength="8" autocomplete="new-password" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="terms" value="1" required class="mt-1">
            <span>I confirm I am 18+ and agree to the terms of use.</span>
        </label>
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Create account</button>
    </form>
    <p class="mt-4 text-center text-sm">Already a member? <a href="{{ route('login') }}" class="font-semibold underline">Log in</a></p>
</div>
@endsection
