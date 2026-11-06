@extends('layouts.app', ['title' => $term ? 'Cheapest '.$term : 'Top cheapest'])

@section('content')
<h1 class="mb-1 text-xl font-bold">{{ $term ? 'Cheapest “'.$term.'” near you' : 'Top '.config('beercheck.prices.top_n').' cheapest near you' }}</h1>
<p class="mb-3 text-sm text-stone-500">Within {{ $filters->radiusKm }} km. Ordered by price, then distance.</p>

@forelse ($results as $i => $r)
    <a href="{{ $r['venue']['url'] }}" class="mb-2 flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm hover:bg-amber-50">
        <span class="w-6 text-center text-xl font-black text-amber-500">{{ $i + 1 }}</span>
        <div class="flex-1">
            <div class="font-semibold">{{ $r['beer'] }} · {{ $r['size_label'] }}</div>
            <div class="text-sm text-stone-500">
                {{ $r['venue']['name'] }}, {{ $r['venue']['suburb'] }} ·
                {{ $r['distance_m'] < 1000 ? $r['distance_m'].' m' : number_format($r['distance_m'] / 1000, 1).' km' }}
                @if ($r['is_venue_listed']) · <span class="font-semibold text-emerald-700">✓ venue listed</span>@endif
            </div>
        </div>
        <span class="text-lg font-black">{{ $r['price_formatted'] }}</span>
    </a>
@empty
    <p class="rounded-xl bg-white p-4 text-stone-600 shadow-sm">No matching prices nearby. Widen the radius or <a class="underline" href="{{ route('prices.create') }}">post one</a>.</p>
@endforelse
@endsection
