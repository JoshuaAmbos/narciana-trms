@props(['room' => null, 'status' => null, 'showReason' => false])
@php
    $val = $status ?? (is_string($room) ? $room : $room?->status);
    $isRentable = is_object($room) ? (bool) ($room->is_rentable ?? true) : ($val !== 'Disabled');
    $reason = is_object($room) ? $room->disabled_reason : null;
@endphp
@if ($val === 'Occupied')
    <span class="tag t-grey bg-slate-100 text-slate-800">Occupied</span>
@elseif (! $isRentable || $val === 'Disabled')
    <span class="tag t-red bg-rose-100 text-rose-800" title="{{ $reason ?: 'Disabled for renting' }}">Disabled (Out)</span>
    @if ($showReason && $reason)
        <div class="sub text-xs text-muted" style="margin-top: 2px;">{{ $reason }}</div>
    @endif
@elseif ($val === 'Vacant')
    <span class="tag t-green bg-emerald-100 text-emerald-800">Vacant</span>
@else
    <span class="tag t-grey bg-slate-100 text-slate-800">{{ $val }}</span>
@endif
