@extends('layouts.app', ['title' => 'My venues'])

@section('content')
<section class="bg-bubbles px-4 pb-5 pt-5 text-white md:rounded-b-[2rem]">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-beer">Publican</p>
    <h1 class="text-[1.9rem] leading-tight">My venues</h1>
</section>
<div class="foam-edge md:hidden" aria-hidden="true"></div>

<div class="space-y-5 px-4 pt-3">
    <div class="grid gap-3 sm:grid-cols-2">
        @forelse ($venues as $v)
            <div class="card p-4">
                <div class="font-bold">{{ $v->name }}</div>
                <div class="text-xs text-ink/60">{{ $v->fullAddress() }}</div>
                <div class="mt-3 flex gap-2">
                    <a class="btn-ghost !px-4 !py-2 text-sm" href="{{ route('venues.show', $v) }}">View</a>
                    <a class="btn-beer !px-4 !py-2 text-sm" href="{{ route('publican.venues.edit', $v) }}">Prices &amp; details</a>
                </div>
            </div>
        @empty
            <div class="card p-5 text-sm">No approved venues yet. <a class="font-bold underline" href="{{ route('publican.claim.create') }}">Claim a venue</a>.</div>
        @endforelse
    </div>

    <section>
        <h2 class="mb-2 text-lg">Customer reviews</h2>
        <div class="space-y-3">
        @forelse ($reviews as $r)
            <div class="card p-4">
                <div class="text-xs text-ink/60">{{ $r->venue->name }} · {{ '@'.$r->user->username }} · {{ $r->created_at->diffForHumans() }}</div>
                <div class="mt-0.5 font-bold">{{ $r->beer->displayName() }}</div>
                @if ($r->rating)<x-stars :rating="$r->rating" />@endif
                @if ($r->review_text)<p class="mt-1 text-[15px]">{{ $r->review_text }}</p>@endif
                <form method="POST" action="{{ route('publican.replies.upsert', $r) }}" class="mt-3 space-y-2">
                    @csrf @method('PUT')
                    <textarea name="reply_text" rows="2" required class="field text-sm" placeholder="Write an official reply">{{ $r->reply?->reply_text }}</textarea>
                    <button class="btn-ink !py-2 text-sm">{{ $r->reply ? 'Update reply' : 'Reply' }}</button>
                </form>
            </div>
        @empty
            <div class="card p-5 text-sm text-ink/60">No reviews yet.</div>
        @endforelse
        </div>
        <div class="mt-3">{{ $reviews->links() }}</div>
    </section>
</div>
@endsection
