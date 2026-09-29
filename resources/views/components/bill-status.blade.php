{{-- Bill status tag. Usage: <x-bill-status :status="$bill->status" /> --}}
@props(['status'])
<span class="tag {{ ['Unpaid' => 't-amber', 'Paid' => 't-green', 'Overdue' => 't-red'][$status] ?? 't-grey' }}">{{ $status }}</span>
