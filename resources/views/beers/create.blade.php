@extends('layouts.app', ['title' => 'Suggest a beer'])

@section('content')
<div class="mx-auto max-w-lg">
    <section class="bg-beer-bubbles px-4 pb-4 pt-5 md:mt-4 md:rounded-[2rem]">
        <h1 class="text-[1.9rem] leading-tight">Suggest a beer</h1>
        <p class="text-sm font-semibold text-ink/70">Missing from the list? Add it. +{{ config('beercheck.points.beer_approved') }} pts when approved.</p>
    </section>
    <div class="foam-edge-beer md:hidden" aria-hidden="true"></div>
    <form method="POST" action="{{ route('beers.store') }}" class="card mx-4 mt-3 space-y-4 p-5">
        @csrf
        <x-input label="Brand / brewery" name="brand" required placeholder="e.g. Mountain Goat" />
        <x-input label="Beer name" name="name" required placeholder="e.g. Steam Ale" />
        <label class="block"><span class="label">Style</span>
            <select name="type_id" required class="field">
                @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('type_id') == $type->id)>{{ $type->name }}</option>@endforeach
            </select>
        </label>
        <x-input label="ABV % (optional)" name="abv" type="number" step="0.1" min="0" max="20" inputmode="decimal" />
        <button class="btn-beer w-full">Send for review</button>
    </form>
</div>
@endsection
