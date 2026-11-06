@extends('layouts.app', ['title' => $venue->name])

@section('content')
<article class="space-y-4">
    <header class="rounded-xl bg-white p-4 shadow-sm">
        @unless ($venue->is_active)
            <p class="mb-2 rounded bg-stone-200 px-2 py-1 text-xs font-semibold">Inactive venue (visible to admins only)</p>
        @endunless
        <h1 class="text-2xl font-black">{{ $venue->name }}</h1>
        <p class="text-stone-600">{{ $venue->fullAddress() }}</p>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            @if ($distanceM)<span>📍 {{ $distanceM < 1000 ? round($distanceM).' m' : number_format($distanceM / 1000, 1).' km' }} away</span>@endif
            @if ($venue->phone)<a class="underline" href="tel:{{ preg_replace('/\s+/', '', $venue->phone) }}">📞 {{ $venue->phone }}</a>@endif
            @if ($venue->website)<a class="underline" href="{{ $venue->website }}" rel="nofollow noopener" target="_blank">🌐 Website</a>@endif
            @if ($avgRating)<span><x-stars :rating="$avgRating" /> {{ $avgRating }} ({{ $ratingCount }})</span>@endif
            <a class="underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $venue->latitude }}&mlon={{ $venue->longitude }}#map=18/{{ $venue->latitude }}/{{ $venue->longitude }}">🗺 Map</a>
        </div>
        @if ($venue->description)<p class="mt-3 text-sm">{{ $venue->description }}</p>@endif
        @if ($venue->opening_hours)
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 text-sm">
                @foreach ($venue->opening_hours as $day => $hours)<dt class="font-semibold capitalize">{{ $day }}</dt><dd>{{ $hours }}</dd>@endforeach
            </dl>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('prices.create', ['venue' => $venue->id]) }}" class="rounded-full bg-amber-500 px-4 py-2 font-bold text-white">+ Post a Price</a>
            @if ($canManage)
                <a href="{{ route('publican.venues.edit', $venue) }}" class="rounded-full border border-stone-300 px-4 py-2 font-semibold">Manage venue</a>
            @elseauth
                <a href="{{ route('publican.claim.create', ['venue' => $venue->id]) }}" class="px-2 py-2 text-sm underline">Own this venue?</a>
            @endif
        </div>
    </header>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-2 text-lg font-bold">Current prices</h2>
        @forelse ($currentPrices as $p)
            <div class="flex items-center justify-between border-b border-stone-100 py-2 last:border-0">
                <div>
                    <span class="font-semibold">{{ trim($p->brand.' '.$p->beer_name) }}</span>
                    <span class="text-sm text-stone-500">· {{ ucfirst($p->size) }}</span>
                    @if ($p->is_venue_listed)
                        <span class="ml-1 whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">✓ Venue listed</span>
                    @else
                        <span class="ml-1 whitespace-nowrap rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">Punter price</span>
                    @endif
                </div>
                <span class="text-lg font-black">${{ number_format($p->price, 2) }}</span>
            </div>
        @empty
            <p class="text-stone-500">No recent prices. Be the first to post one!</p>
        @endforelse
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold">Photos & reviews</h2>
        @foreach ($feed as $post)
            <div class="mb-3 rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="font-semibold">{{ $post->beer->displayName() }}</span>
                        <span class="text-sm text-stone-500">· {{ ucfirst($post->size->value) }} · ${{ number_format($post->price, 2) }}</span>
                        <div class="text-xs text-stone-500">
                            @if ($post->is_venue_listed)
                                <span class="font-bold text-emerald-700">Official venue price</span>
                            @else
                                by <a class="underline" href="{{ route('profile.public', $post->user->username) }}">{{ $post->user->username }}</a>
                            @endif
                            · <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
                        </div>
                    </div>
                    @if ($post->rating)<x-stars :rating="$post->rating" />@endif
                </div>
                @if ($post->photoUrl())
                    <img src="{{ $post->photoUrl() }}" alt="Photo of {{ $post->beer->displayName() }}" loading="lazy" class="mt-2 max-h-80 w-full rounded-lg object-cover">
                @endif
                @if ($post->review_text)<p class="mt-2 whitespace-pre-line">{{ $post->review_text }}</p>@endif
                @if ($post->reply)
                    <div class="mt-3 rounded-lg border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm">
                        <div class="font-bold text-emerald-800">Reply from the venue</div>
                        <p class="whitespace-pre-line">{{ $post->reply->reply_text }}</p>
                    </div>
                @endif
                @if ($canManage && ! $post->is_venue_listed && $post->hasReview())
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer font-semibold">{{ $post->reply ? 'Edit reply' : 'Reply as venue' }}</summary>
                        <form method="POST" action="{{ route('publican.replies.upsert', $post) }}" class="mt-2 space-y-2">
                            @csrf @method('PUT')
                            <textarea name="reply_text" rows="3" required class="w-full rounded-lg border-stone-300">{{ $post->reply?->reply_text }}</textarea>
                            <button class="rounded-lg bg-emerald-600 px-3 py-1 font-semibold text-white">Publish reply</button>
                        </form>
                    </details>
                @endif
            </div>
        @endforeach
        {{ $feed->links() }}
    </section>
</article>
@endsection
