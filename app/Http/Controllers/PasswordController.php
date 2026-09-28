<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** "My password": every role can change their own password. */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $user->update(['password_hash' => $request->validated('password')]);
            AuditLog::record('Password changed', 'tbl_user', $user->user_id);
        });

        return redirect()->route('password.edit')->with('status', 'Your password was changed.');
    }
}
