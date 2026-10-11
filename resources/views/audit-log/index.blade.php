@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
    <form method="GET" action="{{ route('audit-log.index') }}" class="panel" novalidate id="auditLogFilterForm">
        <div class="row">
            <div class="f @error('user') has-err @enderror">
                <label for="user">User</label>
                <select id="user" name="user" onchange="this.form.submit()">
                    <option value="">All users</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->user_id }}" @selected((string) ($filters['user'] ?? '') === (string) $u->user_id)>
                            {{ $u->full_name }} ({{ $u->role }}){{ $u->is_active ? '' : ' – deactivated' }}
                        </option>
                    @endforeach
                </select>
                @error('user') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="f @error('action') has-err @enderror">
                <label for="action">Action</label>
                <select id="action" name="action" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
                @error('action') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="f @error('from') has-err @enderror">
                <label for="from">From</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" onchange="this.form.submit()">
                @error('from') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="f @error('to') has-err @enderror">
                <label for="to">To</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" onchange="this.form.submit()">
                @error('to') <div class="err">{{ $message }}</div> @enderror
            </div>
        </div>
        @php
            $hasActiveFilters = !empty($filters['user']) || !empty($filters['action']) || !empty($filters['from']) || !empty($filters['to']);
        @endphp
        @if ($hasActiveFilters)
            <div class="form-actions" style="margin-top: 10px;">
                <a href="{{ route('audit-log.index') }}" class="btn sec">Clear Filters</a>
            </div>
        @endif
    </form>

    <div class="panel">
        <h2>Audit log <span class="muted">newest first · view only</span></h2>
        @if ($entries->isEmpty())
            <p class="muted">No entries match these filters.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date and time</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr>
                    @foreach ($entries as $e)
                        <tr>
                            <td class="nowrap">{{ $e->logged_at->format('j M Y') }}<span class="sub">{{ $e->logged_at->format('g:i:s A') }}</span></td>
                            <td>{{ $e->user?->full_name ?? '—' }}<span class="sub">{{ $e->user?->role }}</span></td>
                            <td>{{ $e->action }}</td>
                            <td class="nowrap">{{ $tableLabels[$e->table_name] ?? $e->table_name }}@if ($e->record_id) #{{ $e->record_id }}@endif</td>
                            <td class="audit-details">{{ $e->details }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $entries])
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('auditLogFilterForm');
            if (form) {
                form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        form.submit();
                    });
                });
            }
        });
    </script>
@endpush
