@extends('layouts.app', ['title' => $user->username])

@section('content')
<div class="space-y-4">
    <header class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-200 text-2xl font-black">{{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}</div>
        <div>
            <h1 class="text-xl font-black">{{ $user->username }}</h1>
            <p class="text-sm text-stone-600">{{ $user->role->label() }} · member since {{ $user->created_at->format('M Y') }}</p>
        </div>
        <div class="ml-auto text-right">
            <div class="text-3xl font-black text-amber-500">{{ number_format($user->points) }}</div>
            <div class="text-xs uppercase tracking-wide text-stone-500">points</div>
        </div>
    </header>

    @if ($isOwner)
        <section class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="mb-2 font-bold">Points history</h2>
            @forelse ($events as $e)
                <div class="flex justify-between border-b border-stone-100 py-1 text-sm last:border-0">
                    <span>{{ str_replace('_', ' ', ucfirst($e->event_type->value)) }} <span class="text-stone-400">· {{ $e->created_at->diffForHumans() }}</span></span>
                    <span class="font-bold {{ $e->points_awarded < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $e->points_awarded > 0 ? '+' : '' }}{{ $e->points_awarded }}</span>
                </div>
            @empty
                <p class="text-sm text-stone-500">No points yet — <a class="underline" href="{{ route('prices.create') }}">post a price</a>!</p>
            @endforelse
        </section>

        @if ($pendingBeers->isNotEmpty())
            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="mb-2 font-bold">Your beer suggestions</h2>
                @foreach ($pendingBeers as $b)
                    <div class="flex justify-between py-1 text-sm"><span>{{ $b->displayName() }}</span><span class="capitalize text-stone-500">{{ $b->status->value }}</span></div>
                @endforeach
            </section>
        @endif

        <section class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="mb-2 font-bold">Venue claims</h2>
            @foreach ($claims as $c)
                <div class="flex justify-between py-1 text-sm"><span>{{ $c->venue->name }}, {{ $c->venue->suburb }}</span><span class="text-stone-500">{{ str_replace('_', ' ', $c->status->value) }}</span></div>
            @endforeach
            <a href="{{ route('publican.claim.create') }}" class="mt-2 inline-block text-sm underline">Own or manage a venue? Claim it</a>
        </section>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-2 font-bold">Recent posts</h2>
        @forelse ($prices as $p)
            <a href="{{ route('venues.show', $p->venue_id) }}" class="flex justify-between border-b border-stone-100 py-2 text-sm last:border-0">
                <span>{{ $p->beer->displayName() }} ({{ $p->size->value }}) at {{ $p->venue->name }}</span>
                <span class="font-bold">${{ number_format($p->price, 2) }}</span>
            </a>
        @empty
            <p class="text-sm text-stone-500">Nothing posted yet.</p>
        @endforelse
        {{ $prices->links() }}
    </section>
</div>
@endsection
