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
                <button type="button" class="btn hidden lg:inline-flex items-center gap-1" onclick="openLogInquiryModal()">+ Log inquiry</button>
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('inquiries.index') }}" class="inquiry-filter-bar" id="inquiry-filter-form">
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif

            {{-- Row 1: Status Filter Pills (Full width, single clean horizontal row) --}}
            <div class="inquiry-filter-chips-row">
                <span class="inquiry-filter-label">Show:</span>
                <div class="inquiry-chips-wrap filters-scrollable">
                    <a href="{{ route('inquiries.index', array_filter(['search' => $search !== '' ? $search : null])) }}"
                       class="chip {{ $status ? '' : 'on' }}">
                        All <span class="chip-count">({{ $counts['All'] }})</span>
                    </a>
                    @foreach ($statuses as $s)
                        <a href="{{ route('inquiries.index', array_filter(['status' => $s, 'search' => $search !== '' ? $search : null])) }}"
                           class="chip {{ $status === $s ? 'on' : '' }}">
                            {{ $s }} <span class="chip-count">({{ $counts[$s] ?? 0 }})</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Row 2: Search Bar (Dedicated row with spacious input and touch buttons) --}}
            <div class="inquiry-filter-search-row">
                <div class="inquiry-search-input-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text"
                           id="inquiry-search-input"
                           name="search"
                           class="inquiry-search-input"
                           placeholder="Search prospect, phone, remarks..."
                           value="{{ $search }}"
                           autocomplete="off"
                           aria-label="Search inquiries">
                    @if ($search !== '')
                        <a href="{{ route('inquiries.index', array_filter(['status' => $status])) }}"
                           class="inquiry-search-clear-btn"
                           id="inquiry-search-clear"
                           title="Clear search"
                           aria-label="Clear inquiry search">
                            &times;
                        </a>
                    @endif
                </div>
                <div class="inquiry-search-actions">
                    <button type="submit" class="btn sec inquiry-search-submit-btn">Search</button>
                    @if ($search !== '' || $status)
                        <a href="{{ route('inquiries.index') }}" class="btn sec inquiry-reset-btn" title="Reset all filters">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($inquiries->isEmpty())
            @if ($search !== '')
                <div style="padding: 24px 0;">
                    <p class="muted">No inquiries found matching your search criteria.</p>
                    <a href="{{ route('inquiries.index', array_filter(['status' => $status])) }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">Clear search</a>
                </div>
            @else
                <p class="muted">No inquiries{{ $status ? " with status {$status}" : '' }}.</p>
            @endif
        @else
            {{-- Tablet Landscape (~1024px-1280px) & Desktop View --}}
            <div class="table-wrap inquiries-table-wrap hidden lg:block">
                <table class="inquiries-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Prospect</th>
                            <th>Contact / Channel</th>
                            <th>Preference</th>
                            <th>Occupants</th>
                            <th>Status</th>
                            <th>Logged by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inquiries as $inquiry)
                            <tr class="inquiry-row"
                                tabindex="0"
                                role="button"
                                aria-label="View details and actions for {{ $inquiry->prospect_name }}"
                                onclick="openInquiryModal({{ $inquiry->inquiry_id }})"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openInquiryModal({{ $inquiry->inquiry_id }});}">
                                <td>{{ $inquiry->inquiry_date->format('j M Y') }}</td>
                                <td>
                                    <b>{{ $inquiry->prospect_name }}</b>
                                    @if ($inquiry->remarks)<div class="sub">{{ $inquiry->remarks }}</div>@endif
                                </td>
                                <td>
                                    <a href="{{ $inquiry->telHref() }}" class="font-medium" onclick="event.stopPropagation()">{{ $inquiry->contact_no }}</a>
                                    <div class="sub nowrap">{{ $inquiry->channel }}@if ($inquiry->wants_contact) · wants contact @endif</div>
                                </td>
                                <td>{{ $inquiry->preferred_type }}</td>
                                <td>{{ $inquiry->no_of_occupants }}</td>
                                <td><x-inquiry-status :status="$inquiry->status" /></td>
                                <td>{{ $inquiry->user->role }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tablet Portrait (~712px-800px) & Mobile Stacked Cards View --}}
            <div class="inquiry-cards block lg:hidden pb-24">
                @foreach ($inquiries as $inquiry)
                    <div class="inquiry-card inquiry-card-clickable"
                         tabindex="0"
                         role="button"
                         aria-label="View details and actions for {{ $inquiry->prospect_name }}"
                         onclick="openInquiryModal({{ $inquiry->inquiry_id }})"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openInquiryModal({{ $inquiry->inquiry_id }});}">
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
                        </div>
                        <div class="inquiry-card-body">
                            <div>
                                <span>Contact: </span>
                                <a href="{{ $inquiry->telHref() }}" class="font-medium" onclick="event.stopPropagation()">{{ $inquiry->contact_no }}</a>
                                @if ($inquiry->wants_contact)
                                    <span class="sub nowrap">· wants to be contacted</span>
                                @else
                                    <span class="sub nowrap">· no phone follow-ups</span>
                                @endif
                            </div>
                            @if ($inquiry->remarks)
                                <div class="sub">Note: {{ $inquiry->remarks }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="inquiries-pagination pb-20 lg:pb-0">
                @include('partials.pagination', ['paginator' => $inquiries])
            </div>

            {{-- Inquiry Details & Actions Popups for each record --}}
            @foreach ($inquiries as $inquiry)
                <div id="detail-modal-{{ $inquiry->inquiry_id }}" class="modal-backdrop inquiry-detail-modal" hidden>
                    <div class="modal-box" style="max-width: 540px;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <h2 style="margin: 0 0 4px 0; font-size: 19px; color: var(--navy);">{{ $inquiry->prospect_name }}</h2>
                                <div class="sub" style="font-size: 12.5px;">
                                    Logged by {{ $inquiry->user->role }} on {{ $inquiry->inquiry_date->format('j F Y') }}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <x-inquiry-status :status="$inquiry->status" />
                                <button type="button" class="modal-close-btn" onclick="closeInquiryModal({{ $inquiry->inquiry_id }})" aria-label="Close">&times;</button>
                            </div>
                        </div>

                        {{-- Prospect Facts Grid --}}
                        <div class="facts" style="margin-bottom: 16px;">
                            <div>
                                <dt>Contact Number</dt>
                                <dd>
                                    <a href="{{ $inquiry->telHref() }}" style="font-weight: 600;">{{ $inquiry->contact_no }}</a>
                                    @if ($inquiry->wants_contact)
                                        <span class="tag t-blue" style="font-size: 11px; margin-left: 4px;">Wants contact</span>
                                    @else
                                        <span class="tag" style="font-size: 11px; margin-left: 4px; background: #e2e8f0; color: #64748b;">No phone follow-up</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Channel</dt>
                                <dd>{{ $inquiry->channel }}</dd>
                            </div>
                            <div>
                                <dt>Room Preference</dt>
                                <dd><b>{{ $inquiry->preferred_type }}</b> room</dd>
                            </div>
                            <div>
                                <dt>Occupants</dt>
                                <dd>{{ $inquiry->no_of_occupants }} {{ Str::plural('occupant', $inquiry->no_of_occupants) }}</dd>
                            </div>
                            @if ($inquiry->remarks)
                                <div style="grid-column: 1 / -1;">
                                    <dt>Remarks / Notes</dt>
                                    <dd style="white-space: pre-wrap; color: var(--text);">{{ $inquiry->remarks }}</dd>
                                </div>
                            @endif
                        </div>

                        {{-- Modal Action Bar with Guardrails & Universal Disabled States --}}
                        <div class="inquiry-modal-actions" style="border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center;">
                            {{-- Call Button --}}
                            @if (!$inquiry->wants_contact)
                                <button type="button" class="btn sec disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Prospect opted out of phone contact.">📞 Call</button>
                            @else
                                <a href="{{ $inquiry->telHref() }}" class="btn sec" title="Call {{ $inquiry->prospect_name }}">📞 Call</a>
                            @endif

                            {{-- Update Outcome Button --}}
                            <button type="button" class="btn sec" onclick="closeInquiryModal({{ $inquiry->inquiry_id }}); openStatusModal({{ $inquiry->inquiry_id }}, '{{ addslashes($inquiry->prospect_name) }}', '{{ $inquiry->status }}', '{{ addslashes($inquiry->remarks ?? '') }}')">Update Outcome</button>

                            {{-- Encode MOA Guardrail: Never hidden on any entry; disabled on inapplicable entries --}}
                            @if ($inquiry->status === 'Waiting')
                                @if (!auth()->user()->can('encodeMoa', $inquiry))
                                    <button type="button" class="btn disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Disabled: Caretaker role cannot encode contracts.">Encode MOA</button>
                                @elseif (!$inquiry->hasMatchingVacantRoom($vacantRooms))
                                    <button type="button" class="btn disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Disabled: No vacant {{ $inquiry->preferred_type }} rooms available.">Encode MOA</button>
                                @else
                                    <a href="{{ route('leases.create', ['inquiry_id' => $inquiry->inquiry_id]) }}" class="btn" title="Encode MOA for {{ $inquiry->prospect_name }}">Encode MOA</a>
                                @endif
                            @elseif ($inquiry->status === 'Rented')
                                <button type="button" class="btn disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Disabled: MOA already encoded{{ $inquiry->room ? ' (Room '.$inquiry->room->room_no.')' : '' }}.">Encode MOA</button>
                            @elseif ($inquiry->status === 'Referred')
                                <button type="button" class="btn disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Disabled: Inquiry was referred to another property.">Encode MOA</button>
                            @elseif ($inquiry->status === 'Declined')
                                <button type="button" class="btn disabled cursor-not-allowed opacity-40 bg-slate-100 text-slate-400" disabled title="Disabled: Inquiry outcome is Declined.">Encode MOA</button>
                            @endif

                            {{-- Edit Details Full Form Link --}}
                            <a href="{{ route('inquiries.edit', $inquiry) }}" class="btn sec" title="Edit information">Edit</a>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Floating Action Button (FAB) for Portrait and Mobile viewports --}}
    <button type="button" class="inquiry-fab fixed bottom-6 right-6 z-40 lg:hidden flex items-center justify-center"
            onclick="openLogInquiryModal()" aria-label="Log inquiry" title="Log inquiry">
        +
    </button>

    {{-- Intake Modal: Log an inquiry (room pre-assignment removed) --}}
    @include('inquiries._form_modal')

    {{-- Quick Outcome Update Modal --}}
    <div id="status-modal" class="modal-backdrop" hidden>
        <div class="modal-box">
            <h2>
                <span>Update Inquiry Outcome</span>
                <button type="button" class="modal-close-btn" onclick="closeStatusModal()">&times;</button>
            </h2>
            <form method="POST" id="status-modal-form" onsubmit="return handleStatusSubmit(event)">
                @csrf
                @method('PATCH')
                <div class="facts" style="margin-bottom: 12px;">
                    <div><dt>Prospect</dt><dd id="sm_prospect">-</dd></div>
                </div>
                <div class="row">
                    <div class="f">
                        <label for="status">Status</label>
                        <select id="status" name="status" required onchange="handleStatusChange(this.value)">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f" id="sm_room_group">
                        <label for="sm_room_id">Assigned room</label>
                        <select id="sm_room_id" name="room_id">
                            <option value="">None</option>
                            @foreach ($rooms as $r)
                                <option value="{{ $r->room_id }}">Room {{ $r->room_no }} ({{ $r->room_type }}, {{ $r->status }}){{ isset($holds[$r->room_id]) ? ' – rented to '.$holds[$r->room_id]->prospect_name.', MOA pending' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Structured Reason Group for Declined Outcome --}}
                <div class="f" id="declined_reason_group" style="display: none;">
                    <label for="sm_declined_reason">Reason for decline <span class="req" style="color: var(--red);">*</span></label>
                    <select id="sm_declined_reason" name="declined_reason" onchange="handleDeclinedReasonChange(this.value)">
                        <option value="">-- Select structured reason --</option>
                        @foreach ($declinedReasons as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Selecting a structured reason automatically populates the remarks.</div>
                </div>

                <div class="f">
                    <label for="sm_remarks" id="sm_remarks_label">Remarks</label>
                    <input id="sm_remarks" name="remarks" type="text" maxlength="255" placeholder="e.g. Student rule mismatch, parking limitations, pricing objection...">
                    <div class="hint" id="sm_remarks_hint">Persisted into inquiry remarks.</div>
                </div>
                <div class="form-actions" style="margin-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn sec" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn">Save outcome</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openInquiryModal(id) {
            const modal = document.getElementById('detail-modal-' + id);
            if (modal) modal.removeAttribute('hidden');
        }
        function closeInquiryModal(id) {
            const modal = document.getElementById('detail-modal-' + id);
            if (modal) modal.setAttribute('hidden', '');
        }
        function openLogInquiryModal() {
            document.getElementById('log-inquiry-modal').removeAttribute('hidden');
        }
        function closeLogInquiryModal() {
            document.getElementById('log-inquiry-modal').setAttribute('hidden', '');
        }
        function handleStatusChange(status) {
            const declinedGroup = document.getElementById('declined_reason_group');
            const declinedSelect = document.getElementById('sm_declined_reason');

            if (status === 'Declined') {
                declinedGroup.style.display = 'block';
                declinedSelect.setAttribute('required', 'required');
            } else {
                declinedGroup.style.display = 'none';
                declinedSelect.removeAttribute('required');
            }
        }
        function handleDeclinedReasonChange(reason) {
            const remarksInput = document.getElementById('sm_remarks');
            if (!reason) return;

            if (reason === 'Other (custom note)') {
                remarksInput.placeholder = 'Specify custom note for decline...';
                remarksInput.focus();
            } else {
                remarksInput.value = reason;
            }
        }
        function handleStatusSubmit(e) {
            const status = document.getElementById('status').value;
            const declinedSelect = document.getElementById('sm_declined_reason');
            const declinedReason = declinedSelect.value;
            const remarksInput = document.getElementById('sm_remarks');
            const remarks = remarksInput.value.trim();

            if (status === 'Declined') {
                if (!declinedReason && !remarks) {
                    e.preventDefault();
                    alert('Please select a structured reason for declining this inquiry.');
                    declinedSelect.focus();
                    return false;
                }
                if (declinedReason === 'Other (custom note)' && !remarks) {
                    e.preventDefault();
                    alert('Please provide a note in the remarks field for "Other (custom note)".');
                    remarksInput.focus();
                    return false;
                }
                if (declinedReason && declinedReason !== 'Other (custom note)' && !remarks) {
                    remarksInput.value = declinedReason;
                }
            }
            return true;
        }
        function openStatusModal(id, name, status, remarks) {
            const modal = document.getElementById('status-modal');
            const form = document.getElementById('status-modal-form');
            form.action = '{{ url('/inquiries') }}/' + id + '/status';
            document.getElementById('sm_prospect').textContent = name;
            document.getElementById('status').value = status;
            document.getElementById('sm_remarks').value = remarks || '';

            const declinedSelect = document.getElementById('sm_declined_reason');
            declinedSelect.value = '';
            if (status === 'Declined' && remarks) {
                for (let i = 0; i < declinedSelect.options.length; i++) {
                    if (declinedSelect.options[i].value && remarks.startsWith(declinedSelect.options[i].value)) {
                        declinedSelect.selectedIndex = i;
                        break;
                    }
                }
                if (!declinedSelect.value) {
                    declinedSelect.value = 'Other (custom note)';
                }
            }

            handleStatusChange(status);
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
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('inquiry-search-input');
            const clearBtn = document.getElementById('inquiry-search-clear');
            if (searchInput && clearBtn) {
                searchInput.addEventListener('input', function () {
                    clearBtn.style.display = this.value.length > 0 ? 'inline-flex' : 'none';
                });
            }
        });
    </script>
@endsection
