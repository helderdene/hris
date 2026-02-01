<?php

namespace App\Services\Dtr;

use App\Enums\PunchType;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Processes raw attendance logs into paired IN/OUT punches.
 */
class PunchPairProcessor
{
    /**
     * Process attendance logs into time pairs.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array{pairs: array<int, array{in: AttendanceLog, out: AttendanceLog|null, type: string}>, break_pairs: array<int, array{out: AttendanceLog, in: AttendanceLog|null}>, unpaired_in: AttendanceLog|null, unpaired_out: AttendanceLog|null, first_in: Carbon|null, last_out: Carbon|null}
     */
    public function process(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [
                'pairs' => [],
                'break_pairs' => [],
                'unpaired_in' => null,
                'unpaired_out' => null,
                'first_in' => null,
                'last_out' => null,
            ];
        }

        // Sort logs by time
        $sortedLogs = $logs->sortBy('logged_at')->values();

        $pairs = [];
        $breakPairs = [];
        $currentIn = null;
        $currentBreakOut = null;
        $unpairedOut = null;
        $firstIn = null;
        $lastOut = null;

        foreach ($sortedLogs as $log) {
            $direction = $this->normalizeDirection($log->direction);
            $loggedAt = Carbon::parse($log->logged_at);

            // Track first IN
            if ($direction === PunchType::In && $firstIn === null) {
                $firstIn = $loggedAt;
            }

            // Track last OUT
            if ($direction === PunchType::Out) {
                $lastOut = $loggedAt;
            }

            if ($direction === PunchType::In) {
                // If we already have an unpaired IN, complete it with this as implicit out
                // then start a new IN
                if ($currentIn !== null) {
                    // Two consecutive INs - treat as missing OUT
                    $pairs[] = [
                        'in' => $currentIn,
                        'out' => null,
                        'type' => 'work',
                    ];
                }
                $currentIn = $log;
            } elseif ($direction === PunchType::Out) {
                if ($currentIn !== null) {
                    // Complete the pair
                    $pairs[] = [
                        'in' => $currentIn,
                        'out' => $log,
                        'type' => 'work',
                    ];
                    $currentIn = null;
                } else {
                    // OUT without IN
                    $unpairedOut = $log;
                }
            } elseif ($direction === PunchType::BreakOut) {
                // Starting a break
                if ($currentBreakOut !== null) {
                    // Previous break wasn't completed
                    $breakPairs[] = [
                        'out' => $currentBreakOut,
                        'in' => null,
                    ];
                }
                $currentBreakOut = $log;
            } elseif ($direction === PunchType::BreakIn) {
                // Returning from break
                if ($currentBreakOut !== null) {
                    $breakPairs[] = [
                        'out' => $currentBreakOut,
                        'in' => $log,
                    ];
                    $currentBreakOut = null;
                } else {
                    // BreakIn without BreakOut - create a pair with null out
                    $breakPairs[] = [
                        'out' => null,
                        'in' => $log,
                    ];
                }
            }
        }

        // Handle unpaired IN at the end
        $unpairedIn = null;
        if ($currentIn !== null) {
            $unpairedIn = $currentIn;
            $pairs[] = [
                'in' => $currentIn,
                'out' => null,
                'type' => 'work',
            ];
        }

        // Handle unpaired break out at the end
        if ($currentBreakOut !== null) {
            $breakPairs[] = [
                'out' => $currentBreakOut,
                'in' => null,
            ];
        }

        return [
            'pairs' => $pairs,
            'break_pairs' => $breakPairs,
            'unpaired_in' => $unpairedIn,
            'unpaired_out' => $unpairedOut,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
        ];
    }

    /**
     * Calculate total work minutes from punch pairs.
     *
     * @param  array<int, array{in: AttendanceLog, out: AttendanceLog|null}>  $pairs
     */
    public function calculateTotalWorkMinutes(array $pairs): int
    {
        $totalMinutes = 0;

        foreach ($pairs as $pair) {
            if ($pair['in'] === null || $pair['out'] === null) {
                continue;
            }

            $inTime = Carbon::parse($pair['in']->logged_at);
            $outTime = Carbon::parse($pair['out']->logged_at);

            $totalMinutes += $inTime->diffInMinutes($outTime);
        }

        return $totalMinutes;
    }

    /**
     * Calculate total break minutes from punch pairs.
     * Break time is the gap between an OUT and the next IN.
     *
     * @param  array<int, array{in: AttendanceLog, out: AttendanceLog|null}>  $pairs
     */
    public function calculateBreakMinutes(array $pairs): int
    {
        if (count($pairs) < 2) {
            return 0;
        }

        $breakMinutes = 0;

        for ($i = 0; $i < count($pairs) - 1; $i++) {
            $currentPair = $pairs[$i];
            $nextPair = $pairs[$i + 1];

            if ($currentPair['out'] !== null && $nextPair['in'] !== null) {
                $outTime = Carbon::parse($currentPair['out']->logged_at);
                $nextInTime = Carbon::parse($nextPair['in']->logged_at);

                $breakMinutes += $outTime->diffInMinutes($nextInTime);
            }
        }

        return $breakMinutes;
    }

    /**
     * Normalize the direction string from device to PunchType.
     */
    protected function normalizeDirection(?string $direction): ?PunchType
    {
        if ($direction === null) {
            return null;
        }

        $direction = strtolower(trim($direction));

        return match ($direction) {
            'in', 'entry', 'check-in', 'checkin', '1', 'checkIn' => PunchType::In,
            'out', 'exit', 'check-out', 'checkout', '2', 'checkOut' => PunchType::Out,
            'break_out', 'breakout', 'break-out', 'lunch_out', 'lunchout', '3' => PunchType::BreakOut,
            'break_in', 'breakin', 'break-in', 'lunch_in', 'lunchin', '4' => PunchType::BreakIn,
            default => null,
        };
    }

    /**
     * Get punch records formatted for saving to TimeRecordPunch.
     *
     * @param  array<int, array{in: AttendanceLog, out: AttendanceLog|null, type: string}>  $pairs
     * @param  array<int, array{out: AttendanceLog|null, in: AttendanceLog|null}>  $breakPairs
     * @return array<int, array{attendance_log_id: int, punch_type: PunchType, punched_at: Carbon}>
     */
    public function getPunchRecords(array $pairs, array $breakPairs = []): array
    {
        $records = [];

        foreach ($pairs as $pair) {
            if ($pair['in'] !== null) {
                $records[] = [
                    'attendance_log_id' => $pair['in']->id,
                    'punch_type' => PunchType::In,
                    'punched_at' => Carbon::parse($pair['in']->logged_at),
                ];
            }

            if ($pair['out'] !== null) {
                $records[] = [
                    'attendance_log_id' => $pair['out']->id,
                    'punch_type' => PunchType::Out,
                    'punched_at' => Carbon::parse($pair['out']->logged_at),
                ];
            }
        }

        // Add break punches
        foreach ($breakPairs as $breakPair) {
            if ($breakPair['out'] !== null) {
                $records[] = [
                    'attendance_log_id' => $breakPair['out']->id,
                    'punch_type' => PunchType::BreakOut,
                    'punched_at' => Carbon::parse($breakPair['out']->logged_at),
                ];
            }

            if ($breakPair['in'] !== null) {
                $records[] = [
                    'attendance_log_id' => $breakPair['in']->id,
                    'punch_type' => PunchType::BreakIn,
                    'punched_at' => Carbon::parse($breakPair['in']->logged_at),
                ];
            }
        }

        // Sort by punched_at to maintain chronological order
        usort($records, fn ($a, $b) => $a['punched_at']->getTimestamp() <=> $b['punched_at']->getTimestamp());

        return $records;
    }

    /**
     * Calculate actual break minutes from break pairs.
     *
     * @param  array<int, array{out: AttendanceLog|null, in: AttendanceLog|null}>  $breakPairs
     */
    public function calculateActualBreakMinutes(array $breakPairs): int
    {
        $breakMinutes = 0;

        foreach ($breakPairs as $pair) {
            if ($pair['out'] !== null && $pair['in'] !== null) {
                $outTime = Carbon::parse($pair['out']->logged_at);
                $inTime = Carbon::parse($pair['in']->logged_at);
                $breakMinutes += $outTime->diffInMinutes($inTime);
            }
        }

        return $breakMinutes;
    }
}
