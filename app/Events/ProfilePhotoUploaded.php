<?php

namespace App\Events;

use App\Models\Document;
use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an employee's profile photo is uploaded.
 *
 * Triggers automatic synchronization of the photo to biometric devices.
 */
class ProfilePhotoUploaded
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Employee $employee,
        public Document $document
    ) {}
}
