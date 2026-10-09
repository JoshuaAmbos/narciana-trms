{{-- Inquiry outcome tag. Usage: <x-inquiry-status :status="$inquiry->status" /> --}}
@props(['status'])
@php
    $class = match($status) {
        'Waiting' => 't-blue bg-blue-100 text-blue-800',
        'Rented' => 't-green bg-emerald-100 text-emerald-800',
        'Referred' => 't-purple bg-purple-100 text-purple-800',
        'Declined' => 't-grey bg-slate-100 text-slate-700',
        default => 't-grey bg-slate-100 text-slate-700',
    };
@endphp
<span class="tag {{ $class }}">{{ $status }}</span>
