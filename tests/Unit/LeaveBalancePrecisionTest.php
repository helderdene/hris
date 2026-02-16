<?php

use App\Models\LeaveBalance;

it('rounds available balance to 2 decimal places', function () {
    $balance = new LeaveBalance;
    $balance->setRawAttributes([
        'brought_forward' => '10.10',
        'earned' => '15.30',
        'adjustments' => '0.00',
        'used' => '3.70',
        'pending' => '0.00',
        'expired' => '0.00',
    ]);

    expect($balance->available)->toBe(21.7);
});

it('avoids floating point imprecision in available balance', function () {
    $balance = new LeaveBalance;
    $balance->setRawAttributes([
        'brought_forward' => '50.10',
        'earned' => '100.30',
        'adjustments' => '5.50',
        'used' => '12.20',
        'pending' => '3.00',
        'expired' => '1.00',
    ]);

    // Without rounding, this would produce 139.70000000000002
    expect($balance->available)->toBe(139.7);
});

it('rounds total credits to 2 decimal places', function () {
    $balance = new LeaveBalance;
    $balance->setRawAttributes([
        'brought_forward' => '50.10',
        'earned' => '100.30',
        'adjustments' => '5.50',
        'expired' => '1.00',
    ]);

    expect($balance->total_credits)->toBe(154.9);
});
