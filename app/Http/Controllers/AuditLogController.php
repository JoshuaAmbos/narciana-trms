<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/** Audit Log viewer (Owner only). Read-only: there are no routes to change or delete an entry. */
class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request): View
    {
        $filters = $request->validated();
        $action = $filters['action'] ?? null;

        $entries = AuditLog::with('user')
            ->when($filters['user'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            // An action type also matches its bracketed variants, e.g. "Payment voided" = "Payment voided (ACK-…)".
            ->when($action, fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('action', $action)->orWhere('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $action).' (%')))
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('logged_at', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->whereDate('logged_at', '<=', $d))
            ->orderByDesc('logged_at')->orderByDesc('log_id')
            ->paginate(25)->withQueryString();

        return view('audit-log.index', [
            'entries' => $entries,
            'filters' => $filters,
            'users' => User::orderBy('role')->orderBy('full_name')->get(),
            'actions' => AuditLog::distinct()->pluck('action')->map(fn ($a) => AuditLog::actionType($a))->unique()->sort()->values(),
            'tableLabels' => AuditLog::TABLE_LABELS,
        ]);
    }
}
