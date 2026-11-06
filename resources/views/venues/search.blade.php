@extends('layouts.app', ['title' => 'Venues matching "'.$term.'"'])

@section('content')
<h1 class="mb-3 text-xl font-bold">Venues matching “{{ $term }}”</h1>
@forelse ($venues as $venue)
    <a href="{{ route('venues.show', $venue) }}" class="mb-2 block rounded-xl bg-white p-3 shadow-sm hover:bg-amber-50">
        <div class="font-semibold">{{ $venue->name }}</div>
        <div class="text-sm text-stone-500">{{ $venue->fullAddress() }}</div>
    </a>
@empty
    <p class="text-stone-600">No venues found. Try a suburb or a 4-digit postcode.</p>
@endforelse
@endsection
