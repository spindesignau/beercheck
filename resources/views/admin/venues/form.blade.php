@extends('layouts.app', ['title' => ($venue->exists ? 'Edit ' : 'New ').'venue · Admin'])

@section('content')
@include('admin._nav')
<form method="POST" action="{{ $venue->exists ? route('admin.venues.update', $venue) : route('admin.venues.store') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2">
    @csrf
    @if ($venue->exists) @method('PUT') @endif
    <h1 class="text-xl font-black sm:col-span-2">{{ $venue->exists ? $venue->name : 'New venue' }}
        @if ($venue->exists)<span class="text-sm font-normal text-stone-500">· {{ $venue->source->value }} {{ $venue->external_id }}</span>@endif
    </h1>
    <x-input label="Name" name="name" :value="$venue->name" required />
    <x-input label="Address" name="address" :value="$venue->address" />
    <x-input label="Suburb" name="suburb" :value="$venue->suburb" required />
    <label class="block text-sm font-semibold">State
        <select name="state" class="mt-1 w-full rounded-lg border-stone-300">
            @foreach (['NSW','VIC','QLD','SA','WA','TAS','ACT','NT'] as $s)<option @selected(old('state', $venue->state) === $s)>{{ $s }}</option>@endforeach
        </select>
    </label>
    <x-input label="Postcode" name="postcode" :value="$venue->postcode" required pattern="\d{4}" />
    <x-input label="Licence type" name="licence_type" :value="$venue->licence_type" />
    <x-input label="Latitude" name="latitude" type="number" step="0.0000001" :value="$venue->latitude" required />
    <x-input label="Longitude" name="longitude" type="number" step="0.0000001" :value="$venue->longitude" required />
    <x-input label="Phone" name="phone" :value="$venue->phone" />
    <x-input label="Website" name="website" type="url" :value="$venue->website" />
    <label class="block text-sm font-semibold sm:col-span-2">Description
        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-stone-300">{{ old('description', $venue->description) }}</textarea>
    </label>
    <button class="rounded-lg bg-stone-900 py-2 font-bold text-white sm:col-span-2">Save</button>
</form>
@endsection
