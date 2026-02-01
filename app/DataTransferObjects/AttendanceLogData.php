<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Immutable data transfer object for parsed MQTT attendance messages.
 */
readonly class AttendanceLogData
{
    public function __construct(
        public string $deviceIdentifier,
        public string $devicePersonId,
        public string $deviceRecordId,
        public string $employeeCode,
        public float $confidence,
        public string $verifyStatus,
        public Carbon $loggedAt,
        public ?string $direction,
        public ?string $personName,
        public ?string $capturedPhoto,
        public ?array $rawPayload,
    ) {}
}
