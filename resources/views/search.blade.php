@extends('layouts.app', ['title' => $term ? 'Search: '.$term : 'Search'])

@section('content')
<section class="bg-bubbles px-4 pb-5 pt-5 md:rounded-b-[2rem]">
    <form action="{{ route('search') }}" method="GET">
        <label class="flex items-center gap-2 rounded-full bg-white px-4 py-3 shadow-lg">
            <x-icon name="search" class="h-5 w-5 text-ink/40" />
            <input name="q" value="{{ $term }}" @if(! $term) autofocus @endif placeholder="Pub, suburb, postcode or beer"
                   class="min-w-0 flex-1 bg-transparent font-semibold outline-none placeholder:font-medium placeholder:text-ink/40" autocomplete="off" enterkeyhint="search">
            @if ($term)<a href="{{ route('search') }}" class="text-ink/40" aria-label="Clear"><x-icon name="x" class="h-5 w-5" /></a>@endif
        </label>
    </form>
</section>
<div class="foam-edge md:hidden" aria-hidden="true"></div>

<div class="space-y-6 px-4 pt-3">
    @if (! $term)
        <div class="py-6 text-center">
            <x-logo variant="pin" class="mx-auto h-20 w-auto animate-bob" />
            <p class="mt-4 font-display text-xl">What are you after?</p>
            <p class="text-sm text-ink/60">Try a pub name, a suburb, a postcode like 3000, or a beer like “Carlton”.</p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                @foreach (['Carlton Draught', 'Coopers', 'Pale Ale', 'Guinness', 'Fitzroy', '3000'] as $suggestion)
                    <a href="{{ route('search', ['q' => $suggestion]) }}" class="chip">{{ $suggestion }}</a>
                @endforeach
            </div>
        </div>
    @else
        @if ($venues->isEmpty() && $beers->isEmpty())
            <div class="card p-6 text-center">
                <p class="font-bold">Nothing matches “{{ $term }}”.</p>
                <p class="mt-1 text-sm text-ink/60">Check the spelling, or <a class="font-bold underline" href="{{ route('beers.create') }}">suggest a missing beer</a>.</p>
            </div>
        @endif

        @if ($beers->isNotEmpty())
            <section>
                <h2 class="mb-2 text-lg">Beers <span class="font-sans text-sm font-semibold text-ink/50">· tap for the cheapest near you</span></h2>
                <ul class="card divide-y divide-ink/5 overflow-hidden">
                    @foreach ($beers as $beer)
                        <li>
                            <a href="{{ route('beers.cheapest', ['q' => $beer->brand.' '.$beer->name]) }}" class="flex items-center gap-3 px-4 py-3 active:bg-beer-light/40">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-beer-light text-gold-dark"><x-icon name="beer" class="h-5 w-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold">{{ $beer->displayName() }}</span>
                                    <span class="text-xs text-ink/60">{{ $beer->type->name }}@if($beer->abv) · {{ $beer->abv }}%@endif</span>
                                </span>
                                <x-icon name="chevron" class="h-5 w-5 text-ink/30" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($venues->isNotEmpty())
            <section>
                <h2 class="mb-2 text-lg">Pubs &amp; bars</h2>
                <ul class="card divide-y divide-ink/5 overflow-hidden">
                    @foreach ($venues as $venue)
                        <li>
                            <a href="{{ route('venues.show', $venue) }}" class="flex items-center gap-3 px-4 py-3 active:bg-beer-light/40">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-foam text-gold ring-1 ring-ink/5"><x-icon name="pin" class="h-5 w-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold">{{ $venue->name }}</span>
                                    <span class="block truncate text-xs text-ink/60">{{ $venue->fullAddress() }}</span>
                                </span>
                                <x-icon name="chevron" class="h-5 w-5 text-ink/30" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
@endsection
