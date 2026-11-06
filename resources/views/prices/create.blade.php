@extends('layouts.app', ['title' => 'Post a price'])

@section('content')
<div class="mx-auto max-w-lg rounded-xl bg-white p-5 shadow-sm"
     x-data="priceForm(@js([
        'fallback' => ['lat' => config('beercheck.geo.default_lat'), 'lng' => config('beercheck.geo.default_lng')],
        'maxWidth' => config('beercheck.images.max_width'),
        'quality' => config('beercheck.images.jpeg_quality') / 100,
     ]))">
    <h1 class="mb-1 text-2xl font-black">Post a price</h1>
    <p class="mb-4 text-sm text-stone-600">
        Earn <b>{{ $points['price_posted'] }}</b> pts for the price, <b>+{{ $points['photo_uploaded'] }}</b> for a photo
        and <b>+{{ $points['review_written'] }}</b> for a written review ({{ $points['review_min_chars'] }}+ characters).
    </p>

    <form method="POST" action="{{ route('prices.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- 1. Photo (camera on mobile) --}}
        <div>
            <span class="mb-1 block text-sm font-semibold">Photo (optional)</span>
            <label class="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-stone-300 p-4 text-center hover:border-amber-500">
                <input type="file" name="photo" accept="image/*" capture="environment" class="sr-only" @change="onPhoto">
                <span x-show="!preview">📷 Take a photo or choose one</span>
                <img x-show="preview" x-cloak :src="preview" alt="Preview" class="max-h-56 rounded">
            </label>
            <p x-show="compressing" x-cloak class="mt-1 text-xs text-stone-500">Compressing…</p>
        </div>

        {{-- 2. Venue --}}
        <div x-data="typeahead({ url: '{{ route('venues.lookup') }}', initialId: '{{ old('venue_id', $venue?->id) }}', initialLabel: @js($venue ? $venue->name.' — '.$venue->suburb : ''), extraParams: () => (lat ? { lat, lng } : {}) })" class="relative">
            <label class="mb-1 block text-sm font-semibold" for="venue-q">Venue</label>
            <input id="venue-q" x-model="query" @input.debounce.300ms="search" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter"
                   autocomplete="off" required placeholder="Start typing a venue or suburb" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <input type="hidden" name="venue_id" :value="selectedId">
            <ul x-show="open && results.length" x-cloak @click.outside="open = false" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white shadow-lg">
                <template x-for="(r, i) in results" :key="r.id">
                    <li @click="choose(r)" :class="i === active ? 'bg-amber-100' : ''" class="cursor-pointer px-3 py-2 text-sm hover:bg-amber-50" x-text="r.label"></li>
                </template>
            </ul>
        </div>

        {{-- 3. Beer + size --}}
        <div x-data="typeahead({ url: '{{ route('beers.lookup') }}', initialId: '{{ old('beer_id') }}' })" class="relative">
            <label class="mb-1 block text-sm font-semibold" for="beer-q">Beer</label>
            <input id="beer-q" x-model="query" @input.debounce.300ms="search" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter"
                   autocomplete="off" required placeholder="e.g. Carlton Draught" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <input type="hidden" name="beer_id" :value="selectedId">
            <ul x-show="open && results.length" x-cloak @click.outside="open = false" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white shadow-lg">
                <template x-for="(r, i) in results" :key="r.id">
                    <li @click="choose(r)" :class="i === active ? 'bg-amber-100' : ''" class="cursor-pointer px-3 py-2 text-sm hover:bg-amber-50" x-text="r.label"></li>
                </template>
            </ul>
            <p class="mt-1 text-xs text-stone-500">Can't find it? <a href="{{ route('beers.create') }}" class="underline">Suggest a beer</a>.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm font-semibold">Size
                <select name="size" required class="mt-1 w-full rounded-lg border-stone-300">
                    @foreach ($sizes as $size)
                        <option value="{{ $size->value }}" @selected(old('size', 'schooner') === $size->value)>{{ $size->label() }}</option>
                    @endforeach
                </select>
            </label>
            {{-- 4. Price --}}
            <label class="block text-sm font-semibold">Price (AUD)
                <input type="number" name="price" step="0.01" min="1" max="200" inputmode="decimal" required value="{{ old('price') }}" class="mt-1 w-full rounded-lg border-stone-300">
            </label>
        </div>

        {{-- 5. Rating + review --}}
        <fieldset x-data="{ rating: {{ (int) old('rating', 0) }} }">
            <legend class="mb-1 text-sm font-semibold">Rating (optional)</legend>
            <div class="flex gap-1 text-3xl">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" @click="rating = rating === {{ $i }} ? 0 : {{ $i }}" :class="rating >= {{ $i }} ? 'text-amber-500' : 'text-stone-300'" aria-label="{{ $i }} stars">★</button>
                @endfor
            </div>
            <input type="hidden" name="rating" :value="rating || ''">
        </fieldset>
        <label class="block text-sm font-semibold">Review / caption (optional)
            <textarea name="review_text" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border-stone-300">{{ old('review_text') }}</textarea>
        </label>

        <button class="w-full rounded-lg bg-amber-500 py-3 text-lg font-bold text-white" :disabled="compressing">Post price</button>
    </form>
</div>
@endsection
