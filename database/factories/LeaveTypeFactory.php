<?php

namespace Database\Factories;

use App\Enums\AccrualMethod;
use App\Enums\GenderRestriction;
use App\Enums\LeaveCategory;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LeaveType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Vacation Leave',
                'Sick Leave',
                'Personal Leave',
                'Emergency Leave',
                'Birthday Leave',
            ]),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->optional()->sentence(),
            'leave_category' => LeaveCategory::Company,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => fake()->randomElement([5, 7, 10, 12, 15]),
            'monthly_accrual_rate' => null,
            'tenure_brackets' => null,
            'allow_carry_over' => fake()->boolean(30),
            'max_carry_over_days' => null,
            'carry_over_expiry_months' => null,
            'is_convertible_to_cash' => fake()->boolean(20),
            'cash_conversion_rate' => null,
            'max_convertible_days' => null,
            'min_tenure_months' => null,
            'eligible_employment_types' => null,
            'gender_restriction' => null,
            'requires_attachment' => fake()->boolean(30),
            'requires_approval' => true,
            'max_consecutive_days' => null,
            'min_days_advance_notice' => fake()->optional()->randomElement([1, 3, 5, 7]),
            'is_statutory' => false,
            'statutory_reference' => null,
            'is_template' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that this is a statutory leave type.
     */
    public function statutory(): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_category' => LeaveCategory::Statutory,
            'is_statutory' => true,
        ]);
    }

    /**
     * Create Service Incentive Leave (SIL).
     */
    public function serviceIncentiveLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Service Incentive Leave',
            'code' => 'SIL',
            'description' => 'Annual service incentive leave under Labor Code Art. 95',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => 5,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => true,
            'cash_conversion_rate' => 1.0000,
            'max_convertible_days' => 5,
            'min_tenure_months' => 12,
            'is_statutory' => true,
            'statutory_reference' => 'Labor Code Art. 95',
            'requires_attachment' => false,
            'requires_approval' => true,
        ]);
    }

    /**
     * Create Maternity Leave.
     */
    public function maternityLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Maternity Leave',
            'code' => 'MAT',
            'description' => 'Expanded maternity leave for female employees under RA 11210',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 105,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'gender_restriction' => GenderRestriction::Female,
            'is_statutory' => true,
            'statutory_reference' => 'RA 11210',
            'requires_attachment' => true,
            'requires_approval' => true,
        ]);
    }

    /**
     * Create Paternity Leave.
     */
    public function paternityLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Paternity Leave',
            'code' => 'PAT',
            'description' => 'Paternity leave for male employees under RA 8187',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 7,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'gender_restriction' => GenderRestriction::Male,
            'is_statutory' => true,
            'statutory_reference' => 'RA 8187',
            'requires_attachment' => true,
            'requires_approval' => true,
        ]);
    }

    /**
     * Create Solo Parent Leave.
     */
    public function soloParentLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Solo Parent Leave',
            'code' => 'SPL',
            'description' => 'Parental leave for solo parents under RA 8972',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => 7,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'is_statutory' => true,
            'statutory_reference' => 'RA 8972',
            'requires_attachment' => true,
            'requires_approval' => true,
        ]);
    }

    /**
     * Create VAWC Leave.
     */
    public function vawcLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VAWC Leave',
            'code' => 'VAWC',
            'description' => 'Leave for victims of violence against women and children under RA 9262',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 10,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'gender_restriction' => GenderRestriction::Female,
            'is_statutory' => true,
            'statutory_reference' => 'RA 9262',
            'requires_attachment' => true,
            'requires_approval' => true,
        ]);
    }

    /**
     * Create Special Leave for Women (Gynecological).
     */
    public function specialLeaveForWomen(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Special Leave for Women',
            'code' => 'SLW',
            'description' => 'Special leave for women who undergo surgery due to gynecological disorders under RA 9710',
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::None,
            'default_days_per_year' => 60,
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'gender_restriction' => GenderRestriction::Female,
            'is_statutory' => true,
            'statutory_reference' => 'RA 9710',
            'requires_attachment' => true,
            'requires_approval' => true,
        ]);
    }

    /**
     * Set a specific code for the leave type.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Indicate that this leave type allows carry-over.
     */
    public function withCarryOver(?float $maxDays = null, ?int $expiryMonths = null): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_carry_over' => true,
            'max_carry_over_days' => $maxDays,
            'carry_over_expiry_months' => $expiryMonths,
        ]);
    }

    /**
     * Indicate that this leave type is convertible to cash.
     */
    public function convertibleToCash(float $rate = 1.0, ?float $maxDays = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_convertible_to_cash' => true,
            'cash_conversion_rate' => $rate,
            'max_convertible_days' => $maxDays,
        ]);
    }

    /**
     * Indicate that this leave type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a company leave type.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_category' => LeaveCategory::Company,
            'is_statutory' => false,
        ]);
    }

    /**
     * Create a special leave type.
     */
    public function special(): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_category' => LeaveCategory::Special,
            'is_statutory' => false,
        ]);
    }
}
