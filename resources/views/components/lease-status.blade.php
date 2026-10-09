{{-- Lease status. Usage: <x-lease-status :lease="$lease" /> --}}
@props(['lease'])
@php($label = $lease->displayStatus())
<span class="tag {{ ['Active' => 't-green', 'Ended' => 't-grey', 'Cancelled' => 't-grey'][$label] ?? 't-grey' }}">{{ $label }}</span>
