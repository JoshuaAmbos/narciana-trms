{{-- Room status tag. Usage: <x-room-status :room="$room" /> or <x-room-status :status="$status" /> --}}
@props(['room' => null, 'status' => null])
@php
    $val = $status ?? (is_string($room) ? $room : $room?->status);
    $tagClass = match ($val) {
        'Vacant' => 't-green bg-emerald-100 text-emerald-800',
        default => 't-grey bg-slate-100 text-slate-800',
    };
@endphp
<span class="tag {{ $tagClass }}">{{ $val }}</span>
