@extends('layouts.app', ['title' => $venue->name])

@section('content')
<article>
    <header class="bg-bubbles px-4 pb-6 pt-5 text-white md:rounded-b-[2rem]">
        @unless ($venue->is_active)
            <p class="mb-2 inline-block rounded-full bg-white/15 px-3 py-0.5 text-xs font-bold">Inactive · visible to admins only</p>
        @endunless
        <h1 class="text-[1.9rem] leading-tight">{{ $venue->name }}</h1>
        <p class="mt-1 text-sm text-white/75">{{ $venue->fullAddress() }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-semibold">
            @if ($avgRating)<span class="flex items-center gap-1"><x-stars :rating="$avgRating" /> {{ $avgRating }} <span class="text-white/60">({{ $ratingCount }})</span></span>@endif
            @if ($distanceM)<span class="flex items-center gap-1 text-beer"><x-icon name="pin" class="h-4 w-4" /> {{ $distanceM < 1000 ? round($distanceM).' m' : number_format($distanceM / 1000, 1).' km' }} away</span>@endif
        </div>
    </header>
    <div class="foam-edge md:hidden" aria-hidden="true"></div>

    <div class="space-y-4 px-4 pt-3">
        {{-- Quick actions --}}
        <div class="grid grid-cols-3 gap-3">
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $venue->latitude }},{{ $venue->longitude }}" target="_blank" rel="noopener"
               class="card flex flex-col items-center gap-1 py-3 text-xs font-bold active:scale-95"><x-icon name="directions" class="h-6 w-6 text-gold" /> Directions</a>
            @if ($venue->phone)
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $venue->phone) }}" class="card flex flex-col items-center gap-1 py-3 text-xs font-bold active:scale-95"><x-icon name="phone" class="h-6 w-6 text-gold" /> Call</a>
            @else
                <span class="card flex flex-col items-center gap-1 py-3 text-xs font-bold text-ink/30"><x-icon name="phone" class="h-6 w-6" /> Call</span>
            @endif
            @if ($venue->website)
                <a href="{{ $venue->website }}" target="_blank" rel="nofollow noopener" class="card flex flex-col items-center gap-1 py-3 text-xs font-bold active:scale-95"><x-icon name="globe" class="h-6 w-6 text-gold" /> Website</a>
            @else
                <span class="card flex flex-col items-center gap-1 py-3 text-xs font-bold text-ink/30"><x-icon name="globe" class="h-6 w-6" /> Website</span>
            @endif
        </div>

        @if ($venue->description || $venue->opening_hours)
            <details class="card group p-4">
                <summary class="flex cursor-pointer list-none items-center justify-between font-bold">About &amp; hours <x-icon name="chevron" class="h-5 w-5 transition group-open:rotate-90" /></summary>
                @if ($venue->description)<p class="mt-3 text-sm">{{ $venue->description }}</p>@endif
                @if ($venue->opening_hours)
                    <dl class="mt-3 grid grid-cols-[3rem_1fr] gap-y-1 text-sm">
                        @foreach ($venue->opening_hours as $day => $hours)<dt class="font-bold capitalize">{{ $day }}</dt><dd>{{ $hours }}</dd>@endforeach
                    </dl>
                @endif
            </details>
        @endif

        {{-- Current prices --}}
        <section class="card overflow-hidden">
            <h2 class="bg-beer-bubbles px-4 py-3 text-lg">On tap &amp; prices</h2>
            @forelse ($currentPrices as $p)
                <div class="flex items-center gap-3 border-b border-ink/5 px-4 py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-bold">{{ trim($p->brand.' '.$p->beer_name) }}</div>
                        <div class="mt-0.5 flex items-center gap-2 text-xs text-ink/60">
                            {{ ucfirst($p->size) }}
                            @if ($p->is_venue_listed)
                                <span class="badge-official"><x-icon name="check" class="h-3 w-3" /> Venue price</span>
                            @else
                                <span class="badge-punter">Punter price</span>
                            @endif
                        </div>
                    </div>
                    <span class="price-pill">${{ number_format($p->price, 2) }}</span>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-ink/60">No recent prices here yet. Be the first!</div>
            @endforelse
        </section>

        {{-- Photos & reviews --}}
        <section>
            <h2 class="mb-2 text-lg">Photos &amp; reviews</h2>
            <div class="space-y-3">
            @forelse ($feed as $post)
                <div class="card overflow-hidden">
                    @if ($post->photoUrl())
                        <img src="{{ $post->photoUrl() }}" alt="{{ $post->beer->displayName() }} at {{ $venue->name }}" loading="lazy" class="aspect-[4/3] w-full object-cover">
                    @endif
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-bold">{{ $post->beer->displayName() }}</div>
                                <div class="text-xs text-ink/60">
                                    {{ ucfirst($post->size->value) }} ·
                                    @if ($post->is_venue_listed)
                                        <span class="font-bold text-emerald-700">Official venue price</span>
                                    @else
                                        <a class="font-semibold underline" href="{{ route('profile.public', $post->user->username) }}">{{ '@'.$post->user->username }}</a>
                                    @endif
                                    · <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
                                </div>
                            </div>
                            <span class="price-pill !text-base">${{ number_format($post->price, 2) }}</span>
                        </div>
                        @if ($post->rating)<div class="mt-2"><x-stars :rating="$post->rating" /></div>@endif
                        @if ($post->review_text)<p class="mt-2 whitespace-pre-line text-[15px]">{{ $post->review_text }}</p>@endif

                        @if ($post->reply)
                            <div class="mt-3 rounded-2xl bg-emerald-50 p-3 text-sm ring-1 ring-emerald-200">
                                <div class="font-bold text-emerald-800">Reply from {{ $venue->name }}</div>
                                <p class="mt-0.5 whitespace-pre-line">{{ $post->reply->reply_text }}</p>
                            </div>
                        @endif
                        @if ($canManage && ! $post->is_venue_listed && $post->hasReview())
                            <details class="mt-3 text-sm">
                                <summary class="cursor-pointer font-bold text-emerald-700">{{ $post->reply ? 'Edit your reply' : 'Reply as the venue' }}</summary>
                                <form method="POST" action="{{ route('publican.replies.upsert', $post) }}" class="mt-2 space-y-2">
                                    @csrf @method('PUT')
                                    <textarea name="reply_text" rows="3" required class="field">{{ $post->reply?->reply_text }}</textarea>
                                    <button class="btn-ink !py-2 text-sm">Publish reply</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card p-6 text-center text-sm text-ink/60">No photos or reviews yet.</div>
            @endforelse
            </div>
            <div class="mt-3">{{ $feed->links() }}</div>
        </section>

        <div class="flex flex-wrap justify-center gap-4 pb-4 text-sm">
            @if ($canManage)
                <a href="{{ route('publican.venues.edit', $venue) }}" class="font-bold underline">Manage this venue</a>
            @else
                <a href="{{ route('publican.claim.create', ['venue' => $venue->id]) }}" class="font-semibold text-ink/60 underline">Own this venue? Claim it</a>
            @endif
        </div>
    </div>

    {{-- Sticky post button --}}
    <div class="pointer-events-none sticky bottom-24 z-[900] flex justify-center px-4 md:bottom-6">
        <a href="{{ route('prices.create', ['venue' => $venue->id]) }}" class="btn-beer pointer-events-auto shadow-xl"><x-icon name="plus" class="h-5 w-5" /> Post a Price here</a>
    </div>
</article>
@endsection
