<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use Illuminate\Support\Collection;

/**
 * Service for detecting duplicate candidates by email, phone, or name.
 */
class DuplicateDetectionService
{
    /**
     * Find duplicate candidates.
     *
     * @return array{exact: Collection, potential: Collection}
     */
    public function findDuplicates(
        ?string $email,
        ?string $phone,
        ?string $firstName,
        ?string $lastName,
        ?int $excludeId = null
    ): array {
        $exact = collect();
        $potential = collect();

        $baseQuery = Candidate::query();
        if ($excludeId) {
            $baseQuery->where('id', '!=', $excludeId);
        }

        // Exact matches: email OR phone
        $exactQuery = (clone $baseQuery)->where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        });

        if ($email || $phone) {
            $exact = $exactQuery->get();
        }

        // Potential matches: similar name (first 3 chars of both names)
        if ($firstName && $lastName && strlen($firstName) >= 3 && strlen($lastName) >= 3) {
            $firstPrefix = substr($firstName, 0, 3);
            $lastPrefix = substr($lastName, 0, 3);

            $potential = (clone $baseQuery)
                ->where('first_name', 'like', $firstPrefix.'%')
                ->where('last_name', 'like', $lastPrefix.'%')
                ->whereNotIn('id', $exact->pluck('id'))
                ->get();
        }

        return [
            'exact' => $exact,
            'potential' => $potential,
        ];
    }
}
