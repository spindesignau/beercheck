@extends('layouts.app', ['title' => 'Post a price'])

@section('content')
<form method="POST" action="{{ route('prices.store') }}" enctype="multipart/form-data"
      x-data="priceForm(@js([
        'fallback' => ['lat' => config('beercheck.geo.default_lat'), 'lng' => config('beercheck.geo.default_lng')],
        'maxWidth' => config('beercheck.images.max_width'),
        'quality' => config('beercheck.images.jpeg_quality') / 100,
        'points' => $points,
        'review' => old('review_text', ''),
      ]))" class="mx-auto max-w-lg">
    @csrf

    <section class="bg-beer-bubbles px-4 pb-4 pt-5 md:mt-4 md:rounded-[2rem]">
        <h1 class="text-[1.9rem] leading-tight">Post a price</h1>
        <p class="text-sm font-semibold text-ink/70">Share what you paid. Earn points. Be a legend.</p>
    </section>
    <div class="foam-edge-beer md:hidden" aria-hidden="true"></div>

    <div class="space-y-5 px-4 pt-3">
        {{-- 1. Photo --}}
        <div>
            <span class="label">1 · Snap it <span class="font-medium text-ink/50">(optional, +{{ $points['photo_uploaded'] }} pts)</span></span>
            <label class="card relative flex aspect-[4/3] cursor-pointer flex-col items-center justify-center overflow-hidden border-2 border-dashed border-gold/40 text-center">
                <input type="file" name="photo" accept="image/*" capture="environment" class="sr-only" @change="onPhoto">
                <template x-if="!preview">
                    <span class="flex flex-col items-center gap-2 p-6">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-beer shadow-[0_3px_0_var(--color-gold)]"><x-icon name="camera" class="h-8 w-8" /></span>
                        <span class="font-bold">Take a photo</span>
                        <span class="text-xs text-ink/50">of your beer, the tap or the menu board</span>
                    </span>
                </template>
                <img x-show="preview" x-cloak :src="preview" alt="Photo preview" class="absolute inset-0 h-full w-full object-cover">
                <span x-show="compressing" x-cloak class="absolute inset-x-0 bottom-0 bg-ink/70 py-1 text-xs font-bold text-white">Shrinking photo…</span>
            </label>
        </div>

        {{-- 2. Where --}}
        <x-typeahead-field label="2 · Where are you?" name="venue_id" :url="route('venues.lookup')" icon="pin" :with-location="true"
            placeholder="Pub name or suburb" :initial-id="old('venue_id', $venue?->id)" :initial-label="$venue ? $venue->name.' — '.$venue->suburb : ''" />

        {{-- 3. What --}}
        <x-typeahead-field label="3 · What are you drinking?" name="beer_id" :url="route('beers.lookup')" icon="beer"
            placeholder="e.g. Carlton Draught" :initial-id="old('beer_id')">
            <p class="mt-1 text-xs text-ink/60">Not listed? <a href="{{ route('beers.create') }}" class="font-bold underline">Suggest it</a> (+{{ $points['beer_approved'] }} pts if approved).</p>
        </x-typeahead-field>

        <fieldset>
            <legend class="label">Glass</legend>
            <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                @foreach ($sizes as $size)
                    <label class="relative shrink-0">
                        <input type="radio" name="size" value="{{ $size->value }}" class="peer sr-only" @checked(old('size', 'schooner') === $size->value) required>
                        <span class="chip flex-col !items-start !gap-0 !rounded-2xl !px-4 !py-2 peer-checked:bg-ink peer-checked:text-white">
                            <span>{{ ucfirst($size->value) }}</span><span class="text-[10px] opacity-60">{{ $size->millilitres() }}ml</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- 4. How much --}}
        <label class="block">
            <span class="label">4 · How much? <span class="font-medium text-ink/50">(+{{ $points['price_posted'] }} pts)</span></span>
            <span class="field flex items-center gap-1 !py-2">
                <span class="font-display text-3xl text-gold">$</span>
                <input type="number" name="price" step="0.01" min="1" max="200" inputmode="decimal" required value="{{ old('price') }}" placeholder="0.00"
                       class="min-w-0 flex-1 bg-transparent font-display !text-3xl outline-none placeholder:text-ink/20">
            </span>
        </label>

        {{-- 5. Rate & review --}}
        <fieldset x-data="{ rating: {{ (int) old('rating', 0) }} }">
            <legend class="label">5 · Rate it <span class="font-medium text-ink/50">(optional)</span></legend>
            <div class="flex gap-1">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" @click="rating = rating === {{ $i }} ? 0 : {{ $i }}" class="transition active:scale-90" aria-label="{{ $i }} stars">
                        <svg class="h-10 w-10" :class="rating >= {{ $i }} ? 'text-beer' : 'text-ink/15'" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                    </button>
                @endfor
            </div>
            <input type="hidden" name="rating" :value="rating || ''">
        </fieldset>
        <label class="block">
            <span class="label">Review or caption <span class="font-medium text-ink/50">({{ $points['review_min_chars'] }}+ characters = +{{ $points['review_written'] }} pts)</span></span>
            <textarea name="review_text" rows="3" maxlength="2000" x-model="review" placeholder="Cold? Flat? Great beer garden?" class="field">{{ old('review_text') }}</textarea>
        </label>

        <div class="sticky bottom-24 z-10 md:bottom-4">
            <button class="btn-beer w-full !py-4 text-lg shadow-xl" :disabled="compressing">
                Post it · <span x-text="'+' + earn() + ' pts'"></span>
            </button>
        </div>
    </div>
</form>
@endsection
