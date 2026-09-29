{{-- Room status tag. Usage: <x-room-status :room="$room" /> --}}
@props(['room'])
<span class="tag {{ ['Vacant' => 't-green', 'Occupied' => 't-grey', 'Vacating' => 't-amber'][$room->status] ?? 't-grey' }}">{{ $room->status }}</span>
