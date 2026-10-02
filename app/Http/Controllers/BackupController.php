<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function restore(RestoreBackupRequest $request): RedirectResponse
    {
        $name = $request->validated('backup');
        $this->backups->path($name); // 404 for unknown names, before anything is touched

        try {
            $safety = $this->backups->restore($name, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', "The data was restored from {$name}. A safety backup of the data before the restore was saved as {$safety}. Please log in again.");
    }
}
