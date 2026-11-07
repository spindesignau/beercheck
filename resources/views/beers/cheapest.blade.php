@extends('layouts.app', ['title' => $term ? 'Cheapest '.$term : 'Top 3 cheapest'])

@php
    $priceMin = config('beercheck.prices.filter_min');
    $priceMax = config('beercheck.prices.filter_max');
@endphp

@section('content')
@unless ($hasLocation)
    {{-- Server-rendered page: grab the browser location once, then reload with ?lat&lng. --}}
    <div x-data x-init="window.Beercheck.ensureLocationInUrl()" class="px-4 py-16 text-center">
        <x-logo variant="pin" class="mx-auto h-20 w-auto animate-bob" />
        <p class="mt-4 font-display text-xl">Finding beers near you…</p>
    </div>
@else
<section class="bg-beer-bubbles px-4 pb-4 pt-5 md:rounded-b-[2rem]">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-gold-dark">{{ $term ? 'Beer search' : 'The filter' }}</p>
    <h1 class="text-[1.9rem] leading-tight">{{ $term ? 'Cheapest '.$term : 'Top 3 cheapest' }}</h1>
    <p class="text-sm font-semibold text-ink/70">Within {{ $filters->radiusKm }} km · cheapest first</p>
</section>
<div class="foam-edge-beer md:hidden" aria-hidden="true"></div>

<div class="grid grid-cols-1 gap-4 px-4 pt-3 md:grid-cols-[22rem_minmax(0,1fr)]">
    {{-- Filters --}}
    <form method="GET" action="{{ route('beers.cheapest') }}" class="card space-y-4 p-4 md:self-start"
          x-data="{ open: {{ $term ? 'false' : 'true' }}, min: {{ (float) ($filters->minPrice ?? $priceMin) }}, max: {{ (float) ($filters->maxPrice ?? $priceMax) }}, radius: {{ $filters->radiusKm }} }">
        <input type="hidden" name="lat" value="{{ $filters->lat }}">
        <input type="hidden" name="lng" value="{{ $filters->lng }}">
        @if ($term)<input type="hidden" name="q" value="{{ $term }}">@endif

        <button type="button" @click="open = !open" class="flex w-full items-center justify-between font-display text-lg md:pointer-events-none">
            <span class="flex items-center gap-2"><x-icon name="sliders" class="h-5 w-5" /> Filters</span>
            <x-icon name="chevron" class="h-5 w-5 transition md:hidden" ::class="open && 'rotate-90'" />
        </button>

        <div x-show="open" x-collapse.duration.200ms class="space-y-4 md:!block">
            @unless ($term)
                <div class="grid grid-cols-2 gap-3">
                    <label><span class="label">Brand</span>
                        <select name="brand" class="field !py-2.5">
                            <option value="">Any</option>
                            @foreach ($brands as $brand)<option @selected($filters->brand === $brand)>{{ $brand }}</option>@endforeach
                        </select>
                    </label>
                    <label><span class="label">Type</span>
                        <select name="type_id" class="field !py-2.5">
                            <option value="">Any</option>
                            @foreach ($types as $type)<option value="{{ $type->id }}" @selected($filters->typeId === $type->id)>{{ $type->name }}</option>@endforeach
                        </select>
                    </label>
                </div>
            @endunless

            <fieldset>
                <legend class="label">Glass</legend>
                <div class="flex flex-wrap gap-2">
                    <label class="relative"><input type="radio" name="size" value="" class="peer sr-only" @checked(! $filters->size)><span class="chip peer-checked:bg-ink peer-checked:text-white">Any</span></label>
                    @foreach ($sizes as $s)
                        <label class="relative"><input type="radio" name="size" value="{{ $s->value }}" class="peer sr-only" @checked($filters->size === $s)><span class="chip peer-checked:bg-ink peer-checked:text-white">{{ ucfirst($s->value) }}</span></label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <span class="label">Price <span class="font-display text-gold" x-text="'$' + min + ' – $' + max"></span></span>
                <div class="flex items-center gap-3">
                    <input type="range" name="min_price" min="{{ $priceMin }}" max="{{ $priceMax }}" step="0.5" x-model.number="min" @input="max = Math.max(max, min)" class="w-full accent-[#B27800]" aria-label="Minimum price">
                    <input type="range" name="max_price" min="{{ $priceMin }}" max="{{ $priceMax }}" step="0.5" x-model.number="max" @input="min = Math.min(min, max)" class="w-full accent-[#B27800]" aria-label="Maximum price">
                </div>
            </div>

            <div>
                <span class="label">Distance <span class="font-display text-gold" x-text="radius + ' km'"></span></span>
                <input type="range" name="radius" min="{{ config('beercheck.geo.min_radius_km') }}" max="{{ config('beercheck.geo.max_radius_km') }}" step="1" x-model.number="radius" class="w-full accent-[#B27800]">
            </div>

            <button class="btn-beer w-full"><x-icon name="trophy" class="h-5 w-5" /> {{ $term ? 'Update' : 'Show the top 3' }}</button>
        </div>
    </form>

    {{-- Results --}}
    <section class="space-y-3">
        @forelse ($results as $i => $r)
            <a href="{{ $r['venue']['url'] }}" class="card flex items-center gap-3 p-4 transition active:scale-[.99] {{ $i === 0 ? 'ring-2 ring-beer' : '' }}">
                <span class="w-9 shrink-0 text-center {{ $i < 3 ? 'text-3xl' : 'font-display text-xl text-ink/40' }}">{{ $i < 3 ? ['🥇', '🥈', '🥉'][$i] : $i + 1 }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-bold">{{ $r['beer'] }}</span>
                    <span class="block truncate text-xs text-ink/60">{{ $r['size_label'] }}</span>
                    <span class="block truncate text-xs font-semibold">
                        {{ $r['venue']['name'] }} · {{ $r['distance_m'] < 1000 ? $r['distance_m'].' m' : number_format($r['distance_m'] / 1000, 1).' km' }}
                    </span>
                    @if ($r['is_venue_listed'])<span class="badge-official mt-1"><x-icon name="check" class="h-3 w-3" /> Venue price</span>@endif
                </span>
                <span class="price-pill">{{ $r['price_formatted'] }}</span>
            </a>
        @empty
            <div class="card p-6 text-center">
                <x-logo variant="pin" class="mx-auto h-16 w-auto" />
                <p class="mt-3 font-bold">No matching prices nearby.</p>
                <p class="text-sm text-ink/60">Widen the distance or price range, or be the hero who posts one.</p>
                <a href="{{ route('prices.create') }}" class="btn-ink mt-4"><x-icon name="camera" class="h-5 w-5" /> Post a price</a>
            </div>
        @endforelse
    </section>
</div>
@endunless
@endsection
