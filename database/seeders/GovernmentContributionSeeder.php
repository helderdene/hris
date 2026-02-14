<?php

namespace Database\Seeders;

use App\Models\PagibigContributionTable;
use App\Models\PagibigContributionTier;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionBracket;
use App\Models\SssContributionTable;
use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxTable;
use Illuminate\Database\Seeder;

class GovernmentContributionSeeder extends Seeder
{
    /**
     * Seed the government contribution tables with official 2025 rates.
     */
    public function run(): void
    {
        if (SssContributionTable::query()->doesntExist()) {
            $this->seedSssContributionTable();
        }

        if (PhilhealthContributionTable::query()->doesntExist()) {
            $this->seedPhilhealthContributionTable();
        }

        if (PagibigContributionTable::query()->doesntExist()) {
            $this->seedPagibigContributionTable();
        }

        if (WithholdingTaxTable::query()->doesntExist()) {
            $this->seedWithholdingTaxTables();
        }
    }

    /**
     * Seed the SSS contribution table with 2025 brackets.
     */
    protected function seedSssContributionTable(): void
    {
        $table = SssContributionTable::withoutEvents(fn () => SssContributionTable::create([
            'effective_from' => '2025-01-01',
            'description' => '2025 SSS Contribution Table (as of January 2025)',
            'employee_rate' => 0.0450,
            'employer_rate' => 0.0950,
            'is_active' => true,
        ]));

        // Official 2025 SSS contribution brackets
        $brackets = [
            ['min_salary' => 0, 'max_salary' => 4249.99, 'monthly_salary_credit' => 4000, 'employee_contribution' => 180, 'employer_contribution' => 380, 'total_contribution' => 560, 'ec_contribution' => 10],
            ['min_salary' => 4250, 'max_salary' => 4749.99, 'monthly_salary_credit' => 4500, 'employee_contribution' => 202.50, 'employer_contribution' => 427.50, 'total_contribution' => 630, 'ec_contribution' => 10],
            ['min_salary' => 4750, 'max_salary' => 5249.99, 'monthly_salary_credit' => 5000, 'employee_contribution' => 225, 'employer_contribution' => 475, 'total_contribution' => 700, 'ec_contribution' => 10],
            ['min_salary' => 5250, 'max_salary' => 5749.99, 'monthly_salary_credit' => 5500, 'employee_contribution' => 247.50, 'employer_contribution' => 522.50, 'total_contribution' => 770, 'ec_contribution' => 10],
            ['min_salary' => 5750, 'max_salary' => 6249.99, 'monthly_salary_credit' => 6000, 'employee_contribution' => 270, 'employer_contribution' => 570, 'total_contribution' => 840, 'ec_contribution' => 10],
            ['min_salary' => 6250, 'max_salary' => 6749.99, 'monthly_salary_credit' => 6500, 'employee_contribution' => 292.50, 'employer_contribution' => 617.50, 'total_contribution' => 910, 'ec_contribution' => 10],
            ['min_salary' => 6750, 'max_salary' => 7249.99, 'monthly_salary_credit' => 7000, 'employee_contribution' => 315, 'employer_contribution' => 665, 'total_contribution' => 980, 'ec_contribution' => 10],
            ['min_salary' => 7250, 'max_salary' => 7749.99, 'monthly_salary_credit' => 7500, 'employee_contribution' => 337.50, 'employer_contribution' => 712.50, 'total_contribution' => 1050, 'ec_contribution' => 10],
            ['min_salary' => 7750, 'max_salary' => 8249.99, 'monthly_salary_credit' => 8000, 'employee_contribution' => 360, 'employer_contribution' => 760, 'total_contribution' => 1120, 'ec_contribution' => 10],
            ['min_salary' => 8250, 'max_salary' => 8749.99, 'monthly_salary_credit' => 8500, 'employee_contribution' => 382.50, 'employer_contribution' => 807.50, 'total_contribution' => 1190, 'ec_contribution' => 10],
            ['min_salary' => 8750, 'max_salary' => 9249.99, 'monthly_salary_credit' => 9000, 'employee_contribution' => 405, 'employer_contribution' => 855, 'total_contribution' => 1260, 'ec_contribution' => 10],
            ['min_salary' => 9250, 'max_salary' => 9749.99, 'monthly_salary_credit' => 9500, 'employee_contribution' => 427.50, 'employer_contribution' => 902.50, 'total_contribution' => 1330, 'ec_contribution' => 10],
            ['min_salary' => 9750, 'max_salary' => 10249.99, 'monthly_salary_credit' => 10000, 'employee_contribution' => 450, 'employer_contribution' => 950, 'total_contribution' => 1400, 'ec_contribution' => 10],
            ['min_salary' => 10250, 'max_salary' => 10749.99, 'monthly_salary_credit' => 10500, 'employee_contribution' => 472.50, 'employer_contribution' => 997.50, 'total_contribution' => 1470, 'ec_contribution' => 10],
            ['min_salary' => 10750, 'max_salary' => 11249.99, 'monthly_salary_credit' => 11000, 'employee_contribution' => 495, 'employer_contribution' => 1045, 'total_contribution' => 1540, 'ec_contribution' => 10],
            ['min_salary' => 11250, 'max_salary' => 11749.99, 'monthly_salary_credit' => 11500, 'employee_contribution' => 517.50, 'employer_contribution' => 1092.50, 'total_contribution' => 1610, 'ec_contribution' => 10],
            ['min_salary' => 11750, 'max_salary' => 12249.99, 'monthly_salary_credit' => 12000, 'employee_contribution' => 540, 'employer_contribution' => 1140, 'total_contribution' => 1680, 'ec_contribution' => 10],
            ['min_salary' => 12250, 'max_salary' => 12749.99, 'monthly_salary_credit' => 12500, 'employee_contribution' => 562.50, 'employer_contribution' => 1187.50, 'total_contribution' => 1750, 'ec_contribution' => 10],
            ['min_salary' => 12750, 'max_salary' => 13249.99, 'monthly_salary_credit' => 13000, 'employee_contribution' => 585, 'employer_contribution' => 1235, 'total_contribution' => 1820, 'ec_contribution' => 10],
            ['min_salary' => 13250, 'max_salary' => 13749.99, 'monthly_salary_credit' => 13500, 'employee_contribution' => 607.50, 'employer_contribution' => 1282.50, 'total_contribution' => 1890, 'ec_contribution' => 10],
            ['min_salary' => 13750, 'max_salary' => 14249.99, 'monthly_salary_credit' => 14000, 'employee_contribution' => 630, 'employer_contribution' => 1330, 'total_contribution' => 1960, 'ec_contribution' => 10],
            ['min_salary' => 14250, 'max_salary' => 14749.99, 'monthly_salary_credit' => 14500, 'employee_contribution' => 652.50, 'employer_contribution' => 1377.50, 'total_contribution' => 2030, 'ec_contribution' => 10],
            ['min_salary' => 14750, 'max_salary' => 15249.99, 'monthly_salary_credit' => 15000, 'employee_contribution' => 675, 'employer_contribution' => 1425, 'total_contribution' => 2100, 'ec_contribution' => 10],
            ['min_salary' => 15250, 'max_salary' => 15749.99, 'monthly_salary_credit' => 15500, 'employee_contribution' => 697.50, 'employer_contribution' => 1472.50, 'total_contribution' => 2170, 'ec_contribution' => 30],
            ['min_salary' => 15750, 'max_salary' => 16249.99, 'monthly_salary_credit' => 16000, 'employee_contribution' => 720, 'employer_contribution' => 1520, 'total_contribution' => 2240, 'ec_contribution' => 30],
            ['min_salary' => 16250, 'max_salary' => 16749.99, 'monthly_salary_credit' => 16500, 'employee_contribution' => 742.50, 'employer_contribution' => 1567.50, 'total_contribution' => 2310, 'ec_contribution' => 30],
            ['min_salary' => 16750, 'max_salary' => 17249.99, 'monthly_salary_credit' => 17000, 'employee_contribution' => 765, 'employer_contribution' => 1615, 'total_contribution' => 2380, 'ec_contribution' => 30],
            ['min_salary' => 17250, 'max_salary' => 17749.99, 'monthly_salary_credit' => 17500, 'employee_contribution' => 787.50, 'employer_contribution' => 1662.50, 'total_contribution' => 2450, 'ec_contribution' => 30],
            ['min_salary' => 17750, 'max_salary' => 18249.99, 'monthly_salary_credit' => 18000, 'employee_contribution' => 810, 'employer_contribution' => 1710, 'total_contribution' => 2520, 'ec_contribution' => 30],
            ['min_salary' => 18250, 'max_salary' => 18749.99, 'monthly_salary_credit' => 18500, 'employee_contribution' => 832.50, 'employer_contribution' => 1757.50, 'total_contribution' => 2590, 'ec_contribution' => 30],
            ['min_salary' => 18750, 'max_salary' => 19249.99, 'monthly_salary_credit' => 19000, 'employee_contribution' => 855, 'employer_contribution' => 1805, 'total_contribution' => 2660, 'ec_contribution' => 30],
            ['min_salary' => 19250, 'max_salary' => 19749.99, 'monthly_salary_credit' => 19500, 'employee_contribution' => 877.50, 'employer_contribution' => 1852.50, 'total_contribution' => 2730, 'ec_contribution' => 30],
            ['min_salary' => 19750, 'max_salary' => 20249.99, 'monthly_salary_credit' => 20000, 'employee_contribution' => 900, 'employer_contribution' => 1900, 'total_contribution' => 2800, 'ec_contribution' => 30],
            ['min_salary' => 20250, 'max_salary' => 20749.99, 'monthly_salary_credit' => 20500, 'employee_contribution' => 922.50, 'employer_contribution' => 1947.50, 'total_contribution' => 2870, 'ec_contribution' => 30],
            ['min_salary' => 20750, 'max_salary' => 21249.99, 'monthly_salary_credit' => 21000, 'employee_contribution' => 945, 'employer_contribution' => 1995, 'total_contribution' => 2940, 'ec_contribution' => 30],
            ['min_salary' => 21250, 'max_salary' => 21749.99, 'monthly_salary_credit' => 21500, 'employee_contribution' => 967.50, 'employer_contribution' => 2042.50, 'total_contribution' => 3010, 'ec_contribution' => 30],
            ['min_salary' => 21750, 'max_salary' => 22249.99, 'monthly_salary_credit' => 22000, 'employee_contribution' => 990, 'employer_contribution' => 2090, 'total_contribution' => 3080, 'ec_contribution' => 30],
            ['min_salary' => 22250, 'max_salary' => 22749.99, 'monthly_salary_credit' => 22500, 'employee_contribution' => 1012.50, 'employer_contribution' => 2137.50, 'total_contribution' => 3150, 'ec_contribution' => 30],
            ['min_salary' => 22750, 'max_salary' => 23249.99, 'monthly_salary_credit' => 23000, 'employee_contribution' => 1035, 'employer_contribution' => 2185, 'total_contribution' => 3220, 'ec_contribution' => 30],
            ['min_salary' => 23250, 'max_salary' => 23749.99, 'monthly_salary_credit' => 23500, 'employee_contribution' => 1057.50, 'employer_contribution' => 2232.50, 'total_contribution' => 3290, 'ec_contribution' => 30],
            ['min_salary' => 23750, 'max_salary' => 24249.99, 'monthly_salary_credit' => 24000, 'employee_contribution' => 1080, 'employer_contribution' => 2280, 'total_contribution' => 3360, 'ec_contribution' => 30],
            ['min_salary' => 24250, 'max_salary' => 24749.99, 'monthly_salary_credit' => 24500, 'employee_contribution' => 1102.50, 'employer_contribution' => 2327.50, 'total_contribution' => 3430, 'ec_contribution' => 30],
            ['min_salary' => 24750, 'max_salary' => 25249.99, 'monthly_salary_credit' => 25000, 'employee_contribution' => 1125, 'employer_contribution' => 2375, 'total_contribution' => 3500, 'ec_contribution' => 30],
            ['min_salary' => 25250, 'max_salary' => 25749.99, 'monthly_salary_credit' => 25500, 'employee_contribution' => 1147.50, 'employer_contribution' => 2422.50, 'total_contribution' => 3570, 'ec_contribution' => 30],
            ['min_salary' => 25750, 'max_salary' => 26249.99, 'monthly_salary_credit' => 26000, 'employee_contribution' => 1170, 'employer_contribution' => 2470, 'total_contribution' => 3640, 'ec_contribution' => 30],
            ['min_salary' => 26250, 'max_salary' => 26749.99, 'monthly_salary_credit' => 26500, 'employee_contribution' => 1192.50, 'employer_contribution' => 2517.50, 'total_contribution' => 3710, 'ec_contribution' => 30],
            ['min_salary' => 26750, 'max_salary' => 27249.99, 'monthly_salary_credit' => 27000, 'employee_contribution' => 1215, 'employer_contribution' => 2565, 'total_contribution' => 3780, 'ec_contribution' => 30],
            ['min_salary' => 27250, 'max_salary' => 27749.99, 'monthly_salary_credit' => 27500, 'employee_contribution' => 1237.50, 'employer_contribution' => 2612.50, 'total_contribution' => 3850, 'ec_contribution' => 30],
            ['min_salary' => 27750, 'max_salary' => 28249.99, 'monthly_salary_credit' => 28000, 'employee_contribution' => 1260, 'employer_contribution' => 2660, 'total_contribution' => 3920, 'ec_contribution' => 30],
            ['min_salary' => 28250, 'max_salary' => 28749.99, 'monthly_salary_credit' => 28500, 'employee_contribution' => 1282.50, 'employer_contribution' => 2707.50, 'total_contribution' => 3990, 'ec_contribution' => 30],
            ['min_salary' => 28750, 'max_salary' => 29249.99, 'monthly_salary_credit' => 29000, 'employee_contribution' => 1305, 'employer_contribution' => 2755, 'total_contribution' => 4060, 'ec_contribution' => 30],
            ['min_salary' => 29250, 'max_salary' => 29749.99, 'monthly_salary_credit' => 29500, 'employee_contribution' => 1327.50, 'employer_contribution' => 2802.50, 'total_contribution' => 4130, 'ec_contribution' => 30],
            ['min_salary' => 29750, 'max_salary' => null, 'monthly_salary_credit' => 30000, 'employee_contribution' => 1350, 'employer_contribution' => 2850, 'total_contribution' => 4200, 'ec_contribution' => 30],
        ];

        SssContributionBracket::withoutEvents(function () use ($table, $brackets) {
            foreach ($brackets as $bracket) {
                $table->brackets()->create($bracket);
            }
        });
    }

    /**
     * Seed the PhilHealth contribution table with 2025 rates.
     */
    protected function seedPhilhealthContributionTable(): void
    {
        PhilhealthContributionTable::withoutEvents(fn () => PhilhealthContributionTable::create([
            'effective_from' => '2025-01-01',
            'description' => '2025 PhilHealth Contribution Table (5% premium rate)',
            'contribution_rate' => 0.0500,
            'employee_share_rate' => 0.5000,
            'employer_share_rate' => 0.5000,
            'salary_floor' => 10000.00,
            'salary_ceiling' => 100000.00,
            'min_contribution' => 500.00,
            'max_contribution' => 5000.00,
            'is_active' => true,
        ]));
    }

    /**
     * Seed the Pag-IBIG contribution table with 2025 tiers.
     */
    protected function seedPagibigContributionTable(): void
    {
        $table = PagibigContributionTable::withoutEvents(fn () => PagibigContributionTable::create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Pag-IBIG Contribution Table',
            'max_monthly_compensation' => 5000.00,
            'is_active' => true,
        ]));

        // Official Pag-IBIG tiers
        $tiers = [
            [
                'min_salary' => 0,
                'max_salary' => 1500.00,
                'employee_rate' => 0.0100,
                'employer_rate' => 0.0200,
            ],
            [
                'min_salary' => 1500.01,
                'max_salary' => null,
                'employee_rate' => 0.0200,
                'employer_rate' => 0.0200,
            ],
        ];

        PagibigContributionTier::withoutEvents(function () use ($table, $tiers) {
            foreach ($tiers as $tier) {
                $table->tiers()->create($tier);
            }
        });
    }

    /**
     * Seed the BIR Withholding Tax tables (TRAIN Law, RA 10963, effective 2023).
     *
     * Brackets per pay period derived from annual tax table:
     *   Annual ÷ 12 (monthly), ÷ 24 (semi-monthly), ÷ 52 (weekly), ÷ 313 (daily)
     */
    protected function seedWithholdingTaxTables(): void
    {
        $payPeriods = [
            'daily' => [
                'description' => 'BIR Withholding Tax Table - Daily (TRAIN Law)',
                'brackets' => [
                    ['min_compensation' => 0, 'max_compensation' => 685, 'base_tax' => 0, 'excess_rate' => 0],
                    ['min_compensation' => 685, 'max_compensation' => 1096, 'base_tax' => 0, 'excess_rate' => 0.15],
                    ['min_compensation' => 1096, 'max_compensation' => 2192, 'base_tax' => 61.65, 'excess_rate' => 0.20],
                    ['min_compensation' => 2192, 'max_compensation' => 5479, 'base_tax' => 280.85, 'excess_rate' => 0.25],
                    ['min_compensation' => 5479, 'max_compensation' => 21918, 'base_tax' => 1102.60, 'excess_rate' => 0.30],
                    ['min_compensation' => 21918, 'max_compensation' => null, 'base_tax' => 6034.30, 'excess_rate' => 0.35],
                ],
            ],
            'weekly' => [
                'description' => 'BIR Withholding Tax Table - Weekly (TRAIN Law)',
                'brackets' => [
                    ['min_compensation' => 0, 'max_compensation' => 4808, 'base_tax' => 0, 'excess_rate' => 0],
                    ['min_compensation' => 4808, 'max_compensation' => 7692, 'base_tax' => 0, 'excess_rate' => 0.15],
                    ['min_compensation' => 7692, 'max_compensation' => 15385, 'base_tax' => 432.69, 'excess_rate' => 0.20],
                    ['min_compensation' => 15385, 'max_compensation' => 38462, 'base_tax' => 1971.15, 'excess_rate' => 0.25],
                    ['min_compensation' => 38462, 'max_compensation' => 153846, 'base_tax' => 7740.38, 'excess_rate' => 0.30],
                    ['min_compensation' => 153846, 'max_compensation' => null, 'base_tax' => 42355.77, 'excess_rate' => 0.35],
                ],
            ],
            'semi_monthly' => [
                'description' => 'BIR Withholding Tax Table - Semi-Monthly (TRAIN Law)',
                'brackets' => [
                    ['min_compensation' => 0, 'max_compensation' => 10417, 'base_tax' => 0, 'excess_rate' => 0],
                    ['min_compensation' => 10417, 'max_compensation' => 16667, 'base_tax' => 0, 'excess_rate' => 0.15],
                    ['min_compensation' => 16667, 'max_compensation' => 33333, 'base_tax' => 937.50, 'excess_rate' => 0.20],
                    ['min_compensation' => 33333, 'max_compensation' => 83333, 'base_tax' => 4270.83, 'excess_rate' => 0.25],
                    ['min_compensation' => 83333, 'max_compensation' => 333333, 'base_tax' => 16770.83, 'excess_rate' => 0.30],
                    ['min_compensation' => 333333, 'max_compensation' => null, 'base_tax' => 91770.83, 'excess_rate' => 0.35],
                ],
            ],
            'monthly' => [
                'description' => 'BIR Withholding Tax Table - Monthly (TRAIN Law)',
                'brackets' => [
                    ['min_compensation' => 0, 'max_compensation' => 20833, 'base_tax' => 0, 'excess_rate' => 0],
                    ['min_compensation' => 20833, 'max_compensation' => 33333, 'base_tax' => 0, 'excess_rate' => 0.15],
                    ['min_compensation' => 33333, 'max_compensation' => 66667, 'base_tax' => 1875.00, 'excess_rate' => 0.20],
                    ['min_compensation' => 66667, 'max_compensation' => 166667, 'base_tax' => 8541.67, 'excess_rate' => 0.25],
                    ['min_compensation' => 166667, 'max_compensation' => 666667, 'base_tax' => 33541.67, 'excess_rate' => 0.30],
                    ['min_compensation' => 666667, 'max_compensation' => null, 'base_tax' => 183541.67, 'excess_rate' => 0.35],
                ],
            ],
        ];

        foreach ($payPeriods as $payPeriod => $config) {
            $table = WithholdingTaxTable::withoutEvents(fn () => WithholdingTaxTable::create([
                'pay_period' => $payPeriod,
                'effective_from' => '2023-01-01',
                'description' => $config['description'],
                'is_active' => true,
            ]));

            WithholdingTaxBracket::withoutEvents(function () use ($table, $config) {
                foreach ($config['brackets'] as $bracket) {
                    $table->brackets()->create($bracket);
                }
            });
        }
    }
}
