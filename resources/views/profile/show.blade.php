@extends('layouts.app', ['title' => $user->username])

@php($level = $user->level())

@section('content')
<section class="bg-bubbles px-4 pb-6 pt-6 text-white md:rounded-b-[2rem]">
    <div class="flex items-center gap-4">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-beer font-display text-2xl text-ink ring-4 ring-white/10">{{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}</div>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl">{{ '@'.$user->username }}</h1>
            <p class="text-sm font-semibold text-beer">{{ $level['name'] }}</p>
            <p class="text-xs text-white/60">{{ $user->role->label() }} · since {{ $user->created_at->format('M Y') }}</p>
        </div>
        <div class="text-right">
            <div class="font-display text-3xl text-beer">{{ number_format($user->points) }}</div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-white/60">points</div>
        </div>
    </div>

    {{-- Beer-glass progress bar --}}
    <div class="mt-5">
        <div class="relative h-5 overflow-hidden rounded-full bg-white/10 ring-1 ring-white/20">
            <div class="bg-beer-bubbles h-full rounded-full transition-all" style="width: {{ max(4, $level['progress']) }}%"></div>
            <div class="absolute inset-y-0 rounded-r-full bg-white/90" style="left: calc({{ max(4, $level['progress']) }}% - 6px); width: 8px" aria-hidden="true"></div>
        </div>
        <p class="mt-1.5 text-xs text-white/70">
            @if ($level['next']) {{ $level['to_next'] }} pts to <b class="text-beer">{{ $level['next'] }}</b> @else Top of the ladder. Legend. @endif
        </p>
    </div>
</section>
<div class="foam-edge md:hidden" aria-hidden="true"></div>

<div class="space-y-4 px-4 pt-3">
    @if ($isOwner)
        <nav class="card divide-y divide-ink/5 overflow-hidden font-bold">
            <a href="{{ route('prices.create') }}" class="flex items-center gap-3 px-4 py-3.5"><x-icon name="camera" class="h-5 w-5 text-gold" /> Post a price <x-icon name="chevron" class="ml-auto h-5 w-5 text-ink/30" /></a>
            <a href="{{ route('beers.create') }}" class="flex items-center gap-3 px-4 py-3.5"><x-icon name="beer" class="h-5 w-5 text-gold" /> Suggest a beer <x-icon name="chevron" class="ml-auto h-5 w-5 text-ink/30" /></a>
            @if ($user->hasRole('publican', 'admin'))
                <a href="{{ route('publican.dashboard') }}" class="flex items-center gap-3 px-4 py-3.5"><x-icon name="pin" class="h-5 w-5 text-gold" /> My venues <x-icon name="chevron" class="ml-auto h-5 w-5 text-ink/30" /></a>
            @else
                <a href="{{ route('publican.claim.create') }}" class="flex items-center gap-3 px-4 py-3.5"><x-icon name="pin" class="h-5 w-5 text-gold" /> Own a pub? Claim it <x-icon name="chevron" class="ml-auto h-5 w-5 text-ink/30" /></a>
            @endif
            @can('admin')
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3.5"><x-icon name="sliders" class="h-5 w-5 text-gold" /> Admin panel <x-icon name="chevron" class="ml-auto h-5 w-5 text-ink/30" /></a>
            @endcan
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="flex w-full items-center gap-3 px-4 py-3.5 text-left text-ink/60"><x-icon name="logout" class="h-5 w-5" /> Log out</button>
            </form>
        </nav>

        <section class="card p-4">
            <h2 class="mb-2 text-lg">Points history</h2>
            @forelse ($events as $e)
                <div class="flex items-center justify-between border-b border-ink/5 py-2 text-sm last:border-0">
                    <span>{{ ucfirst(str_replace('_', ' ', $e->event_type->value)) }} <span class="text-ink/40">· {{ $e->created_at->diffForHumans() }}</span></span>
                    <span class="font-display {{ $e->points_awarded < 0 ? 'text-red-600' : 'text-gold' }}">{{ $e->points_awarded > 0 ? '+' : '' }}{{ $e->points_awarded }}</span>
                </div>
            @empty
                <p class="text-sm text-ink/60">No points yet. <a class="font-bold underline" href="{{ route('prices.create') }}">Post your first price</a>!</p>
            @endforelse
        </section>

        @if ($pendingBeers->isNotEmpty())
            <section class="card p-4">
                <h2 class="mb-2 text-lg">Your beer suggestions</h2>
                @foreach ($pendingBeers as $b)
                    <div class="flex justify-between py-1.5 text-sm"><span>{{ $b->displayName() }}</span><span class="font-bold capitalize {{ ['approved' => 'text-emerald-700', 'rejected' => 'text-red-600', 'pending' => 'text-ink/50'][$b->status->value] }}">{{ $b->status->value }}</span></div>
                @endforeach
            </section>
        @endif

        @if ($claims->isNotEmpty())
            <section class="card p-4">
                <h2 class="mb-2 text-lg">Venue claims</h2>
                @foreach ($claims as $c)
                    <div class="flex justify-between py-1.5 text-sm"><span>{{ $c->venue->name }}, {{ $c->venue->suburb }}</span><span class="font-semibold text-ink/60">{{ str_replace('_', ' ', $c->status->value) }}</span></div>
                @endforeach
            </section>
        @endif
    @endif

    <section class="card p-4">
        <h2 class="mb-2 text-lg">{{ $isOwner ? 'Your posts' : 'Recent posts' }}</h2>
        @forelse ($prices as $p)
            <a href="{{ route('venues.show', $p->venue_id) }}" class="flex items-center gap-3 border-b border-ink/5 py-2.5 last:border-0">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">{{ $p->beer->displayName() }} <span class="font-medium text-ink/50">· {{ $p->size->value }}</span></span>
                    <span class="block truncate text-xs text-ink/60">{{ $p->venue->name }} · {{ $p->created_at->diffForHumans() }}</span>
                </span>
                <span class="price-pill !text-sm">${{ number_format($p->price, 2) }}</span>
            </a>
        @empty
            <p class="text-sm text-ink/60">Nothing posted yet.</p>
        @endforelse
        <div class="mt-2">{{ $prices->links() }}</div>
    </section>
</div>
@endsection
