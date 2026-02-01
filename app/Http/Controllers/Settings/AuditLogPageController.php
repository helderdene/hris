<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogPageController extends Controller
{
    /**
     * Display the audit logs settings page.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('can-view-audit-logs');

        $query = AuditLog::query()
            ->orderByDesc('created_at');

        // Apply filters
        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->input('model_type'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // Get filter options
        $modelTypes = AuditLog::getAuditableTypes();
        $modelTypeOptions = array_map(fn (string $type) => [
            'value' => $type,
            'label' => class_basename($type),
        ], $modelTypes);

        // Get users who have made changes
        $userIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $users = User::whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->toArray();

        return Inertia::render('settings/AuditLogs/Index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => [
                'model_type' => $request->input('model_type'),
                'action' => $request->input('action'),
                'user_id' => $request->input('user_id'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'modelTypes' => $modelTypeOptions,
            'actions' => AuditAction::options(),
            'users' => $users,
        ]);
    }
}
