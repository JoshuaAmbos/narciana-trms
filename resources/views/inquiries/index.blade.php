@extends('layouts.app')

@section('title', 'Inquiries')

@section('content')
    <div class="panel">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h2 style="margin: 0;">Inquiry log</h2>
                @if ($waitingCount > 0)
                    <span class="tag t-blue">{{ $waitingCount }} waiting</span>
                @endif
            </div>
            <div>
                <button type="button" class="btn" onclick="openLogInquiryModal()">+ Log inquiry</button>
            </div>
        </div>

        {{-- Horizontally scrollable status filter pills with counts --}}
        <div class="filters filters-scrollable">
            <span class="muted">Show:</span>
            <a href="{{ route('inquiries.index') }}" class="chip {{ $status ? '' : 'on' }}">
                All <span class="chip-count">({{ $counts['All'] }})</span>
            </a>
            @foreach ($statuses as $s)
                <a href="{{ route('inquiries.index', ['status' => $s]) }}" class="chip {{ $status === $s ? 'on' : '' }}">
                    {{ $s }} <span class="chip-count">({{ $counts[$s] ?? 0 }})</span>
                </a>
            @endforeach
        </div>

        @if ($inquiries->isEmpty())
            <p class="muted">No inquiries{{ $status ? " with status {$status}" : '' }}.</p>
        @else
            {{-- Desktop / Wide Tablet View --}}
            <div class="table-wrap inquiries-table-wrap hidden md:block">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Prospect</th>
                            <th>Contact / Channel</th>
                            <th>Preference</th>
                            <th>Occupants</th>
                            @if ($status !== 'Waiting')
                                <th>Assigned Room</th>
                            @endif
                            <th>Status</th>
                            <th>Logged by</th>
                            <th class="r" style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inquiries as $inquiry)
                            <tr>
                                <td>{{ $inquiry->inquiry_date->format('j M Y') }}</td>
                                <td>
                                    <b>{{ $inquiry->prospect_name }}</b>
                                    @if ($inquiry->remarks)<div class="sub">{{ $inquiry->remarks }}</div>@endif
                                </td>
                                <td>
                                    <a href="{{ $inquiry->telHref() }}">{{ $inquiry->contact_no }}</a>
                                    <div class="sub nowrap">{{ $inquiry->channel }}@if ($inquiry->via_representative) · rep @endif @if ($inquiry->wants_contact) · wants contact @endif</div>
                                </td>
                                <td>{{ $inquiry->preferred_type }}</td>
                                <td>{{ $inquiry->no_of_occupants }}</td>
                                @if ($status !== 'Waiting')
                                    <td>{{ $inquiry->status === 'Rented' && $inquiry->room ? $inquiry->room->room_no : '–' }}</td>
                                @endif
                                <td><x-inquiry-status :status="$inquiry->status" /></td>
                                <td>{{ $inquiry->user->role }}</td>
                                <td class="actions r" style="text-align: right;">
                                    <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                        <a href="{{ $inquiry->telHref() }}" class="btn sec" style="padding: 2px 8px; font-size: 11.5px;" title="Call {{ $inquiry->prospect_name }}">📞 Call</a>
                                        <button type="button" class="btn sec" style="padding: 2px 8px; font-size: 11.5px;" onclick="openStatusModal({{ $inquiry->inquiry_id }}, '{{ addslashes($inquiry->prospect_name) }}', '{{ $inquiry->status }}', '{{ addslashes($inquiry->remarks ?? '') }}', '{{ $inquiry->room_id ?? '' }}')">Update</button>
                                        @can('encodeMoa', $inquiry)
                                            @if ($inquiry->hasMatchingVacantRoom($vacantRooms))
                                                <a href="{{ route('leases.create', array_filter(['inquiry_id' => $inquiry->inquiry_id, 'room_id' => $inquiry->room_id])) }}" class="btn" style="padding: 2px 8px; font-size: 11.5px;">Encode MOA</a>
                                            @endif
                                        @endcan
                                        <a href="{{ route('inquiries.edit', $inquiry) }}" class="sub" style="margin-left: 2px;">edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (Responsive Android phones / stacked viewports) --}}
            <div class="inquiry-cards block md:hidden">
                @foreach ($inquiries as $inquiry)
                    <div class="inquiry-card">
                        <div class="inquiry-card-head">
                            <div>
                                <div class="inquiry-card-name">{{ $inquiry->prospect_name }}</div>
                                <div class="inquiry-card-date">{{ $inquiry->inquiry_date->format('j M Y') }} · logged by {{ $inquiry->user->role }}</div>
                            </div>
                            <x-inquiry-status :status="$inquiry->status" />
                        </div>
                        <div class="inquiry-card-specs">
                            <strong>{{ $inquiry->preferred_type }}</strong> room · {{ $inquiry->no_of_occupants }} {{ Str::plural('occupant', $inquiry->no_of_occupants) }}
                            · {{ $inquiry->channel }}
                            @if ($inquiry->via_representative)
                                <span class="sub">(via representative)</span>
                            @endif
                        </div>
                        <div class="inquiry-card-body">
                            <div>
                                <span>Contact: </span>
                                <a href="{{ $inquiry->telHref() }}" class="font-medium">{{ $inquiry->contact_no }}</a>
                                @if ($inquiry->wants_contact)
                                    <span class="sub nowrap">· wants to be contacted</span>
                                @endif
                            </div>
                            @if ($inquiry->status === 'Rented' && $inquiry->room)
                                <div><span>Leased room: </span><b>Room {{ $inquiry->room->room_no }}</b></div>
                            @endif
                            @if ($inquiry->remarks)
                                <div class="sub">Note: {{ $inquiry->remarks }}</div>
                            @endif
                        </div>
                        <div class="inquiry-card-actions">
                            <a href="{{ $inquiry->telHref() }}" class="btn sec">📞 Call</a>
                            <button type="button" class="btn sec" onclick="openStatusModal({{ $inquiry->inquiry_id }}, '{{ addslashes($inquiry->prospect_name) }}', '{{ $inquiry->status }}', '{{ addslashes($inquiry->remarks ?? '') }}', '{{ $inquiry->room_id ?? '' }}')">Update Outcome</button>
                            @can('encodeMoa', $inquiry)
                                @if ($inquiry->hasMatchingVacantRoom($vacantRooms))
                                    <a href="{{ route('leases.create', array_filter(['inquiry_id' => $inquiry->inquiry_id, 'room_id' => $inquiry->room_id])) }}" class="btn">Encode MOA</a>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>

            @include('partials.pagination', ['paginator' => $inquiries])
        @endif
    </div>

    {{-- Intake Modal: Log an inquiry (room pre-assignment removed) --}}
    @include('inquiries._form_modal')

    {{-- Quick Outcome Update Modal --}}
    <div id="status-modal" class="modal-backdrop" hidden>
        <div class="modal-box">
            <h2>
                <span>Update Inquiry Outcome</span>
                <button type="button" class="modal-close-btn" onclick="closeStatusModal()">&times;</button>
            </h2>
            <form method="POST" id="status-modal-form">
                @csrf
                @method('PATCH')
                <div class="facts" style="margin-bottom: 12px;">
                    <div><dt>Prospect</dt><dd id="sm_prospect">-</dd></div>
                </div>
                <div class="row">
                    <div class="f">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f">
                        <label for="sm_room_id">Assigned room</label>
                        <select id="sm_room_id" name="room_id">
                            <option value="">None</option>
                            @foreach ($rooms as $r)
                                <option value="{{ $r->room_id }}">Room {{ $r->room_no }} ({{ $r->room_type }}, {{ $r->status }}){{ isset($holds[$r->room_id]) ? ' – rented to '.$holds[$r->room_id]->prospect_name.', MOA pending' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="f">
                    <label for="sm_remarks">Remarks</label>
                    <input id="sm_remarks" name="remarks" type="text" maxlength="255" placeholder="e.g. Student rule mismatch, parking limitations, pricing objection...">
                </div>
                <div class="form-actions" style="margin-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn sec" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn">Save outcome</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openLogInquiryModal() {
            document.getElementById('log-inquiry-modal').removeAttribute('hidden');
        }
        function closeLogInquiryModal() {
            document.getElementById('log-inquiry-modal').setAttribute('hidden', '');
        }
        function openStatusModal(id, name, status, remarks, roomId) {
            const modal = document.getElementById('status-modal');
            const form = document.getElementById('status-modal-form');
            form.action = '{{ url('/inquiries') }}/' + id + '/status';
            document.getElementById('sm_prospect').textContent = name;
            document.getElementById('status').value = status;
            document.getElementById('sm_remarks').value = remarks || '';
            document.getElementById('sm_room_id').value = roomId || '';
            modal.removeAttribute('hidden');
        }
        function closeStatusModal() {
            document.getElementById('status-modal').setAttribute('hidden', '');
        }
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                e.target.setAttribute('hidden', '');
            }
        });
    </script>
@endsection
