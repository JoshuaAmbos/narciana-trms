{{-- Move-out status tag. Usage: <x-moveout-status :moveout="$moveout" /> --}}
@props(['moveout'])
@php($label = $moveout->status === 'Pending'
    ? (! $moveout->actual_date ? 'Notice given' : ($moveout->inspected_at ? 'Awaiting approval' : 'To inspect'))
    : $moveout->status)
<span class="tag {{ ['Notice given' => 't-blue', 'To inspect' => 't-blue', 'Awaiting approval' => 't-amber', 'Refunded' => 't-green', 'Excess billed' => 't-red', 'Settled' => 't-grey'][$label] ?? 't-grey' }}">{{ $label }}</span>
