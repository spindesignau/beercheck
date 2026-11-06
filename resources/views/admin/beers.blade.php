@extends('layouts.app', ['title' => 'Beers · Admin'])

@section('content')
@include('admin._nav')
<div class="mb-3 flex flex-wrap items-center gap-2">
    @foreach (['pending', 'approved', 'rejected'] as $s)
        <a href="{{ route('admin.beers.index', ['status' => $s]) }}" class="rounded-full px-3 py-1 text-sm {{ $status === $s ? 'bg-amber-500 font-bold text-white' : 'bg-white' }}">{{ ucfirst($s) }}</a>
    @endforeach
    <form class="ml-auto"><input type="hidden" name="status" value="{{ $status }}"><input name="q" value="{{ request('q') }}" placeholder="Search" class="rounded-lg border-stone-300 text-sm"></form>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
<table class="w-full text-left text-sm">
    <thead class="bg-stone-100"><tr><th class="p-2">Beer</th><th>Type</th><th>Submitted by</th><th>When</th><th></th></tr></thead>
    <tbody>
    @forelse ($beers as $beer)
        <tr class="border-t border-stone-100">
            <td class="p-2 font-semibold">{{ $beer->brand }} — {{ $beer->name }} @if($beer->abv)<span class="text-stone-500">({{ $beer->abv }}%)</span>@endif</td>
            <td>{{ $beer->type->name }}</td>
            <td>{{ $beer->submitter?->username ?? '—' }}</td>
            <td>{{ $beer->created_at->diffForHumans() }}</td>
            <td class="whitespace-nowrap p-2 text-right">
                @if ($beer->status->value !== 'approved')
                    <form method="POST" action="{{ route('admin.beers.approve', $beer) }}" class="inline">@csrf<button class="rounded bg-emerald-600 px-2 py-1 text-white">Approve</button></form>
                @endif
                @if ($beer->status->value === 'pending')
                    <form method="POST" action="{{ route('admin.beers.reject', $beer) }}" class="inline">@csrf
                        <input name="reason" placeholder="Reason" class="w-28 rounded border-stone-300 text-xs">
                        <button class="rounded bg-red-600 px-2 py-1 text-white">Reject</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="p-3 text-stone-500">Nothing here.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $beers->links() }}

<form method="POST" action="{{ route('admin.beers.store') }}" class="mt-6 grid gap-2 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-5">
    @csrf
    <h2 class="font-bold sm:col-span-5">Add a beer (approved immediately)</h2>
    <input name="brand" required placeholder="Brand" class="rounded-lg border-stone-300">
    <input name="name" required placeholder="Name" class="rounded-lg border-stone-300">
    <select name="type_id" class="rounded-lg border-stone-300">@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
    <input name="abv" type="number" step="0.1" placeholder="ABV %" class="rounded-lg border-stone-300">
    <button class="rounded-lg bg-stone-900 font-bold text-white">Add</button>
</form>
@endsection
