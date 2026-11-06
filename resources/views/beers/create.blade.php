@extends('layouts.app', ['title' => 'Suggest a beer'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-1 text-2xl font-black">Suggest a missing beer</h1>
    <p class="mb-4 text-sm text-stone-600">An admin will review it. You'll earn {{ config('beercheck.points.beer_approved') }} points if it's approved.</p>
    <form method="POST" action="{{ route('beers.store') }}" class="space-y-3">
        @csrf
        <x-input label="Brand / brewery" name="brand" required placeholder="e.g. Carlton" />
        <x-input label="Beer name" name="name" required placeholder="e.g. Draught" />
        <label class="block text-sm font-semibold">Type
            <select name="type_id" required class="mt-1 w-full rounded-lg border-stone-300">
                @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('type_id') == $type->id)>{{ $type->name }}</option>@endforeach
            </select>
        </label>
        <x-input label="ABV % (optional)" name="abv" type="number" step="0.1" min="0" max="20" />
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Submit for review</button>
    </form>
</div>
@endsection
