@props(['label', 'name', 'url', 'placeholder' => '', 'initialId' => '', 'initialLabel' => '', 'withLocation' => false, 'icon' => 'search'])
<div x-data="typeahead({ url: @js($url), initialId: @js((string) $initialId), initialLabel: @js($initialLabel){{ $withLocation ? ', extraParams: () => (lat ? { lat, lng } : {})' : '' }} })" class="relative">
    <label class="label" for="{{ $name }}-q">{{ $label }}</label>
    <div class="field flex items-center gap-2 !py-0" :class="selectedId && '!ring-2 !ring-emerald-500'">
        <x-icon :name="$icon" class="h-5 w-5 shrink-0 text-ink/40" />
        <input id="{{ $name }}-q" x-model="query" @input.debounce.250ms="search" @focus="results.length && (open = true)"
               @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter" @keydown.escape="open = false"
               autocomplete="off" required placeholder="{{ $placeholder }}" class="min-w-0 flex-1 bg-transparent py-3 outline-none"
               role="combobox" :aria-expanded="open" aria-autocomplete="list">
        <x-icon name="check" class="h-5 w-5 shrink-0 text-emerald-600" x-show="selectedId" x-cloak />
    </div>
    <input type="hidden" name="{{ $name }}" :value="selectedId">
    <ul x-show="open && results.length" x-cloak @click.outside="open = false" role="listbox"
        class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-2xl bg-white py-1 shadow-xl ring-1 ring-ink/10">
        <template x-for="(r, i) in results" :key="r.id">
            <li @click="choose(r)" :class="i === active && 'bg-beer-light'" role="option" class="cursor-pointer px-4 py-2.5 text-sm font-semibold hover:bg-beer-light/60" x-text="r.label"></li>
        </template>
    </ul>
    {{ $slot }}
    @error($name)<span class="mt-1 block text-sm font-semibold text-red-600">{{ $message }}</span>@enderror
</div>
