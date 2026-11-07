@extends('layouts.app', ['title' => 'Manage '.$venue->name])

@section('content')
<section class="bg-bubbles px-4 pb-5 pt-5 text-white md:rounded-b-[2rem]">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-beer">Manage venue</p>
    <h1 class="text-[1.9rem] leading-tight">{{ $venue->name }}</h1>
</section>
<div class="foam-edge md:hidden" aria-hidden="true"></div>

<div class="grid grid-cols-1 gap-4 px-4 pt-3 md:grid-cols-2">
    <form method="POST" action="{{ route('publican.prices.store', $venue) }}" enctype="multipart/form-data" class="card space-y-4 p-5 md:order-2">
        @csrf
        <h2 class="text-lg">Publish an official price</h2>
        <p class="-mt-2 text-xs text-ink/60">Shown with a “Venue price” badge and preferred over punter prices.</p>
        <x-typeahead-field label="Beer" name="beer_id" :url="route('beers.lookup')" icon="beer" placeholder="e.g. Carlton Draught" />
        <div class="grid grid-cols-2 gap-3">
            <label><span class="label">Glass</span>
                <select name="size" class="field">@foreach ($sizes as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach</select>
            </label>
            <label><span class="label">Price ($)</span>
                <input type="number" name="price" step="0.01" min="1" max="200" required inputmode="decimal" class="field">
            </label>
        </div>
        <label class="block"><span class="label">Photo (optional)</span><input type="file" name="photo" accept="image/*" class="text-sm"></label>
        <button class="btn-beer w-full">Publish price</button>
        <p class="text-xs text-ink/60">Got a whole menu? Send it to us as a spreadsheet and we'll import it.</p>
    </form>

    <form method="POST" action="{{ route('publican.venues.update', $venue) }}" class="card space-y-4 p-5">
        @csrf @method('PUT')
        <h2 class="text-lg">Venue details</h2>
        <x-input label="Phone" name="phone" type="tel" :value="$venue->phone" />
        <x-input label="Website" name="website" type="url" :value="$venue->website" placeholder="https://" />
        <label class="block"><span class="label">Description</span>
            <textarea name="description" rows="4" maxlength="2000" class="field">{{ old('description', $venue->description) }}</textarea>
        </label>
        <fieldset>
            <legend class="label">Opening hours</legend>
            <div class="grid grid-cols-[3rem_1fr] items-center gap-2">
                @foreach ($days as $day)
                    <label for="h-{{ $day }}" class="text-sm font-bold capitalize">{{ $day }}</label>
                    <input id="h-{{ $day }}" name="opening_hours[{{ $day }}]" value="{{ old('opening_hours.'.$day, $venue->opening_hours[$day] ?? '') }}" placeholder="11am – late" class="field !py-2 text-sm">
                @endforeach
            </div>
        </fieldset>
        <button class="btn-ink w-full">Save details</button>
    </form>
</div>
@endsection
