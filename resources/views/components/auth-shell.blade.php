@props(['title', 'subtitle' => null])
<div class="bg-bubbles -mb-28 min-h-[calc(100dvh-3.5rem)] px-4 pb-32 pt-6 md:mb-0 md:rounded-b-[2rem] md:pb-12">
    <div class="mx-auto max-w-sm">
        <x-logo variant="badge" class="mx-auto h-40 w-auto drop-shadow-xl" />
        <div class="card mt-5 p-6">
            <h1 class="text-2xl">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1 text-sm text-ink/60">{{ $subtitle }}</p>@endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</div>
