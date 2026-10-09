{{-- Tenant master profile fields. Needs: $tenant (may be a new, empty Tenant) --}}
<div style="margin-bottom: 20px;">
    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px; color: var(--color-ink, #1e293b);">
        Personal Identity
    </h3>
    <div class="row" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <div class="f @error('first_name') has-err @enderror" style="flex: 1 1 200px; min-height: 44px;">
            <label for="first_name">First Name <span style="color: #ef4444;">*</span></label>
            <input id="first_name" name="first_name" type="text" maxlength="100" required
                   value="{{ old('first_name', $tenant->first_name) }}"
                   placeholder="e.g. Maria Clara" style="min-height: 44px;">
            @error('first_name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('middle_name') has-err @enderror" style="flex: 1 1 160px; min-height: 44px;">
            <label for="middle_name">Middle Name <span class="muted" style="font-weight: normal;">(optional)</span></label>
            <input id="middle_name" name="middle_name" type="text" maxlength="100"
                   value="{{ old('middle_name', $tenant->middle_name) }}"
                   placeholder="e.g. Santos" style="min-height: 44px;">
            @error('middle_name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('last_name') has-err @enderror" style="flex: 1 1 200px; min-height: 44px;">
            <label for="last_name">Last Name <span style="color: #ef4444;">*</span></label>
            <input id="last_name" name="last_name" type="text" maxlength="100" required
                   value="{{ old('last_name', $tenant->last_name) }}"
                   placeholder="e.g. Dela Cruz" style="min-height: 44px;">
            @error('last_name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('name_extension') has-err @enderror" style="flex: 0 1 100px; min-height: 44px;">
            <label for="name_extension">Ext. <span class="muted" style="font-weight: normal;">(Jr., III)</span></label>
            <input id="name_extension" name="name_extension" type="text" maxlength="10"
                   value="{{ old('name_extension', $tenant->name_extension) }}"
                   placeholder="Jr." style="min-height: 44px;">
            @error('name_extension') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px; color: var(--color-ink, #1e293b);">
        Contact & Verification Credentials
    </h3>
    <div class="row" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <div class="f @error('contact_no') has-err @enderror" style="flex: 1 1 260px; min-height: 44px;">
            <label for="contact_no">Philippine Mobile Number <span style="color: #ef4444;">*</span></label>
            <input id="contact_no" name="contact_no" type="tel" maxlength="15" required
                   value="{{ old('contact_no', $tenant->contact_no) }}"
                   placeholder="09XXXXXXXXX or +639XXXXXXXXX" style="min-height: 44px;">
            @error('contact_no') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('valid_id_type') has-err @enderror" style="flex: 1 1 260px; min-height: 44px;">
            <label for="valid_id_type">Valid Government ID Type <span style="color: #ef4444;">*</span></label>
            <select id="valid_id_type" name="valid_id_type" required style="min-height: 44px;"
                    onchange="updateIdFieldConfig(this.value)">
                @php
                    $idConfigs = \App\Models\Tenant::idTypeConfigs();
                    $selectedIdType = old('valid_id_type', $tenant->valid_id_type ?: 'PhilSys National ID (PCN)');
                    $activeConfig = $idConfigs[$selectedIdType] ?? [
                        'placeholder' => 'Enter ID number',
                        'helper' => 'Official government, company, or institutional ID number',
                        'maxlength' => 50,
                    ];
                @endphp
                @foreach (array_keys($idConfigs) as $idType)
                    <option value="{{ $idType }}" {{ $selectedIdType === $idType ? 'selected' : '' }}>
                        {{ $idType }}
                    </option>
                @endforeach
            </select>
            @error('valid_id_type') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div id="other-id-container" class="f @error('other_id_type') has-err @enderror"
             style="flex: 1 1 260px; min-height: 44px; display: {{ $selectedIdType === 'Other' ? 'block' : 'none' }};">
            <label for="other_id_type">Specify Other ID Type <span style="color: #ef4444;">*</span></label>
            <input id="other_id_type" name="other_id_type" type="text" maxlength="100"
                   value="{{ old('other_id_type', $tenant->other_id_type) }}"
                   placeholder="e.g. Senior Citizen ID, Barangay ID" style="min-height: 44px;">
            @error('other_id_type') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('valid_id_no') has-err @enderror" style="flex: 1 1 260px; min-height: 44px;">
            <label for="valid_id_no">Valid ID Number / Card No. <span style="color: #ef4444;">*</span></label>
            <input id="valid_id_no" name="valid_id_no" type="text"
                   maxlength="{{ $activeConfig['maxlength'] ?? 50 }}" required
                   value="{{ old('valid_id_no', $tenant->valid_id_no) }}"
                   placeholder="{{ $activeConfig['placeholder'] ?? 'Enter ID number' }}" style="min-height: 44px;">
            <div id="valid_id_helper" class="hint" style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                {{ $activeConfig['helper'] ?? '' }}
            </div>
            @error('valid_id_no') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>

    <div style="margin-top: 8px; padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 12.5px; color: #1e40af; display: flex; align-items: flex-start; gap: 8px;">
        <span style="font-size: 16px; line-height: 1.1;">ⓘ</span>
        <div>
            <strong>PhilSys Identity Compliance (RA 10173 & PhilSys Act):</strong>
            For PhilSys National ID, strictly record the 16-digit <em>PhilSys Card Number (PCN)</em> printed on the card face. Never record or request the confidential 12-digit PhilSys Number (PSN).
        </div>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px; color: var(--color-ink, #1e293b);">
        Socio-Demographic Details & Profile Status
    </h3>
    <div class="row" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <div class="f @error('provincial_address') has-err @enderror" style="flex: 2 1 300px; min-height: 44px;">
            <label for="provincial_address">Provincial / Home Address</label>
            <input id="provincial_address" name="provincial_address" type="text" maxlength="255"
                   value="{{ old('provincial_address', $tenant->provincial_address) }}"
                   placeholder="Barangay, Municipality/City, Province" style="min-height: 44px;">
            @error('provincial_address') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('occupation') has-err @enderror" style="flex: 1 1 200px; min-height: 44px;">
            <label for="occupation">Occupation / Source of Livelihood</label>
            <input id="occupation" name="occupation" type="text" maxlength="100"
                   value="{{ old('occupation', $tenant->occupation) }}"
                   placeholder="e.g. Call Center Agent, Nurse" style="min-height: 44px;">
            @error('occupation') <div class="err">{{ $message }}</div> @enderror
        </div>

        @if ($tenant->exists)
            <div class="f @error('status') has-err @enderror" style="flex: 1 1 160px; min-height: 44px;">
                <label for="status">Profile Status</label>
                <select id="status" name="status" style="min-height: 44px;">
                    @php($currentStatus = old('status', $tenant->status ?: 'Active'))
                    <option value="Active" {{ $currentStatus === 'Active' ? 'selected' : '' }}>Active (Eligible)</option>
                    <option value="Inactive" {{ $currentStatus === 'Inactive' ? 'selected' : '' }}>Inactive (Not Renting)</option>
                    <option value="Blacklisted" {{ $currentStatus === 'Blacklisted' ? 'selected' : '' }}>Blacklisted (Barred)</option>
                </select>
                @error('status') <div class="err">{{ $message }}</div> @enderror
            </div>
        @endif
    </div>
</div>

<script>
    const idConfigs = @json(\App\Models\Tenant::idTypeConfigs());

    function updateIdFieldConfig(val) {
        toggleOtherIdInput(val);
        const config = idConfigs[val] || {
            placeholder: 'Enter ID number',
            helper: 'Official government, company, or institutional ID number',
            maxlength: 50
        };
        const idInput = document.getElementById('valid_id_no');
        const helperEl = document.getElementById('valid_id_helper');
        if (idInput) {
            idInput.placeholder = config.placeholder;
            idInput.maxLength = config.maxlength;
        }
        if (helperEl) {
            helperEl.textContent = config.helper;
        }
    }

    function toggleOtherIdInput(val) {
        var container = document.getElementById('other-id-container');
        if (container) {
            container.style.display = (val === 'Other') ? 'block' : 'none';
        }
    }
</script>
