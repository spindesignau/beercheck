<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F7BB0E">
    <meta name="description" content="Beercheck — beer prices at your fingertips. Find the cheapest beer near you.">
    @unless (app()->isProduction() && ! config('beercheck.demo.enabled'))
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <title>{{ isset($title) ? $title.' · ' : '' }}Beercheck</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta property="og:title" content="Beercheck — beer prices at your fingertips">
    <meta property="og:image" content="{{ asset('images/brand/logo-horizontal.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh overflow-x-clip">
@if (config('beercheck.demo.enabled'))
    <div class="bg-ink px-4 py-1 text-center text-[11px] font-semibold tracking-wide text-beer">STAGING · demo venues &amp; prices, not real</div>
@endif

{{-- Top app bar --}}
<header class="sticky top-0 z-[1000] border-b border-ink/5 bg-froth/95 backdrop-blur">
    <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-4">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="Beercheck home"><x-logo class="h-9 w-auto" /></a>

        {{-- Desktop nav --}}
        <nav class="ml-6 hidden items-center gap-1 text-sm font-bold md:flex">
            <a href="{{ route('home') }}" @class(['rounded-full px-3 py-1.5', 'bg-beer' => request()->routeIs('home')])>Nearby</a>
            <a href="{{ route('search') }}" @class(['rounded-full px-3 py-1.5', 'bg-beer' => request()->routeIs('search', 'venues.search')])>Search</a>
            <a href="{{ route('beers.cheapest') }}" @class(['rounded-full px-3 py-1.5', 'bg-beer' => request()->routeIs('beers.cheapest')])>Top 3</a>
        </nav>

        <div class="ml-auto flex items-center gap-2">
            @auth
                <a href="{{ route('prices.create') }}" class="btn-beer hidden !py-2 md:inline-flex"><x-icon name="camera" class="h-5 w-5" /> Post a price</a>
                <a href="{{ route('profile') }}" class="flex items-center gap-1.5 rounded-full bg-ink py-1 pl-1 pr-3 text-sm font-bold text-white" title="Your points">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-beer font-display text-xs text-ink">{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}</span>
                    {{ number_format(auth()->user()->points) }} <span class="text-beer">pts</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="px-2 text-sm font-bold">Log in</a>
                <a href="{{ route('register') }}" class="btn-ink !px-4 !py-2 text-sm">Join</a>
            @endauth
        </div>
    </div>
</header>

<main @class(["mx-auto max-w-5xl pb-28 md:pb-12", "px-4 pt-4" => request()->routeIs("admin.*")])>
    @if (session('status'))
        <div class="mx-4 mt-4 flex items-start gap-3 rounded-2xl bg-ink px-4 py-3 text-sm font-semibold text-white" role="status"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity>
            <span class="text-xl leading-none">🍻</span><span class="flex-1">{{ session('status') }}</span>
            <button @click="show = false" aria-label="Dismiss"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="mx-4 mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">
            <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="mx-auto hidden max-w-5xl px-4 pb-10 text-center text-xs text-ink/50 md:block">
    Prices are posted by the community and venues. Enjoy responsibly. Beercheck is for people aged 18+.
</footer>

{{-- Mobile bottom tab bar --}}
<nav class="pb-safe fixed inset-x-0 bottom-0 z-[1000] border-t border-ink/10 bg-froth/95 backdrop-blur md:hidden" aria-label="Main">
    <div class="mx-auto flex max-w-md items-end px-2 pt-1">
        <x-nav-tab :href="route('home')" icon="pin" label="Nearby" :active="request()->routeIs('home')" />
        <x-nav-tab :href="route('search')" icon="search" label="Search" :active="request()->routeIs('search', 'venues.search')" />
        <div class="flex flex-1 justify-center">
            <a href="{{ route('prices.create') }}" class="-mt-7 flex h-16 w-16 items-center justify-center rounded-full bg-beer text-ink shadow-[0_4px_0_var(--color-gold),0_10px_20px_-6px_rgb(32_29_30/.5)] ring-4 ring-froth transition active:translate-y-0.5" aria-label="Post a price">
                <x-icon name="camera" class="h-8 w-8" />
            </a>
        </div>
        <x-nav-tab :href="route('beers.cheapest')" icon="trophy" label="Top 3" :active="request()->routeIs('beers.cheapest')" />
        @auth
            <x-nav-tab :href="route('profile')" icon="user" label="Me" :active="request()->routeIs('profile', 'publican.*', 'admin.*')" />
        @else
            <x-nav-tab :href="route('login')" icon="user" label="Log in" :active="request()->routeIs('login', 'register')" />
        @endauth
    </div>
</nav>
</body>
</html>
