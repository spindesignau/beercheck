@extends('layouts.app')

@section('content')
<div x-data="geoDiscovery(@js([
        'fallback' => ['lat' => $geo['default_lat'], 'lng' => $geo['default_lng']],
        'radius' => $geo['default_radius_km'],
        'priceBounds' => $priceBounds,
        'nearbyUrl' => route('venues.nearby'),
        'cheapestUrl' => route('beers.cheapest'),
        'venueSearchUrl' => route('venues.search'),
    ]))" class="space-y-4">

    {{-- Search bar: dual toggle (venues | beers) --}}
    <form :action="searchAction()" method="GET" class="flex gap-2">
        <div class="flex overflow-hidden rounded-full border border-stone-300 bg-white text-sm font-semibold">
            <button type="button" @click="searchMode = 'venues'" :class="searchMode === 'venues' ? 'bg-stone-900 text-white' : ''" class="px-3">Venues</button>
            <button type="button" @click="searchMode = 'beers'" :class="searchMode === 'beers' ? 'bg-stone-900 text-white' : ''" class="px-3">Beers</button>
        </div>
        <input name="q" required minlength="2" class="min-w-0 flex-1 rounded-full border border-stone-300 px-4 py-2"
               :placeholder="searchMode === 'venues' ? 'Venue name, suburb or postcode' : 'Beer brand or name, e.g. Carlton'">
        <template x-if="searchMode === 'beers'">
            <span><input type="hidden" name="lat" :value="lat"><input type="hidden" name="lng" :value="lng"><input type="hidden" name="radius" :value="radius"></span>
        </template>
        <button class="rounded-full bg-amber-500 px-4 font-bold text-white">Go</button>
    </form>

    {{-- Radius slider --}}
    <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
        <label for="radius" class="text-sm font-semibold whitespace-nowrap">Within <span x-text="radius"></span> km</label>
        <input id="radius" type="range" min="{{ $geo['min_radius_km'] }}" max="{{ $geo['max_radius_km'] }}" step="1" x-model.number="radius" class="flex-1 accent-amber-500">
        <button @click="showFilters = !showFilters" class="rounded-full border border-stone-300 px-3 py-1 text-sm font-semibold">Filter ▾</button>
    </div>
    <p x-show="usingFallback" x-cloak class="text-xs text-stone-500">Location unavailable — showing Melbourne CBD. Allow location access for results near you.</p>

    {{-- Filter engine: Top 3 cheapest --}}
    <section x-show="showFilters" x-cloak class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2">
        <label class="text-sm font-semibold">Brand
            <select x-model="filters.brand" class="mt-1 w-full rounded-lg border-stone-300">
                <option value="">Any brand</option>
                @foreach ($brands as $brand)<option>{{ $brand }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-semibold">Type
            <select x-model="filters.type_id" class="mt-1 w-full rounded-lg border-stone-300">
                <option value="">Any type</option>
                @foreach ($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-semibold">Size
            <select x-model="filters.size" class="mt-1 w-full rounded-lg border-stone-300">
                <option value="">Any size</option>
                @foreach ($sizes as $size)<option value="{{ $size->value }}">{{ $size->label() }}</option>@endforeach
            </select>
        </label>
        <div class="text-sm font-semibold">Price: $<span x-text="filters.min_price"></span> – $<span x-text="filters.max_price"></span>
            <div class="flex gap-2">
                <input type="range" min="{{ $priceBounds[0] }}" max="{{ $priceBounds[1] }}" step="0.5" x-model.number="filters.min_price" @input="filters.max_price = Math.max(filters.max_price, filters.min_price)" class="w-full accent-amber-500" aria-label="Minimum price">
                <input type="range" min="{{ $priceBounds[0] }}" max="{{ $priceBounds[1] }}" step="0.5" x-model.number="filters.max_price" @input="filters.min_price = Math.min(filters.min_price, filters.max_price)" class="w-full accent-amber-500" aria-label="Maximum price">
            </div>
        </div>
        <button @click="runFilter()" class="rounded-lg bg-stone-900 py-2 font-bold text-white sm:col-span-2" :disabled="topLoading">
            <span x-text="topLoading ? 'Searching…' : 'Show top 3 cheapest'"></span>
        </button>
        <ol x-show="top.length" class="space-y-2 sm:col-span-2">
            <template x-for="(r, i) in top" :key="r.venue.id + r.beer + r.size">
                <li class="flex items-center gap-3 rounded-lg bg-amber-50 p-3">
                    <span class="text-2xl font-black text-amber-500" x-text="i + 1"></span>
                    <a :href="r.venue.url" class="flex-1">
                        <span class="font-bold" x-text="r.beer"></span> · <span x-text="r.size"></span><br>
                        <span class="text-sm text-stone-600" x-text="r.venue.name + ' · ' + km(r.distance_m)"></span>
                    </a>
                    <span class="text-lg font-black" x-text="r.price_formatted"></span>
                </li>
            </template>
        </ol>
        <p x-show="!topLoading && top.length === 0" class="text-sm text-stone-500 sm:col-span-2">Set your filters and tap the button.</p>
    </section>

    {{-- Map --}}
    <div x-ref="map" class="h-72 w-full overflow-hidden rounded-xl shadow-sm sm:h-96" role="region" aria-label="Map of nearby venues"></div>

    {{-- Nearest venues list --}}
    <section>
        <h2 class="mb-2 text-lg font-bold">Beer prices nearest to you</h2>
        <p x-show="loading" class="text-stone-500">Finding venues…</p>
        <p x-show="!loading && venues.length === 0" x-cloak class="text-stone-500">No venues in this radius. Try widening it.</p>
        <ul class="divide-y divide-stone-200 rounded-xl bg-white shadow-sm">
            <template x-for="v in venues" :key="v.id">
                <li>
                    <a :href="v.url" class="flex items-center gap-3 p-3 hover:bg-amber-50">
                        <div class="flex-1">
                            <div class="font-semibold" x-text="v.name"></div>
                            <div class="text-sm text-stone-500" x-text="(v.suburb ?? '') + ' · ' + km(v.distance_m)"></div>
                        </div>
                        <div class="text-right" x-show="v.cheapest">
                            <div class="font-black" x-text="v.cheapest?.price_formatted"></div>
                            <div class="text-xs text-stone-500" x-text="v.cheapest ? v.cheapest.beer + ' · ' + v.cheapest.size : ''"></div>
                        </div>
                        <div x-show="!v.cheapest" class="text-xs text-stone-400">No prices yet</div>
                    </a>
                </li>
            </template>
        </ul>
    </section>
</div>
@endsection
