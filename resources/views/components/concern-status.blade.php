{{-- Concern status and priority tags. Usage: <x-concern-status :concern="$concern" /> --}}
@props(['concern', 'priority' => true])
@if ($priority && $concern->priority === 'Urgent' && $concern->status !== 'Resolved')
    <span class="tag t-red">Urgent</span>
@endif
<span class="tag {{ ['Open' => 't-blue', 'In progress' => 't-amber', 'Resolved' => 't-green'][$concern->status] ?? 't-grey' }}">{{ $concern->status }}</span>
