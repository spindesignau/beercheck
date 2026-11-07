@props(['href', 'icon', 'label', 'active' => false])
<a href="{{ $href }}" @class(['flex flex-1 flex-col items-center gap-0.5 py-1.5 text-[11px] font-bold transition', 'text-ink' => $active, 'text-ink/45' => ! $active]) @if($active) aria-current="page" @endif>
    <x-icon :name="$icon" class="h-6 w-6" />
    {{ $label }}
</a>
