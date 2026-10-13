<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Backup and Restore (Owner only). The work is done by BackupService. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index(): View
    {
        return view('backups.index', [
            'backups' => $this->backups->list(),
            'keep' => config('narciana.backup.keep'),
            'setting' => Setting::current(),
        ]);
    }

    /** "Create Backup Now" */
    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create('Manual', $request->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The backup failed: '.$e->getMessage());
        }

        return redirect()->route('backups.index')
            ->with('status', "Backup created: {$backup['name']} (".BackupService::humanSize($backup['size']).').');
    }

    public function download(Request $request, string $backup): BinaryFileResponse
    {
        $file = $this->backups->path($backup); // 404 for unknown names
        $this->backups->recordDownload($backup, $request->user());

        return response()->download($file, $backup, ['Content-Type' => 'application/sql']);
    }

    public function destroy(Request $request, string $backup): RedirectResponse
    {
        $this->backups->delete($backup, $request->user());

        return redirect()->route('backups.index')->with('status', "Backup {$backup} was deleted.");
    }

    /** Safety backup, restore, then log out (the logged-in account may not even exist in the older copy). */
    public function restore(Request $request): RedirectResponse
    {
        if (! $request->has('backup_file') && $request->has('backup')) {
            $request->merge(['backup_file' => $request->input('backup')]);
        }
        if (! $request->has('backup') && $request->has('backup_file')) {
            $request->merge(['backup' => $request->input('backup_file')]);
        }

        $validator = Validator::make($request->all(), [
            'backup_file' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'backup_file.required' => 'Choose the backup to restore.',
            'password.required' => 'Enter your account password to authorize database restoration.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = $request->user();
        if (! Hash::check($request->input('password'), $user->getAuthPassword())) {
            AuditLog::record(
                'Database restoration failed: Invalid password authorization',
                'backup',
                null,
                $user->user_id
            );

            return back()
                ->withErrors(['password' => 'The provided password does not match your account password.'])
                ->withInput()
                ->with('warning', 'The provided password does not match your account password.');
        }

        $name = $request->input('backup_file');
        $this->backups->path($name); // 404 for unknown names, before anything is touched

        try {
            $safety = $this->backups->restore($name, $user);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        $auditUserId = User::whereKey($user->user_id)->exists()
            ? $user->user_id
            : (User::where('role', 'Owner')->value('user_id') ?? $user->user_id);

        AuditLog::record(
            "Database restored from {$name} via password authorization",
            'backup',
            null,
            $auditUserId
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', "The data was restored from {$name}. A safety backup of the data before the restore was saved as {$safety}. Please log in again.");
    }
}
