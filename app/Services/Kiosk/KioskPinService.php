<?php

namespace App\Services\Kiosk;

use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class KioskPinService
{
    /**
     * Generate a unique numeric PIN.
     */
    public function generatePin(int $length = 4): string
    {
        $max = (int) str_repeat('9', $length);
        $min = (int) ('1'.str_repeat('0', $length - 1));

        do {
            $pin = (string) random_int($min, $max);
            $hash = $this->computeLookupHash($pin);
        } while (Employee::where('kiosk_pin_hash', $hash)->exists());

        return $pin;
    }

    /**
     * Assign a new PIN to an employee.
     *
     * Returns the plain PIN for display (one-time).
     */
    public function assignPin(Employee $employee, int $length = 4): string
    {
        $pin = $this->generatePin($length);

        $employee->update([
            'kiosk_pin' => Hash::make($pin),
            'kiosk_pin_hash' => $this->computeLookupHash($pin),
            'kiosk_pin_changed_at' => now(),
        ]);

        return $pin;
    }

    /**
     * Verify a PIN and return the matching employee.
     *
     * Uses the SHA-256 keyed hash column for O(1) lookup.
     */
    public function verifyPin(string $pin): ?Employee
    {
        $hash = $this->computeLookupHash($pin);

        return Employee::where('kiosk_pin_hash', $hash)
            ->where('employment_status', 'active')
            ->first();
    }

    /**
     * Reset an employee's PIN and return the new plain PIN.
     */
    public function resetPin(Employee $employee): string
    {
        return $this->assignPin($employee);
    }

    /**
     * Compute the SHA-256 keyed hash for fast lookup.
     */
    protected function computeLookupHash(string $pin): string
    {
        return hash('sha256', $pin.config('app.key'));
    }
}
