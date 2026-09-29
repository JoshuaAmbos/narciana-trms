{{-- Tenant profile fields (new MOA and tenant edit). Needs: $tenant (may be a new, empty Tenant) --}}
<div class="row">
    <div class="f @error('full_name') has-err @enderror">
        <label for="full_name">Full name of principal renter</label>
        <input id="full_name" name="full_name" type="text" maxlength="255" value="{{ old('full_name', $tenant->full_name) }}">
        @error('full_name') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('contact_no') has-err @enderror">
        <label for="contact_no">Contact number</label>
        <input id="contact_no" name="contact_no" type="tel" maxlength="15" value="{{ old('contact_no', $tenant->contact_no) }}">
        @error('contact_no') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>
<div class="row">
    <div class="f @error('valid_id_type') has-err @enderror">
        <label for="valid_id_type">Valid ID presented</label>
        <input id="valid_id_type" name="valid_id_type" type="text" maxlength="50" list="id-types" value="{{ old('valid_id_type', $tenant->valid_id_type) }}">
        <datalist id="id-types">
            @foreach (['PhilSys National ID', "Driver's License", 'Passport', 'UMID', 'SSS ID', 'Postal ID', "Voter's ID", 'PRC ID', 'Student ID', 'Company ID'] as $idType)
                <option value="{{ $idType }}">
            @endforeach
        </datalist>
        @error('valid_id_type') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('valid_id_no') has-err @enderror">
        <label for="valid_id_no">ID number</label>
        <input id="valid_id_no" name="valid_id_no" type="text" maxlength="50" value="{{ old('valid_id_no', $tenant->valid_id_no) }}">
        @error('valid_id_no') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>
