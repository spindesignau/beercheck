@extends('layouts.app', ['title' => 'Publican dashboard'])

@section('content')
<h1 class="mb-3 text-2xl font-black">Your venues</h1>
<div class="mb-6 grid gap-3 sm:grid-cols-2">
    @forelse ($venues as $v)
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="font-bold">{{ $v->name }}</div>
            <div class="text-sm text-stone-500">{{ $v->fullAddress() }}</div>
            <div class="mt-2 flex gap-3 text-sm">
                <a class="underline" href="{{ route('venues.show', $v) }}">View</a>
                <a class="underline" href="{{ route('publican.venues.edit', $v) }}">Edit details & prices</a>
            </div>
        </div>
    @empty
        <p class="text-stone-600">No approved venues yet. <a class="underline" href="{{ route('publican.claim.create') }}">Claim a venue</a>.</p>
    @endforelse
</div>

<h2 class="mb-2 text-lg font-bold">Customer reviews</h2>
@forelse ($reviews as $r)
    <div class="mb-3 rounded-xl bg-white p-4 shadow-sm">
        <div class="text-sm text-stone-500">{{ $r->venue->name }} · {{ $r->user->username }} · {{ $r->created_at->diffForHumans() }}</div>
        <div class="font-semibold">{{ $r->beer->displayName() }} @if ($r->rating)<x-stars :rating="$r->rating" />@endif</div>
        @if ($r->review_text)<p class="mt-1">{{ $r->review_text }}</p>@endif
        <form method="POST" action="{{ route('publican.replies.upsert', $r) }}" class="mt-2 space-y-2">
            @csrf @method('PUT')
            <textarea name="reply_text" rows="2" required class="w-full rounded-lg border-stone-300 text-sm" placeholder="Write an official reply">{{ $r->reply?->reply_text }}</textarea>
            <button class="rounded-lg bg-emerald-600 px-3 py-1 text-sm font-semibold text-white">{{ $r->reply ? 'Update reply' : 'Reply' }}</button>
        </form>
    </div>
@empty
    <p class="text-stone-600">No reviews yet.</p>
@endforelse
{{ $reviews->links() }}
@endsection
