<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f59e0b">
    <title>{{ isset($title) ? $title.' · ' : '' }}Beercheck</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
<header class="sticky top-0 z-[1000] border-b border-amber-200 bg-amber-400/95 backdrop-blur">
    <nav class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2" x-data="{ open: false }">
        <a href="{{ route('home') }}" class="text-xl font-black tracking-tight">🍺 Beercheck</a>
        <div class="ml-auto flex items-center gap-2 text-sm">
            @auth
                <a href="{{ route('prices.create') }}" class="rounded-full bg-stone-900 px-3 py-1.5 font-semibold text-white">📷 Post a price</a>
                <button @click="open = !open" class="rounded-full bg-white/70 px-3 py-1.5 font-semibold">
                    {{ auth()->user()->username }} · {{ number_format(auth()->user()->points) }} pts
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-4 top-12 w-48 rounded-lg bg-white p-2 shadow-lg">
                    <a class="block rounded px-3 py-2 hover:bg-stone-100" href="{{ route('profile') }}">My profile</a>
                    @if(auth()->user()->hasRole('publican', 'admin'))
                        <a class="block rounded px-3 py-2 hover:bg-stone-100" href="{{ route('publican.dashboard') }}">Publican</a>
                    @endif
                    @can('admin')
                        <a class="block rounded px-3 py-2 hover:bg-stone-100" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endcan
                    <a class="block rounded px-3 py-2 hover:bg-stone-100" href="{{ route('beers.create') }}">Suggest a beer</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full rounded px-3 py-2 text-left hover:bg-stone-100">Log out</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="font-semibold">Log in</a>
                <a href="{{ route('register') }}" class="rounded-full bg-stone-900 px-3 py-1.5 font-semibold text-white">Join (18+)</a>
            @endauth
        </div>
    </nav>
</header>

<main class="mx-auto max-w-5xl px-4 py-4">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-100 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-red-900" role="alert">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

<footer class="mx-auto max-w-5xl px-4 py-8 text-center text-xs text-stone-500">
    Prices are posted by the community and venues. Drink responsibly — Beercheck is for people aged 18+.
</footer>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
