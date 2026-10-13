{{-- Shared add/edit form. Needs: $user, $roles, $canChangeRole (bool), $passwordRequired (bool) --}}
@csrf

<div class="row">
    <div class="f @error('full_name') has-err @enderror">
        <label for="full_name">Full name <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
        <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $user->full_name) }}" maxlength="255" required>
        @error('full_name') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('username') has-err @enderror">
        <label for="username">Username (used to log in) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
        <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" maxlength="50"
               autocapitalize="none" autocorrect="off" spellcheck="false" required>
        @error('username') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('role') has-err @enderror">
        <label for="role">Role <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
        @if ($canChangeRole)
            <select id="role" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                @endforeach
            </select>
        @else
            <input id="role" type="text" value="{{ $user->role }}" disabled>
            <div class="hint">You cannot change your own role.</div>
        @endif
        @error('role') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('contact_no') has-err @enderror">
        <label for="contact_no">Contact number <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
        <input id="contact_no" name="contact_no" type="tel" value="{{ old('contact_no', $user->contact_no) }}" maxlength="15" required>
        @error('contact_no') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('password') has-err @enderror">
        <label for="password">
            @if ($passwordRequired)
                Password <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span>
            @else
                New password <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(leave blank to keep current)</span>
            @endif
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password" @required($passwordRequired)>
        <div class="hint">At least 8 characters.</div>
        @error('password') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f">
        <label for="password_confirmation">
            @if ($passwordRequired)
                Type the password again <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span>
            @else
                Type the new password again <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span>
            @endif
        </label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required($passwordRequired)>
    </div>
</div>
