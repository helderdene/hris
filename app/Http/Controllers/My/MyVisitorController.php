<?php

namespace App\Http\Controllers\My;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VisitorVisitResource;
use App\Models\Employee;
use App\Models\VisitorVisit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyVisitorController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $pending = [];
        $history = [];
        $pendingCount = 0;

        if ($employee) {
            $pendingVisits = VisitorVisit::query()
                ->forHost($employee->id)
                ->where('status', VisitStatus::PendingApproval)
                ->whereNull('host_approved_at')
                ->with(['visitor', 'workLocation'])
                ->orderByDesc('created_at')
                ->get();

            $pending = VisitorVisitResource::collection($pendingVisits);
            $pendingCount = $pendingVisits->count();

            $historyVisits = VisitorVisit::query()
                ->forHost($employee->id)
                ->where(function ($q) {
                    $q->whereNotNull('host_approved_at')
                        ->orWhereNot('status', VisitStatus::PendingApproval);
                })
                ->with(['visitor', 'workLocation'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $history = VisitorVisitResource::collection($historyVisits);
        }

        return Inertia::render('My/Visitors', [
            'pending' => $pending,
            'history' => $history,
            'pendingCount' => $pendingCount,
            'hasEmployeeProfile' => $employee !== null,
        ]);
    }
}
