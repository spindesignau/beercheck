@props(['label', 'name', 'type' => 'text', 'value' => null])
<label class="block">
    <span class="mb-1 block text-sm font-semibold">{{ $label }}</span>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-stone-300 px-3 py-2 focus:border-amber-500 focus:ring-amber-500']) }}>
    @error($name)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
</label>
