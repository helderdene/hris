<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EvaluationSummary $resource
 */
class EvaluationSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'performance_cycle_participant_id' => $this->resource->performance_cycle_participant_id,

            // Competency averages by source
            'self_competency_avg' => $this->resource->self_competency_avg,
            'manager_competency_avg' => $this->resource->manager_competency_avg,
            'peer_competency_avg' => $this->resource->peer_competency_avg,
            'direct_report_competency_avg' => $this->resource->direct_report_competency_avg,
            'overall_competency_avg' => $this->resource->overall_competency_avg,

            // KPI scores
            'kpi_achievement_score' => $this->resource->kpi_achievement_score,
            'manager_kpi_rating' => $this->resource->manager_kpi_rating,

            // Final calibrated scores
            'final_competency_score' => $this->resource->final_competency_score,
            'final_kpi_score' => $this->resource->final_kpi_score,
            'final_overall_score' => $this->resource->final_overall_score,
            'final_rating' => $this->resource->final_rating,
            'final_rating_label' => $this->resource->getFinalRatingLabel(),

            // Status flags
            'is_calibrated' => $this->resource->isCalibrated(),
            'is_acknowledged' => $this->resource->isAcknowledged(),

            // Calibration metadata
            'calibrated_at' => $this->resource->calibrated_at?->toISOString(),
            'calibrated_by' => $this->resource->calibrated_by,
            'calibration_notes' => $this->resource->calibration_notes,

            // Calibrator user
            'calibrator' => $this->when(
                $this->resource->relationLoaded('calibratedBy') && $this->resource->calibratedBy,
                fn () => [
                    'id' => $this->resource->calibratedBy->id,
                    'name' => $this->resource->calibratedBy->name,
                    'email' => $this->resource->calibratedBy->email,
                ]
            ),

            // Employee acknowledgement
            'employee_acknowledged_at' => $this->resource->employee_acknowledged_at?->toISOString(),
            'employee_comments' => $this->resource->employee_comments,

            // Participant
            'participant' => $this->when(
                $this->resource->relationLoaded('participant') && $this->resource->participant,
                fn () => new PerformanceCycleParticipantResource($this->resource->participant)
            ),

            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
