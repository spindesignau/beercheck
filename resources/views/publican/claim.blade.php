@extends('layouts.app', ['title' => 'Claim a venue'])

@section('content')
<div class="mx-auto max-w-lg">
    <section class="bg-bubbles px-4 pb-5 pt-5 text-white md:mt-4 md:rounded-[2rem]">
        <h1 class="text-[1.9rem] leading-tight">Own a pub?</h1>
        <p class="text-sm text-white/75">Claim it to post official prices, update your hours and reply to reviews.</p>
    </section>
    <div class="foam-edge md:hidden" aria-hidden="true"></div>
    <form method="POST" action="{{ route('publican.claim.store') }}" class="card mx-4 mt-3 space-y-4 p-5">
        @csrf
        <x-typeahead-field label="Your venue" name="venue_id" :url="route('venues.lookup')" icon="pin" placeholder="Venue name or suburb"
            :initial-id="old('venue_id', $venue?->id)" :initial-label="$venue ? $venue->name.' — '.$venue->suburb : ''" />
        <x-input label="Business name" name="business_name" required />
        <x-input label="Business email" name="business_email" type="email" required hint="We'll send a confirmation link here." />
        <x-input label="ABN (optional)" name="abn" inputmode="numeric" pattern="\d{11}" hint="Speeds up verification." />
        <button class="btn-beer w-full">Submit claim</button>
        <ol class="list-inside list-decimal space-y-1 text-xs text-ink/60">
            <li>Confirm the link we email to your business address.</li>
            <li>Our team verifies the claim (usually within a day).</li>
            <li>You'll get publican tools on your profile.</li>
        </ol>
    </form>
</div>
@endsection
