@extends('layouts.app', ['title' => 'Join'])

@section('content')
<x-auth-shell title="Join the crew" subtitle="Free forever. Find cheap beer, post prices, earn points. 18+ only.">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input label="Your name" name="name" required autocomplete="name" />
        <x-input label="Username" name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]+" autocomplete="username" autocapitalize="none" hint="Shown on your posts. Letters, numbers, dots and underscores." />
        <x-input label="Email" name="email" type="email" required autocomplete="email" inputmode="email" />
        <x-input label="Mobile (optional)" name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="0412 345 678" />
        {{-- Client-side 18+ constraint; the server enforces it again (MinimumAge rule). --}}
        <x-input label="Date of birth" name="date_of_birth" type="date" required max="{{ $maxDob }}" min="1900-01-01" autocomplete="bday" />
        <x-input label="Password" name="password" type="password" required minlength="8" autocomplete="new-password" hint="8+ characters with letters and numbers." />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-5 w-5 accent-[#B27800]">
            <span>I'm 18 or older and agree to the terms of use.</span>
        </label>
        <button class="btn-beer w-full">Create my account 🍻</button>
    </form>
    <p class="mt-5 text-center text-sm">Already in? <a href="{{ route('login') }}" class="font-bold underline">Log in</a></p>
</x-auth-shell>
@endsection
