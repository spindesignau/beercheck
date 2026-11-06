@extends('layouts.app', ['title' => 'Claims · Admin'])

@section('content')
@include('admin._nav')
@forelse ($claims as $c)
    <div class="mb-3 rounded-xl bg-white p-4 text-sm shadow-sm">
        <div class="font-semibold">{{ $c->venue->name }}, {{ $c->venue->suburb }} <span class="ml-2 rounded-full bg-stone-100 px-2 text-xs">{{ str_replace('_', ' ', $c->status->value) }}</span></div>
        <div class="text-stone-600">
            {{ $c->business_name }} · {{ $c->business_email }} @if($c->business_email_verified_at)<span class="text-emerald-700">✓ email confirmed</span>@endif
            @if($c->abn) · ABN {{ $c->abn }} (<a class="underline" target="_blank" rel="noopener" href="https://abr.business.gov.au/ABN/View?abn={{ $c->abn }}">ABR lookup</a>)@endif
        </div>
        <div class="text-stone-500">User {{ $c->user->username }} ({{ $c->user->email }}) · venue phone {{ $c->venue->phone ?? '—' }} · {{ $c->venue->website ?? 'no website' }}</div>
        @if (in_array($c->status->value, ['pending', 'email_verified']))
            <div class="mt-2 flex gap-2">
                <form method="POST" action="{{ route('admin.claims.approve', $c) }}">@csrf<button class="rounded bg-emerald-600 px-3 py-1 text-white">Approve</button></form>
                <form method="POST" action="{{ route('admin.claims.reject', $c) }}" class="flex gap-1">@csrf<input name="notes" placeholder="Notes" class="rounded border-stone-300 text-xs"><button class="rounded bg-red-600 px-3 py-1 text-white">Reject</button></form>
            </div>
        @endif
    </div>
@empty
    <p class="text-stone-500">No claims.</p>
@endforelse
{{ $claims->links() }}
@endsection
