@props(['label', 'name', 'type' => 'text', 'value' => null, 'hint' => null])
<label class="block">
    <span class="label">{{ $label }}</span>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->merge(['class' => 'field']) }}>
    @if ($hint)<span class="mt-1 block text-xs text-ink/60">{{ $hint }}</span>@endif
    @error($name)<span class="mt-1 block text-sm font-semibold text-red-600">{{ $message }}</span>@enderror
</label>
