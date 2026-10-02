{{-- Lease status as staff see it (decision 18). Usage: <x-lease-status :lease="$lease" /> (load 'moveout' first) --}}
@props(['lease'])
@php($label = $lease->displayStatus())
<span class="tag {{ ['Active' => 't-green', 'Moving out' => 't-amber', 'Moved out' => 't-grey', 'Cancelled' => 't-grey'][$label] ?? 't-grey' }}">{{ $label }}</span>
