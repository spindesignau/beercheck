@extends('layouts.app', ['title' => 'Posts · Admin'])

@section('content')
@include('admin._nav')
<div class="mb-3 flex gap-2 text-sm">
    @foreach (['recent' => 'Recent', 'flagged' => 'Hidden', 'photos' => 'With photos', 'deleted' => 'Deleted'] as $key => $label)
        <a href="{{ route('admin.prices.index', ['view' => $key]) }}" class="rounded-full px-3 py-1 {{ $view === $key ? 'bg-amber-500 font-bold text-white' : 'bg-white' }}">{{ $label }}</a>
    @endforeach
</div>
@forelse ($prices as $p)
    <div class="mb-3 flex gap-3 rounded-xl bg-white p-3 shadow-sm">
        @if ($p->photoUrl())<img src="{{ $p->photoUrl() }}" alt="" class="h-24 w-24 rounded object-cover" loading="lazy">@endif
        <div class="flex-1 text-sm">
            <div class="font-semibold">{{ $p->beer->displayName() }} · {{ $p->size->value }} · ${{ number_format($p->price, 2) }}
                @if ($p->is_venue_listed)<span class="text-emerald-700">(official)</span>@endif
                @if ($p->is_flagged)<span class="text-red-600">(hidden{{ $p->flag_reason ? ': '.$p->flag_reason : '' }})</span>@endif
            </div>
            <div class="text-stone-500">{{ $p->venue->name }}, {{ $p->venue->suburb }} · {{ $p->user->username }} · {{ $p->created_at->diffForHumans() }}</div>
            @if ($p->rating)<x-stars :rating="$p->rating" />@endif
            @if ($p->review_text)<p class="mt-1">{{ $p->review_text }}</p>@endif
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($p->trashed())
                    <form method="POST" action="{{ route('admin.prices.restore', $p) }}">@csrf<button class="rounded bg-stone-700 px-2 py-1 text-white">Restore</button></form>
                @else
                    @if ($p->is_flagged)
                        <form method="POST" action="{{ route('admin.prices.unflag', $p) }}">@csrf<button class="rounded bg-emerald-600 px-2 py-1 text-white">Unhide</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.prices.flag', $p) }}" class="flex gap-1">@csrf<input name="reason" placeholder="Reason" class="w-28 rounded border-stone-300 text-xs"><button class="rounded bg-amber-600 px-2 py-1 text-white">Hide</button></form>
                    @endif
                    @if ($p->photo_path)
                        <form method="POST" action="{{ route('admin.prices.remove-photo', $p) }}">@csrf<button class="rounded bg-stone-500 px-2 py-1 text-white">Remove photo</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.prices.destroy', $p) }}" onsubmit="return confirm('Delete this post and reverse its points?')">@csrf @method('DELETE')<button class="rounded bg-red-600 px-2 py-1 text-white">Delete</button></form>
                @endif
            </div>
        </div>
    </div>
@empty
    <p class="text-stone-500">Nothing here.</p>
@endforelse
{{ $prices->links() }}
@endsection
