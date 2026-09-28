{{-- Shared add/edit form. Needs: $user, $roles, $canChangeRole (bool), $passwordRequired (bool) --}}
@csrf

<div class="row">
    <div class="f @error('full_name') has-err @enderror">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $user->full_name) }}" maxlength="255" required>
        @error('full_name') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('username') has-err @enderror">
        <label for="username">Username (used to log in)</label>
        <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" maxlength="50"
               autocapitalize="none" autocorrect="off" spellcheck="false" required>
        @error('username') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('role') has-err @enderror">
        <label for="role">Role</label>
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
        <label for="contact_no">Contact number</label>
        <input id="contact_no" name="contact_no" type="tel" value="{{ old('contact_no', $user->contact_no) }}" maxlength="15" required>
        @error('contact_no') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('password') has-err @enderror">
        <label for="password">{{ $passwordRequired ? 'Password' : 'New password (leave blank to keep the current one)' }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" @required($passwordRequired)>
        <div class="hint">At least 8 characters.</div>
        @error('password') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f">
        <label for="password_confirmation">Type the password again</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required($passwordRequired)>
    </div>
</div>
