@extends('layouts.app', ['title' => 'Venues · Admin'])

@section('content')
@include('admin._nav')
<form class="mb-3 flex flex-wrap gap-2 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="Name, suburb or postcode" class="rounded-lg border-stone-300">
    <select name="source" class="rounded-lg border-stone-300"><option value="">All sources</option>@foreach ($sources as $s)<option @selected(request('source') === $s->value)>{{ $s->value }}</option>@endforeach</select>
    <select name="active" class="rounded-lg border-stone-300"><option value="">Any status</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></select>
    <button class="rounded-lg bg-stone-900 px-3 text-white">Filter</button>
    <a href="{{ route('admin.venues.create') }}" class="ml-auto rounded-lg bg-amber-500 px-3 py-2 font-bold text-white">+ Add venue</a>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
<table class="w-full text-left text-sm">
    <thead class="bg-stone-100"><tr><th class="p-2">Venue</th><th>Source</th><th>Licence</th><th>Last synced</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach ($venues as $v)
        <tr class="border-t border-stone-100">
            <td class="p-2"><a class="font-semibold underline" href="{{ route('admin.venues.edit', $v) }}">{{ $v->name }}</a><div class="text-xs text-stone-500">{{ $v->suburb }} {{ $v->state }} {{ $v->postcode }}</div></td>
            <td>{{ $v->source->value }}</td>
            <td class="text-xs">{{ $v->licence_type }}</td>
            <td class="text-xs">{{ $v->last_synced_at?->diffForHumans() ?? '—' }}</td>
            <td>{{ $v->is_active ? 'Active' : 'Inactive ('.$v->deactivation_reason.')' }}</td>
            <td class="p-2 text-right"><form method="POST" action="{{ route('admin.venues.toggle', $v) }}">@csrf<button class="rounded bg-stone-200 px-2 py-1">{{ $v->is_active ? 'Deactivate' : 'Activate' }}</button></form></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $venues->links() }}
@endsection
