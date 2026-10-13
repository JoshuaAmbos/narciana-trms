@extends('layouts.app')

@section('title', 'My password')

@section('content')
    <div class="panel form-panel narrow">
        <h2>Change my password</h2>
        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="f @error('current_password') has-err @enderror">
                <label for="current_password">Current password <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="f @error('password') has-err @enderror">
                <label for="password">New password <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                <div class="hint">At least 8 characters.</div>
                @error('password') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="f">
                <label for="password_confirmation">Type the new password again <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Change password</button>
            </div>
        </form>
    </div>
@endsection
