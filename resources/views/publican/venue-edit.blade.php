@extends('layouts.app', ['title' => 'Manage '.$venue->name])

@section('content')
<h1 class="mb-3 text-2xl font-black">{{ $venue->name }}</h1>
<div class="grid gap-4 md:grid-cols-2">
    <form method="POST" action="{{ route('publican.venues.update', $venue) }}" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        @csrf @method('PUT')
        <h2 class="font-bold">Venue details</h2>
        <x-input label="Phone" name="phone" type="tel" :value="$venue->phone" />
        <x-input label="Website" name="website" type="url" :value="$venue->website" placeholder="https://" />
        <label class="block text-sm font-semibold">Description
            <textarea name="description" rows="4" maxlength="2000" class="mt-1 w-full rounded-lg border-stone-300">{{ old('description', $venue->description) }}</textarea>
        </label>
        <fieldset class="grid grid-cols-[3rem_1fr] items-center gap-2">
            <legend class="mb-1 text-sm font-semibold">Opening hours</legend>
            @foreach ($days as $day)
                <label for="h-{{ $day }}" class="text-sm capitalize">{{ $day }}</label>
                <input id="h-{{ $day }}" name="opening_hours[{{ $day }}]" value="{{ old('opening_hours.'.$day, $venue->opening_hours[$day] ?? '') }}" placeholder="11am – late" class="rounded-lg border-stone-300 text-sm">
            @endforeach
        </fieldset>
        <button class="w-full rounded-lg bg-stone-900 py-2 font-bold text-white">Save details</button>
    </form>

    <form method="POST" action="{{ route('publican.prices.store', $venue) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        @csrf
        <h2 class="font-bold">Publish an official price</h2>
        <p class="text-xs text-stone-500">Shown with a “✓ Venue listed” badge and preferred over punter prices.</p>
        <div x-data="typeahead({ url: '{{ route('beers.lookup') }}' })" class="relative">
            <input x-model="query" @input.debounce.300ms="search" autocomplete="off" required placeholder="Beer" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <input type="hidden" name="beer_id" :value="selectedId">
            <ul x-show="open && results.length" x-cloak @click.outside="open = false" class="absolute z-10 mt-1 w-full rounded-lg bg-white shadow-lg">
                <template x-for="r in results" :key="r.id"><li @click="choose(r)" class="cursor-pointer px-3 py-2 text-sm hover:bg-amber-50" x-text="r.label"></li></template>
            </ul>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <select name="size" class="rounded-lg border-stone-300">@foreach ($sizes as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach</select>
            <input type="number" name="price" step="0.01" min="1" max="200" required placeholder="Price" class="rounded-lg border-stone-300">
        </div>
        <input type="file" name="photo" accept="image/*" class="text-sm">
        <button class="w-full rounded-lg bg-emerald-600 py-2 font-bold text-white">Publish price</button>
    </form>
</div>
@endsection
