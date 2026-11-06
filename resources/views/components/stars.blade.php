@props(['rating'])
<span class="text-amber-500" aria-label="{{ $rating }} out of 5 stars">{{ str_repeat('★', (int) round($rating)) }}<span class="text-stone-300">{{ str_repeat('★', 5 - (int) round($rating)) }}</span></span>
