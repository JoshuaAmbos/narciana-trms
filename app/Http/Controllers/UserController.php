<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** User Accounts (Owner only): list, add, edit, deactivate, reactivate. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('role')->orderBy('full_name')->get(), // enum order: Owner, Manager, Caretaker
            'roleAccess' => config('narciana.role_access'),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User(['role' => 'Caretaker']), 'roles' => User::ROLES]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'full_name' => $data['full_name'],
                'username' => $data['username'],
                'role' => $data['role'],
                'contact_no' => $data['contact_no'],
                'password_hash' => $data['password'], // hashed by the model's 'hashed' cast
                'is_active' => true,
            ]);
            AuditLog::record('User created', 'tbl_user', $user->user_id);

            return $user;
        });

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' was added.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => User::ROLES,
            'canChangeRole' => Gate::allows('changeRole', $user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $user) {
            $user->fill([
                'full_name' => $data['full_name'],
                'username' => $data['username'],
                'contact_no' => $data['contact_no'],
            ]);
            if (isset($data['role']) && Gate::allows('changeRole', $user)) {
                $user->role = $data['role'];
            }
            $passwordReset = filled($data['password'] ?? null);
            if ($passwordReset) {
                $user->password_hash = $data['password'];
            }
            $user->save();

            AuditLog::record('User changed', 'tbl_user', $user->user_id);
            if ($passwordReset) {
                AuditLog::record('Password reset', 'tbl_user', $user->user_id);
            }
        });

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' was updated.");
    }

    public function deactivate(User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => false]);
            AuditLog::record('User deactivated', 'tbl_user', $user->user_id);
        });

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' was deactivated. It can no longer log in.");
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => true]);
            AuditLog::record('User activated', 'tbl_user', $user->user_id);
        });

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' was reactivated.");
    }
}
