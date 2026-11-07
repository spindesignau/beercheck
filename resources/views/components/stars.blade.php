@props(['rating', 'size' => 'h-4 w-4'])
<span class="inline-flex items-center gap-0.5 align-middle" aria-label="{{ $rating }} out of 5 stars" role="img">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="{{ $size }} {{ $i <= round($rating) ? 'text-beer' : 'text-ink/15' }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
    @endfor
</span>
