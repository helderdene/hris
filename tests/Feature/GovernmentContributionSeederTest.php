<?php

use App\Models\PagibigContributionTable;
use App\Models\PagibigContributionTier;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionBracket;
use App\Models\SssContributionTable;
use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxTable;
use Database\Seeders\GovernmentContributionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Government Contribution Seeder', function () {
    it('seeds all contribution tables', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        expect(SssContributionTable::query()->count())->toBe(1);
        expect(PhilhealthContributionTable::query()->count())->toBe(1);
        expect(PagibigContributionTable::query()->count())->toBe(1);
        expect(WithholdingTaxTable::query()->count())->toBe(4);
    });

    it('seeds SSS contribution table with correct rates and brackets', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        $table = SssContributionTable::query()->first();

        expect($table->effective_from->format('Y-m-d'))->toBe('2025-01-01');
        expect((float) $table->employee_rate)->toBe(0.0450);
        expect((float) $table->employer_rate)->toBe(0.0950);
        expect($table->is_active)->toBeTrue();

        $brackets = SssContributionBracket::query()->count();
        expect($brackets)->toBe(53);

        // Verify first bracket
        $first = $table->brackets()->orderBy('min_salary')->first();
        expect((float) $first->min_salary)->toBe(0.00);
        expect((float) $first->max_salary)->toBe(4249.99);
        expect((float) $first->monthly_salary_credit)->toBe(4000.00);
        expect((float) $first->employee_contribution)->toBe(180.00);
        expect((float) $first->employer_contribution)->toBe(380.00);

        // Verify last bracket (open-ended)
        $last = $table->brackets()->whereNull('max_salary')->first();
        expect((float) $last->min_salary)->toBe(29750.00);
        expect((float) $last->monthly_salary_credit)->toBe(30000.00);
        expect((float) $last->employee_contribution)->toBe(1350.00);
    });

    it('seeds PhilHealth contribution table with correct rates', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        $table = PhilhealthContributionTable::query()->first();

        expect($table->effective_from->format('Y-m-d'))->toBe('2025-01-01');
        expect((float) $table->contribution_rate)->toBe(0.0500);
        expect((float) $table->employee_share_rate)->toBe(0.5000);
        expect((float) $table->employer_share_rate)->toBe(0.5000);
        expect((float) $table->salary_floor)->toBe(10000.00);
        expect((float) $table->salary_ceiling)->toBe(100000.00);
        expect((float) $table->min_contribution)->toBe(500.00);
        expect((float) $table->max_contribution)->toBe(5000.00);
        expect($table->is_active)->toBeTrue();
    });

    it('seeds Pag-IBIG contribution table with correct tiers', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        $table = PagibigContributionTable::query()->first();

        expect($table->effective_from->format('Y-m-d'))->toBe('2025-01-01');
        expect((float) $table->max_monthly_compensation)->toBe(5000.00);
        expect($table->is_active)->toBeTrue();

        $tiers = PagibigContributionTier::query()->count();
        expect($tiers)->toBe(2);

        // Verify first tier (lower bracket)
        $firstTier = $table->tiers()->orderBy('min_salary')->first();
        expect((float) $firstTier->min_salary)->toBe(0.00);
        expect((float) $firstTier->max_salary)->toBe(1500.00);
        expect((float) $firstTier->employee_rate)->toBe(0.0100);
        expect((float) $firstTier->employer_rate)->toBe(0.0200);

        // Verify second tier (upper bracket, open-ended)
        $secondTier = $table->tiers()->whereNull('max_salary')->first();
        expect((float) $secondTier->min_salary)->toBe(1500.01);
        expect((float) $secondTier->employee_rate)->toBe(0.0200);
        expect((float) $secondTier->employer_rate)->toBe(0.0200);
    });

    it('seeds withholding tax tables for all four pay periods', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        $tables = WithholdingTaxTable::query()->get();

        expect($tables)->toHaveCount(4);
        expect($tables->pluck('pay_period')->sort()->values()->toArray())
            ->toBe(['daily', 'monthly', 'semi_monthly', 'weekly']);

        foreach ($tables as $table) {
            expect($table->effective_from->format('Y-m-d'))->toBe('2023-01-01');
            expect($table->is_active)->toBeTrue();
            expect($table->brackets()->count())->toBe(6);
        }
    });

    it('seeds semi-monthly withholding tax brackets with correct values', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();

        $table = WithholdingTaxTable::query()->where('pay_period', 'semi_monthly')->first();

        // First bracket: 0% (exempt)
        $exempt = $table->brackets()->orderBy('min_compensation')->first();
        expect((float) $exempt->min_compensation)->toBe(0.00);
        expect((float) $exempt->max_compensation)->toBe(10417.00);
        expect((float) $exempt->base_tax)->toBe(0.00);
        expect((float) $exempt->excess_rate)->toBe(0.0000);

        // Last bracket: 35% (open-ended)
        $top = $table->brackets()->whereNull('max_compensation')->first();
        expect((float) $top->min_compensation)->toBe(333333.00);
        expect((float) $top->base_tax)->toBe(91770.83);
        expect((float) $top->excess_rate)->toBe(0.3500);

        // Verify tax calculation: 50,000 semi-monthly -> bracket 25%
        // base_tax 4,270.83 + 0.25 * (50,000 - 33,333) = 4,270.83 + 4,166.75 = 8,437.58
        $tax = $table->calculateTax(50000);
        expect(round($tax, 2))->toBe(8437.58);
    });

    it('is idempotent and does not duplicate data on re-run', function () {
        $seeder = new GovernmentContributionSeeder;
        $seeder->run();
        $seeder->run();

        expect(SssContributionTable::query()->count())->toBe(1);
        expect(PhilhealthContributionTable::query()->count())->toBe(1);
        expect(PagibigContributionTable::query()->count())->toBe(1);
        expect(SssContributionBracket::query()->count())->toBe(53);
        expect(PagibigContributionTier::query()->count())->toBe(2);
        expect(WithholdingTaxTable::query()->count())->toBe(4);
        expect(WithholdingTaxBracket::query()->count())->toBe(24);
    });
});
