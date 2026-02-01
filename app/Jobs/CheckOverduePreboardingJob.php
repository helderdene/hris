<?php

namespace App\Jobs;

use App\Enums\PreboardingStatus;
use App\Models\PreboardingChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to mark preboarding checklists past their deadline as overdue.
 */
class CheckOverduePreboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        PreboardingChecklist::query()
            ->whereIn('status', [PreboardingStatus::Pending, PreboardingStatus::InProgress])
            ->whereNotNull('deadline')
            ->where('deadline', '<', now()->toDateString())
            ->update(['status' => PreboardingStatus::Overdue]);
    }
}
