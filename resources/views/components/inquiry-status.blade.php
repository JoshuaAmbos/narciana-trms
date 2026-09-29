{{-- Inquiry outcome tag. Usage: <x-inquiry-status :status="$inquiry->status" /> --}}
@props(['status'])
<span class="tag {{ ['Waiting' => 't-blue', 'Rented' => 't-green', 'Referred' => 't-grey', 'Declined' => 't-amber'][$status] ?? 't-grey' }}">{{ $status }}</span>
