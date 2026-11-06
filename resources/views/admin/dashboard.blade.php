@extends('layouts.app', ['title' => 'Admin'])

@section('content')
@include('admin._nav')
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
    @foreach ($stats as $label => $value)
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-3xl font-black">{{ number_format($value) }}</div>
            <div class="text-sm text-stone-500">{{ $label }}</div>
        </div>
    @endforeach
</div>
<h2 class="mb-2 mt-6 font-bold">Latest sync runs</h2>
@include('admin._sync-table', ['runs' => $lastSyncs])
@endsection
