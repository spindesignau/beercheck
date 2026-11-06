@extends('layouts.app', ['title' => 'Claim a venue'])

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-1 text-2xl font-black">Claim your venue</h1>
    <p class="mb-4 text-sm text-stone-600">We'll email your business address to confirm, then our team verifies the claim. Approved publicans can post official prices, update venue details and reply to reviews.</p>
    <form method="POST" action="{{ route('publican.claim.store') }}" class="space-y-3">
        @csrf
        <div x-data="typeahead({ url: '{{ route('venues.lookup') }}', initialId: '{{ old('venue_id', $venue?->id) }}', initialLabel: @js($venue ? $venue->name.' — '.$venue->suburb : '') })" class="relative">
            <label class="mb-1 block text-sm font-semibold" for="claim-venue">Venue</label>
            <input id="claim-venue" x-model="query" @input.debounce.300ms="search" autocomplete="off" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <input type="hidden" name="venue_id" :value="selectedId">
            <ul x-show="open && results.length" x-cloak @click.outside="open = false" class="absolute z-10 mt-1 w-full rounded-lg bg-white shadow-lg">
                <template x-for="r in results" :key="r.id"><li @click="choose(r)" class="cursor-pointer px-3 py-2 text-sm hover:bg-amber-50" x-text="r.label"></li></template>
            </ul>
        </div>
        <x-input label="Business name" name="business_name" required />
        <x-input label="Business email" name="business_email" type="email" required />
        <x-input label="ABN (optional)" name="abn" inputmode="numeric" pattern="\d{11}" />
        <button class="w-full rounded-lg bg-amber-500 py-2 font-bold text-white">Submit claim</button>
    </form>
</div>
@endsection
