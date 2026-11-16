@extends('layouts.app')

@section('content')
<div x-data="geoDiscovery(@js([
        'fallback' => ['lat' => $geo['default_lat'], 'lng' => $geo['default_lng']],
        'radius' => $geo['default_radius_km'],
        'nearbyUrl' => route('venues.nearby'),
        'cheapestUrl' => route('beers.cheapest'),
        'placesUrl' => route('places.lookup'),
    ]))">

    {{-- Hero --}}
    <section class="bg-bubbles px-4 pb-6 pt-5 text-white md:rounded-b-[2rem]">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-beer">Beer prices at your fingertips</p>
        <h1 class="mt-1 text-[2rem] leading-[1.05]">Cheapest beer<br class="sm:hidden"> near you</h1>

        <form action="{{ route('search') }}" method="GET" class="mt-4">
            <label class="flex items-center gap-2 rounded-full bg-white px-4 py-3 text-ink shadow-lg">
                <x-icon name="search" class="h-5 w-5 text-ink/40" />
                <input name="q" placeholder="Search a pub, suburb or beer…" class="min-w-0 flex-1 bg-transparent font-semibold outline-none placeholder:font-medium placeholder:text-ink/40" autocomplete="off" enterkeyhint="search">
            </label>
        </form>

        <div class="mt-4 flex items-center gap-3 text-sm">
            <button type="button" @click="pickerOpen = !pickerOpen" class="flex min-w-0 items-center gap-1 rounded-full bg-white/10 py-1 pl-2 pr-3 font-semibold ring-1 ring-white/15" :aria-expanded="pickerOpen">
                <x-icon name="pin" class="h-4 w-4 shrink-0 text-beer" ::class="locating && 'animate-pulse'" />
                <span class="truncate" x-text="locationLabel()">Finding you…</span>
                <x-icon name="chevron" class="h-3.5 w-3.5 shrink-0 rotate-90 opacity-60" />
            </button>
            <input type="range" min="{{ $geo['min_radius_km'] }}" max="{{ $geo['max_radius_km'] }}" step="1" x-model.number="radius"
                   class="min-w-0 flex-1 accent-[#F7BB0E]" aria-label="Search radius in kilometres">
            <span class="w-12 shrink-0 text-right font-display text-beer" x-text="radius + ' km'"></span>
        </div>

        {{-- Location picker --}}
        <div x-show="pickerOpen" x-cloak x-transition.opacity class="mt-3 rounded-3xl bg-white p-3 text-ink shadow-xl">
            <button type="button" @click="locate(true)" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left font-bold hover:bg-beer-light/50">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-sky-600"><x-icon name="pin" class="h-5 w-5" /></span>
                Use my current location
            </button>
            <label class="mt-1 flex items-center gap-2 rounded-2xl bg-foam px-3 py-2.5 ring-1 ring-ink/10">
                <x-icon name="search" class="h-4 w-4 text-ink/40" />
                <input x-model="placeQuery" @input.debounce.250ms="searchPlaces" placeholder="Or type a suburb or postcode" autocomplete="off"
                       class="min-w-0 flex-1 bg-transparent font-semibold outline-none placeholder:font-medium placeholder:text-ink/40">
            </label>
            <ul x-show="places.length" class="mt-1 max-h-60 overflow-auto">
                <template x-for="p in places" :key="p.label">
                    <li><button type="button" @click="pickPlace(p)" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-semibold hover:bg-beer-light/50">
                        <span x-text="p.label"></span><span class="text-xs text-ink/40" x-text="p.venues + ' venues'"></span>
                    </button></li>
                </template>
            </ul>
            <p x-show="placeQuery.trim().length >= 2 && !places.length" class="px-3 py-2 text-xs text-ink/50">No matching suburb yet. We cover NSW and VIC venues.</p>
        </div>
    </section>
    <div class="foam-edge md:hidden" aria-hidden="true"></div>

    {{-- Why we couldn't find you (instead of silently showing Melbourne) --}}
    <section x-show="!locating && error && source !== 'manual'" x-cloak class="mx-4 mt-2 rounded-3xl bg-white p-4 text-sm shadow-sm ring-1 ring-beer/60" role="status">
        <p class="font-bold">
            <span x-show="error === 'denied'">📍 Location is blocked for Beercheck</span>
            <span x-show="error === 'timeout' || error === 'unavailable'">📍 We couldn't get a fix on your location</span>
            <span x-show="error === 'unsupported' || error === 'insecure'">📍 This browser can't share your location</span>
        </p>
        <p class="mt-1 text-ink/70">
            Showing <b x-text="source === 'saved' ? 'your last known location' : 'Melbourne CBD'"></b> for now.
        </p>
        <div x-show="error === 'denied'" class="mt-2 text-ink/80">
            <template x-if="platform() === 'ios'">
                <ol class="list-inside list-decimal space-y-0.5">
                    <li>Settings → Privacy &amp; Security → <b>Location Services</b> on → <b>Safari Websites</b> → “While Using the App”.</li>
                    <li>In Safari tap <b>aA</b> (or the ⋯ menu) → Website Settings → <b>Location</b> → Allow.</li>
                </ol>
            </template>
            <template x-if="platform() === 'android'">
                <ol class="list-inside list-decimal space-y-0.5">
                    <li>Make sure phone Location is on (swipe down → Location).</li>
                    <li>In Chrome tap the icon left of the address → <b>Permissions</b> → Location → Allow.</li>
                </ol>
            </template>
            <template x-if="platform() === 'desktop'">
                <p>Click the icon left of the address bar → Site settings → Location → Allow, and check your computer's location services are on.</p>
            </template>
        </div>
        <p x-show="error === 'timeout' || error === 'unavailable'" class="mt-2 text-ink/80">Check Location Services is switched on, step outside or near a window, then try again.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" @click="locate(true)" class="btn-ink !px-4 !py-2 text-sm">Try again</button>
            <button type="button" @click="pickerOpen = true; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-ghost !px-4 !py-2 text-sm">Pick a suburb</button>
        </div>
    </section>

    {{-- Quick size filter --}}
    <div class="no-scrollbar -mt-1 flex gap-2 overflow-x-auto px-4 py-3" role="group" aria-label="Glass size">
        <button @click="setSize('')" :class="size === '' && 'chip-on'" class="chip">🍺 Any</button>
        @foreach ($sizes as $s)
            <button @click="setSize('{{ $s->value }}')" :class="size === '{{ $s->value }}' && 'chip-on'" class="chip">{{ ucfirst($s->value) }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 px-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        {{-- Map (always visible on desktop, toggled on mobile) --}}
        <div :class="view === 'map' ? 'block' : 'hidden'" class="md:sticky md:top-20 md:block md:self-start">
            <div x-ref="map" class="card h-[calc(100dvh-13.5rem)] min-h-80 w-full scroll-mt-16 overflow-hidden md:h-[calc(100vh-7rem)]" role="region" aria-label="Map of nearby venues"></div>
        </div>

        <div :class="view === 'list' ? 'block' : 'hidden'" class="space-y-4 md:block">
            {{-- Top 3 --}}
            <section class="card overflow-hidden" x-show="top.length" x-cloak>
                <div class="bg-beer-bubbles flex items-center justify-between px-4 py-3">
                    <h2 class="flex items-center gap-2 text-lg"><x-icon name="trophy" class="h-5 w-5" /> Top 3 cheapest</h2>
                    <a :href="cheapestUrl()" class="text-xs font-bold underline">More filters</a>
                </div>
                <ol class="divide-y divide-ink/5">
                    <template x-for="(r, i) in top" :key="r.venue.id + r.beer + r.size">
                        <li>
                            <a :href="r.venue.url" class="flex items-center gap-3 px-4 py-3 active:bg-beer-light/40">
                                <span class="text-2xl" x-text="['🥇','🥈','🥉'][i]"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold" x-text="r.beer"></span>
                                    <span class="block truncate text-xs text-ink/60" x-text="r.size + ' · ' + r.venue.name + ' · ' + km(r.distance_m)"></span>
                                </span>
                                <span class="price-pill" x-text="r.price_formatted"></span>
                            </a>
                        </li>
                    </template>
                </ol>
            </section>

            {{-- Nearest venues --}}
            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-xl">Nearby pubs</h2>
                    <span class="text-xs font-semibold text-ink/50" x-text="loading ? 'Finding pubs…' : venues.length + (hasMore ? '+' : '') + ' within ' + radius + ' km'"></span>
                </div>

                <template x-if="loading && venues.length === 0">
                    <div class="space-y-3">
                        <template x-for="n in 4"><div class="card h-20 animate-pulse"></div></template>
                    </div>
                </template>

                <div x-show="!loading && venues.length === 0" x-cloak class="card p-6 text-center">
                    <x-logo variant="pin" class="mx-auto h-16 w-auto animate-bob" />
                    <p class="mt-3 font-bold">No pubs in this radius yet.</p>
                    <p class="text-sm text-ink/60">Slide the radius wider, or add a price for your local.</p>
                </div>

                <ul class="space-y-3">
                    <template x-for="v in venues" :key="v.id">
                        <li>
                            <a :href="v.url" class="card flex items-center gap-3 p-4 transition active:scale-[.99]">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-foam text-gold ring-1 ring-ink/5">
                                    <x-icon name="beer" class="h-6 w-6" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold" x-text="v.name"></span>
                                    <span class="block truncate text-xs text-ink/60">
                                        <span x-text="km(v.distance_m)"></span>
                                        <span x-text="v.suburb ? ' · ' + v.suburb : ''"></span>
                                        <span x-show="v.cheapest" x-text="v.cheapest ? ' · ' + v.cheapest.beer + ' ' + v.cheapest.size : ''"></span>
                                    </span>
                                </span>
                                <span x-show="v.cheapest" class="price-pill" x-text="v.cheapest?.price_formatted"></span>
                                <span x-show="!v.cheapest" class="text-xs font-semibold text-ink/40">No price yet</span>
                            </a>
                        </li>
                    </template>
                </ul>
                <div x-show="hasMore" x-cloak class="mt-4 flex justify-center">
                    <button type="button" @click="loadMore()" :disabled="loadingMore" class="btn-ghost">
                        <span x-text="loadingMore ? 'Loading…' : 'Show more pubs'"></span>
                    </button>
                </div>
            </section>
        </div>
    </div>

    {{-- Floating List/Map toggle (mobile) --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-[5.75rem] z-[900] flex justify-center md:hidden">
        <div class="pointer-events-auto flex rounded-full bg-ink p-1 text-sm font-bold text-white shadow-xl">
            <button @click="show('list')" :class="view === 'list' && 'bg-beer text-ink'" class="flex items-center gap-1.5 rounded-full px-4 py-2"><x-icon name="list" class="h-4 w-4" /> List</button>
            <button @click="show('map')" :class="view === 'map' && 'bg-beer text-ink'" class="flex items-center gap-1.5 rounded-full px-4 py-2"><x-icon name="map" class="h-4 w-4" /> Map</button>
        </div>
    </div>
</div>
@endsection
