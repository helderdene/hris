<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-view-audit-logs');

        $query = AuditLog::query()
            ->orderByDesc('created_at');

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->input('model_type'));
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate($request->input('per_page', 25));

        return AuditLogResource::collection($logs);
    }

    /**
     * Get filter options for the audit logs.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        Gate::authorize('can-view-audit-logs');

        // Get unique model types
        $modelTypes = AuditLog::getAuditableTypes();

        // Format model types for display
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

        return [
            'model_types' => $modelTypeOptions,
            'actions' => AuditAction::options(),
            'users' => $users,
        ];
    }
}
