@extends('layouts.app', ['title' => 'Points · Admin'])

@section('content')
@include('admin._nav')
<div class="grid gap-4 md:grid-cols-3">
    <section class="md:col-span-2">
        <form class="mb-2 flex gap-2 text-sm">
            <input name="user" value="{{ request('user') }}" placeholder="Username" class="rounded-lg border-stone-300">
            <select name="type" class="rounded-lg border-stone-300"><option value="">All events</option>
                @foreach (\App\Enums\PointsEventType::cases() as $t)<option @selected(request('type') === $t->value)>{{ $t->value }}</option>@endforeach
            </select>
            <button class="rounded-lg bg-stone-900 px-3 text-white">Filter</button>
        </form>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-100"><tr><th class="p-2">When</th><th>User</th><th>Event</th><th>Ref</th><th class="text-right">Pts</th><th class="p-2">By</th></tr></thead>
            <tbody>
            @foreach ($events as $e)
                <tr class="border-t border-stone-100">
                    <td class="p-2 whitespace-nowrap">{{ $e->created_at->timezone('Australia/Sydney')->format('d M H:i') }}</td>
                    <td>{{ $e->user?->username }}</td>
                    <td>{{ $e->event_type->value }}@if($e->note)<div class="text-xs text-stone-500">{{ $e->note }}</div>@endif</td>
                    <td class="text-xs">{{ $e->reference_type }} #{{ $e->reference_id }}</td>
                    <td class="text-right font-bold {{ $e->points_awarded < 0 ? 'text-red-600' : '' }}">{{ $e->points_awarded }}</td>
                    <td class="p-2 text-xs">{{ $e->creator?->username ?? 'system' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        {{ $events->links() }}
    </section>
    <aside class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-2 font-bold">Top contributors</h2>
        @foreach ($leaders as $u)
            <div class="flex items-center justify-between py-1 text-sm">
                <span>{{ $u->username }} @if($u->is_banned)<span class="text-red-600">(banned)</span>@endif</span>
                <span class="flex items-center gap-2"><b>{{ $u->points }}</b>
                    <form method="POST" action="{{ route('admin.users.ban', $u) }}">@csrf<button class="text-xs underline">{{ $u->is_banned ? 'unban' : 'ban' }}</button></form>
                </span>
            </div>
        @endforeach
    </aside>
</div>
@endsection
