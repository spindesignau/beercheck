@extends('layouts.app', ['title' => 'Venue sync · Admin'])

@section('content')
@include('admin._nav')
<div class="mb-4 flex flex-wrap gap-3">
    @foreach ($sources as $s)
        <form method="POST" action="{{ route('admin.sync.run') }}" class="rounded-xl bg-white p-4 shadow-sm">
            @csrf
            <input type="hidden" name="source" value="{{ $s['key'] }}">
            <div class="font-bold">{{ $s['key'] }}</div>
            <div class="mb-2 text-xs text-stone-500">{{ $s['enabled'] ? 'enabled' : 'disabled' }} · {{ $s['configured'] ? 'feed URL set' : 'no feed URL configured' }}</div>
            <button class="rounded-lg bg-stone-900 px-3 py-1 text-sm font-semibold text-white" @disabled(! $s['configured'])>Run now</button>
        </form>
    @endforeach
</div>
<p class="mb-2 text-sm text-stone-500">Scheduled weekly: Sunday 02:00 AEST. Manual runs are queued and start within a minute (cron queue worker).</p>
@include('admin._sync-table', ['runs' => $runs])
{{ $runs->links() }}
@endsection
