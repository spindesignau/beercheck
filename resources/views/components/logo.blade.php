@props(['variant' => 'horizontal', 'class' => 'h-9 w-auto'])
@php
    $file = ['horizontal' => 'logo-horizontal', 'light' => 'logo-horizontal-light', 'stacked' => 'logo-stacked', 'badge' => 'logo-badge', 'pin' => 'pin'][$variant];
    [$w, $h] = ['horizontal' => [720, 299], 'light' => [720, 299], 'stacked' => [600, 939], 'badge' => [600, 939], 'pin' => [256, 389]][$variant];
@endphp
<img src="{{ asset('images/brand/'.$file.'.webp') }}" width="{{ $w }}" height="{{ $h }}" alt="Beercheck — beer prices at your fingertips" {{ $attributes->merge(['class' => $class]) }}>
