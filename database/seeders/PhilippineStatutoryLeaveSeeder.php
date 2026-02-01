<?php

namespace Database\Seeders;

use App\Enums\AccrualMethod;
use App\Enums\GenderRestriction;
use App\Enums\LeaveCategory;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Seeder for Philippine statutory leave types.
 *
 * Seeds the standard Philippine statutory leaves as defined by law:
 * - Service Incentive Leave (SIL) - Labor Code Art. 95
 * - Maternity Leave - RA 11210
 * - Paternity Leave - RA 8187
 * - Solo Parent Leave - RA 8972
 * - VAWC Leave - RA 9262
 * - Special Leave for Women - RA 9710
 */
class PhilippineStatutoryLeaveSeeder extends Seeder
{
    /**
     * Philippine statutory leave types configuration.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $statutoryLeaves = [
        [
            'name' => 'Service Incentive Leave',
            'code' => 'SIL',
            'description' => 'Annual service incentive leave for employees who have rendered at least one year of service.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => 5,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => true,
            'cash_conversion_rate' => 1.0,
            'max_convertible_days' => 5,
            'min_tenure_months' => 12,
            'gender_restriction' => null,
            'requires_attachment' => false,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'Labor Code Art. 95',
        ],
        [
            'name' => 'Maternity Leave',
            'code' => 'MAT',
            'description' => 'Expanded maternity leave for female employees who give birth or suffer miscarriage.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 105,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'min_tenure_months' => null,
            'gender_restriction' => GenderRestriction::Female,
            'requires_attachment' => true,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'RA 11210',
        ],
        [
            'name' => 'Paternity Leave',
            'code' => 'PAT',
            'description' => 'Leave for married male employees whose legitimate spouse has delivered a child or suffered a miscarriage.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 7,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'min_tenure_months' => null,
            'gender_restriction' => GenderRestriction::Male,
            'requires_attachment' => true,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'RA 8187',
        ],
        [
            'name' => 'Solo Parent Leave',
            'code' => 'SPL',
            'description' => 'Parental leave for employees who are solo parents as defined by RA 8972.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => 7,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'min_tenure_months' => null,
            'gender_restriction' => null,
            'requires_attachment' => true,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'RA 8972',
        ],
        [
            'name' => 'VAWC Leave',
            'code' => 'VAWC',
            'description' => 'Leave for women employees who are victims of violence against women and their children.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 10,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'min_tenure_months' => null,
            'gender_restriction' => GenderRestriction::Female,
            'requires_attachment' => true,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'RA 9262',
        ],
        [
            'name' => 'Special Leave for Women',
            'code' => 'SLW',
            'description' => 'Special leave for women who undergo surgery due to gynecological disorders.',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 60,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'min_tenure_months' => null,
            'gender_restriction' => GenderRestriction::Female,
            'requires_attachment' => true,
            'requires_approval' => true,
            'is_statutory' => true,
            'statutory_reference' => 'RA 9710',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->statutoryLeaves as $leaveData) {
            LeaveType::firstOrCreate(
                ['code' => $leaveData['code']],
                $leaveData
            );
        }
    }

    /**
     * Get the count of statutory leave types.
     */
    public function getStatutoryLeavesCount(): int
    {
        return count($this->statutoryLeaves);
    }

    /**
     * Get the array of statutory leave codes.
     *
     * @return array<string>
     */
    public function getStatutoryLeaveCodes(): array
    {
        return array_column($this->statutoryLeaves, 'code');
    }
}
