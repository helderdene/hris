<?php

namespace App\Http\Controllers;

use App\Enums\ContributionType;
use App\Models\PagibigContributionTable;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionTable;
use App\Models\WithholdingTaxTable;
use Inertia\Inertia;
use Inertia\Response;

class ContributionController extends Controller
{
    /**
     * Display the SSS contribution tables index page.
     */
    public function sssIndex(): Response
    {
        $tables = SssContributionTable::query()
            ->with(['brackets', 'creator'])
            ->orderByDesc('effective_from')
            ->get();

        return Inertia::render('Contributions/Sss/Index', [
            'sssTables' => $tables,
        ]);
    }

    /**
     * Display the PhilHealth contribution tables index page.
     */
    public function philhealthIndex(): Response
    {
        $tables = PhilhealthContributionTable::query()
            ->with('creator')
            ->orderByDesc('effective_from')
            ->get();

        return Inertia::render('Contributions/Philhealth/Index', [
            'philhealthTables' => $tables,
        ]);
    }

    /**
     * Display the Pag-IBIG contribution tables index page.
     */
    public function pagibigIndex(): Response
    {
        $tables = PagibigContributionTable::query()
            ->with(['tiers', 'creator'])
            ->orderByDesc('effective_from')
            ->get();

        return Inertia::render('Contributions/Pagibig/Index', [
            'pagibigTables' => $tables,
        ]);
    }

    /**
     * Display the withholding tax tables index page.
     */
    public function taxIndex(): Response
    {
        $tables = WithholdingTaxTable::query()
            ->with(['brackets'])
            ->orderByDesc('effective_from')
            ->orderBy('pay_period')
            ->get();

        return Inertia::render('Contributions/Tax/Index', [
            'taxTables' => $tables,
        ]);
    }

    /**
     * Display the contribution calculator page.
     */
    public function calculator(): Response
    {
        $hasSss = SssContributionTable::current() !== null;
        $hasPhilhealth = PhilhealthContributionTable::current() !== null;
        $hasPagibig = PagibigContributionTable::current() !== null;
        $hasTax = WithholdingTaxTable::current('monthly') !== null;

        return Inertia::render('Contributions/Calculator', [
            'contributionTypes' => ContributionType::options(),
            'hasAllTables' => $hasSss && $hasPhilhealth && $hasPagibig,
            'tableStatus' => [
                'sss' => $hasSss,
                'philhealth' => $hasPhilhealth,
                'pagibig' => $hasPagibig,
                'tax' => $hasTax,
            ],
        ]);
    }
}
