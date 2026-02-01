<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as DompdfWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Service for generating BIR 2316 certificates using the official Excel template.
 *
 * This service loads the official BIR Form 2316 (September 2021 ENCS) template,
 * fills in employee and company data, and exports as Excel or PDF.
 *
 * Template structure (based on analysis):
 * - Columns A-T: Left panel (Part I, II, III, IVA)
 * - Columns U-AN: Right panel (Part IV-B)
 * - Part IVA amounts: Column M-T area (rows 61-80)
 * - Part IV-B amounts: Column AH-AN area (rows 16-77)
 */
class Bir2316TemplateService
{
    /**
     * Path to the official BIR 2316 Excel template.
     */
    protected string $templatePath;

    /**
     * Cell mappings for Part IV-B income amounts (items 29-52).
     * Maps item number to the row where the amount should be placed.
     * Amount values are entered in column AH.
     *
     * @var array<int, int>
     */
    protected array $partIVBRows = [
        // A. NON-TAXABLE/EXEMPT COMPENSATION INCOME
        29 => 16,  // Basic Salary (exempt P250,000 & below or MWE)
        30 => 18,  // Holiday Pay (MWE)
        31 => 20,  // Overtime Pay (MWE)
        32 => 23,  // Night Shift Differential (MWE)
        33 => 26,  // Hazard Pay (MWE)
        34 => 28,  // 13th Month Pay & Other Benefits (up to P90,000)
        35 => 30,  // De Minimis Benefits
        36 => 33,  // SSS, GSIS, PHIC, Pag-IBIG Contributions
        37 => 36,  // Salaries and Other Forms of Compensation
        38 => 39,  // Total Non-Taxable/Exempt Compensation

        // B. TAXABLE COMPENSATION INCOME - REGULAR
        39 => 43,  // Basic Salary
        40 => 45,  // Representation
        41 => 47,  // Transportation
        42 => 50,  // Cost of Living Allowance (COLA)
        43 => 52,  // Fixed Housing Allowance
        44 => 54,  // Others (specify)

        // SUPPLEMENTARY
        45 => 60,  // Commission
        46 => 62,  // Profit Sharing
        47 => 64,  // Fees Including Director's Fees
        48 => 66,  // Taxable 13th Month Benefits
        49 => 68,  // Hazard Pay
        50 => 70,  // Overtime Pay
        51 => 72,  // Others (specify)
        52 => 77,  // Total Taxable Compensation Income
    ];

    /**
     * Cell mappings for Part IVA Summary amounts (items 19-28).
     * Amount values are entered in column M (merged M-T).
     *
     * @var array<int, int>
     */
    protected array $partIVARows = [
        19 => 61,  // Gross Compensation Income from Present Employer
        20 => 63,  // Less: Total Non-Taxable/Exempt Compensation
        21 => 65,  // Taxable Compensation Income from Present Employer
        22 => 67,  // Add: Taxable Compensation Income from Previous Employer
        23 => 69,  // Gross Taxable Compensation Income
        24 => 71,  // Tax Due
        25 => 73,  // Amount of Taxes Withheld (25A Present Employer)
        26 => 77,  // Total Amount of Taxes Withheld as adjusted
        27 => 79,  // 5% Tax Credit (PERA Act of 2008)
        28 => 80,  // Total Taxes Withheld
    ];

    public function __construct()
    {
        $this->templatePath = base_path('docs/2316 Sep 2021 ENCS_Final_corrected.xlsx');
    }

    /**
     * Fill the template with employee and company data.
     *
     * @param  array<string, mixed>  $employeeData
     * @param  array<string, mixed>  $companyData
     */
    public function fillTemplate(array $employeeData, array $companyData): Spreadsheet
    {
        $spreadsheet = IOFactory::load($this->templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Fill tax year (Item 1)
        $this->fillTaxYear($sheet, $employeeData['tax_year'] ?? (int) date('Y'));

        // Fill employee information (Part I - Items 3-11)
        $this->mapEmployeeToTemplate($sheet, $employeeData);

        // Fill employer information (Part II - Items 12-15)
        $this->mapCompanyToTemplate($sheet, $companyData);

        // Fill Part IVA summary (Items 19-28)
        $this->mapPartIVA($sheet, $employeeData);

        // Fill Part IV-B details (Items 29-52)
        $this->mapPartIVB($sheet, $employeeData);

        return $spreadsheet;
    }

    /**
     * Export filled spreadsheet as Excel file.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function exportAsExcel(Spreadsheet $spreadsheet, string $filename): array
    {
        $writer = new Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'bir_2316_');
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Export filled spreadsheet as PDF file.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function exportAsPdf(Spreadsheet $spreadsheet, string $filename): array
    {
        // Register the Dompdf PDF writer
        IOFactory::registerWriter('Pdf', DompdfWriter::class);

        $writer = IOFactory::createWriter($spreadsheet, 'Pdf');

        // Configure PDF settings
        $writer->setSheetIndex(0);

        $tempFile = tempnam(sys_get_temp_dir(), 'bir_2316_').'.pdf';
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'application/pdf',
        ];
    }

    /**
     * Fill multiple employees into separate sheets and export as single Excel file.
     *
     * @param  array<array<string, mixed>>  $employeesData
     * @param  array<string, mixed>  $companyData
     * @return array{content: string, filename: string, contentType: string}
     */
    public function exportBatchAsExcel(array $employeesData, array $companyData, int $year): array
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0); // Remove default sheet

        foreach ($employeesData as $index => $employeeData) {
            // Load template for each employee
            $templateSpreadsheet = IOFactory::load($this->templatePath);
            $templateSheet = $templateSpreadsheet->getActiveSheet();

            // Create sheet name from employee name (max 31 chars for Excel)
            $sheetName = substr($employeeData['last_name'].'_'.$employeeData['first_name'], 0, 31);
            $sheetName = preg_replace('/[\\\\\\/?*\\[\\]:]+/', '_', $sheetName);
            $templateSheet->setTitle($sheetName);

            // Fill the template
            $this->fillTaxYear($templateSheet, $year);
            $this->mapEmployeeToTemplate($templateSheet, $employeeData);
            $this->mapCompanyToTemplate($templateSheet, $companyData);
            $this->mapPartIVA($templateSheet, $employeeData);
            $this->mapPartIVB($templateSheet, $employeeData);

            // Copy sheet to output spreadsheet
            $spreadsheet->addExternalSheet($templateSheet);
        }

        $filename = sprintf('bir_2316_batch_%d.xlsx', $year);

        return $this->exportAsExcel($spreadsheet, $filename);
    }

    /**
     * Fill the tax year in the template.
     * Item 1: For the Year (YYYY) - Row 11
     */
    protected function fillTaxYear(Worksheet $sheet, int $year): void
    {
        // Year field is in the area after "For the Year" label
        // Based on template, year digits go in cells around C11-F11
        $this->setCellWithWhiteBackground($sheet, 'C11', $year);
    }

    /**
     * Map employee personal data to the template (Part I - Items 3-11).
     *
     * @param  array<string, mixed>  $employee
     */
    protected function mapEmployeeToTemplate(Worksheet $sheet, array $employee): void
    {
        // Item 3: Employee's TIN - Row 14
        // TIN format: XXX-XXX-XXX-XXX (12 digits with dashes)
        if (! empty($employee['tin'])) {
            $tin = preg_replace('/[^0-9]/', '', $employee['tin']);
            $this->setCellWithWhiteBackground($sheet, 'C14', $this->formatTinForTemplate($tin));
        }

        // Item 4: Employee's Name - Row 16-17
        // Format: Last Name, First Name, Middle Name
        $fullName = '';
        if (! empty($employee['last_name'])) {
            $fullName = strtoupper($employee['last_name']);
        }
        if (! empty($employee['first_name'])) {
            $fullName .= ', '.strtoupper($employee['first_name']);
        }
        if (! empty($employee['middle_name'])) {
            $fullName .= ' '.strtoupper($employee['middle_name']);
        }
        if ($fullName) {
            $this->setCellWithWhiteBackground($sheet, 'C16', $fullName);
        }

        // Item 5: RDO Code - Row 16 area (right side)
        if (! empty($employee['rdo_code'])) {
            $this->setCellWithWhiteBackground($sheet, 'Q16', $employee['rdo_code']);
        }

        // Item 6: Registered Address - Row 19
        if (! empty($employee['address'])) {
            $this->setCellWithWhiteBackground($sheet, 'C19', $employee['address']);
        }

        // Item 6A: ZIP Code - Row 19 area (right side)
        if (! empty($employee['zip_code'])) {
            $this->setCellWithWhiteBackground($sheet, 'Q19', $employee['zip_code']);
        }

        // Item 6B: Local Home Address - Row 22
        if (! empty($employee['local_address'])) {
            $this->setCellWithWhiteBackground($sheet, 'C22', $employee['local_address']);
        }

        // Item 6C: ZIP Code for local address - Row 22 area
        if (! empty($employee['local_zip_code'])) {
            $this->setCellWithWhiteBackground($sheet, 'Q22', $employee['local_zip_code']);
        }

        // Item 6D: Foreign Address - Row 26
        if (! empty($employee['foreign_address'])) {
            $this->setCellWithWhiteBackground($sheet, 'C26', $employee['foreign_address']);
        }

        // Item 7: Date of Birth - Row 29 (format: MM/DD/YYYY)
        if (! empty($employee['date_of_birth'])) {
            $dob = $employee['date_of_birth'];
            if (is_string($dob)) {
                $dob = \Carbon\Carbon::parse($dob)->format('m/d/Y');
            }
            $this->setCellWithWhiteBackground($sheet, 'C29', $dob);
        }

        // Item 8: Contact Number - Row 29 area (right side)
        if (! empty($employee['telephone']) || ! empty($employee['contact_number'])) {
            $contact = $employee['telephone'] ?? $employee['contact_number'];
            $this->setCellWithWhiteBackground($sheet, 'L29', $contact);
        }

        // Item 9: Statutory Minimum Wage rate per day - Row 32
        if (! empty($employee['min_wage_rate_day'])) {
            $this->setCellWithWhiteBackground($sheet, 'C32', $employee['min_wage_rate_day']);
        }

        // Item 10: Statutory Minimum Wage rate per month - Row 35
        if (! empty($employee['min_wage_rate_month'])) {
            $this->setCellWithWhiteBackground($sheet, 'C35', $employee['min_wage_rate_month']);
        }

        // Item 11: Minimum Wage Earner checkbox - Row 38
        if (! empty($employee['is_minimum_wage_earner'])) {
            $this->setCellWithWhiteBackground($sheet, 'B38', 'X');
        }
    }

    /**
     * Map company/employer data to the template (Part II - Items 12-15).
     *
     * @param  array<string, mixed>  $company
     */
    protected function mapCompanyToTemplate(Worksheet $sheet, array $company): void
    {
        // Item 12: Employer's TIN - Row 41
        if (! empty($company['tin'])) {
            $tin = preg_replace('/[^0-9]/', '', $company['tin']);
            $this->setCellWithWhiteBackground($sheet, 'C41', $this->formatTinForTemplate($tin));
        }

        // Item 13: Employer's Name - Row 43
        if (! empty($company['name'])) {
            $this->setCellWithWhiteBackground($sheet, 'C43', strtoupper($company['name']));
        }

        // Item 14: Registered Address - Row 46
        if (! empty($company['address'])) {
            $this->setCellWithWhiteBackground($sheet, 'C46', $company['address']);
        }

        // Item 14A: ZIP Code - Row 46 area
        if (! empty($company['zip_code'])) {
            $this->setCellWithWhiteBackground($sheet, 'Q46', $company['zip_code']);
        }

        // Item 15: Type of Employer - Row 49
        // Main Employer checkbox
        $this->setCellWithWhiteBackground($sheet, 'H49', 'X');
    }

    /**
     * Map Part IVA summary amounts (Items 19-28).
     *
     * @param  array<string, mixed>  $employee
     */
    protected function mapPartIVA(Worksheet $sheet, array $employee): void
    {
        // Part IVA amounts go in column M (rows 61-80)
        $amountColumn = 'M';

        // Item 19: Gross Compensation Income from Present Employer
        if (isset($employee['gross_compensation'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[19], $employee['gross_compensation']);
        }

        // Item 20: Less: Total Non-Taxable/Exempt Compensation Income
        if (isset($employee['total_non_taxable'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[20], $employee['total_non_taxable']);
        }

        // Item 21: Taxable Compensation Income from Present Employer
        if (isset($employee['taxable_compensation'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[21], $employee['taxable_compensation']);
        }

        // Item 22: Add: Taxable Compensation Income from Previous Employer
        if (isset($employee['previous_employer_income'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[22], $employee['previous_employer_income']);
        }

        // Item 23: Gross Taxable Compensation Income (Sum of Items 21 and 22)
        $grossTaxable = ($employee['taxable_compensation'] ?? 0) + ($employee['previous_employer_income'] ?? 0);
        if ($grossTaxable > 0) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[23], $grossTaxable);
        }

        // Item 24: Tax Due
        if (isset($employee['tax_due'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[24], $employee['tax_due']);
        }

        // Item 25: Amount of Taxes Withheld (25A Present Employer)
        if (isset($employee['withholding_tax'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[25], $employee['withholding_tax']);
        }

        // Item 26: Total Amount of Taxes Withheld as adjusted
        if (isset($employee['withholding_tax'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[26], $employee['withholding_tax']);
        }

        // Item 27: 5% Tax Credit (PERA Act of 2008)
        if (isset($employee['pera_tax_credit'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[27], $employee['pera_tax_credit']);
        }

        // Item 28: Total Taxes Withheld (Sum of Items 26 and 27)
        $totalTaxWithheld = ($employee['withholding_tax'] ?? 0) + ($employee['pera_tax_credit'] ?? 0);
        if ($totalTaxWithheld > 0 || isset($employee['withholding_tax'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVARows[28], $totalTaxWithheld);
        }
    }

    /**
     * Map Part IV-B income details (Items 29-52).
     *
     * @param  array<string, mixed>  $employee
     */
    protected function mapPartIVB(Worksheet $sheet, array $employee): void
    {
        // Part IV-B amounts go in column AH
        $amountColumn = 'AH';

        // A. NON-TAXABLE/EXEMPT COMPENSATION INCOME
        // Item 29: Basic Salary (exempt portion)
        if (isset($employee['non_taxable_basic_salary'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[29], $employee['non_taxable_basic_salary']);
        }

        // Item 30: Holiday Pay (MWE)
        if (isset($employee['holiday_pay_mwe'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[30], $employee['holiday_pay_mwe']);
        }

        // Item 31: Overtime Pay (MWE)
        if (isset($employee['overtime_pay_mwe'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[31], $employee['overtime_pay_mwe']);
        }

        // Item 32: Night Shift Differential (MWE)
        if (isset($employee['night_differential_mwe'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[32], $employee['night_differential_mwe']);
        }

        // Item 33: Hazard Pay (MWE)
        if (isset($employee['hazard_pay_mwe'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[33], $employee['hazard_pay_mwe']);
        }

        // Item 34: 13th Month Pay & Other Benefits (up to P90,000)
        $thirteenthMonth = $employee['non_taxable_13th_month'] ?? $employee['thirteenth_month_pay'] ?? null;
        if ($thirteenthMonth !== null) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[34], min($thirteenthMonth, 90000));
        }

        // Item 35: De Minimis Benefits
        if (isset($employee['de_minimis'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[35], $employee['de_minimis']);
        }

        // Item 36: SSS, GSIS, PHIC, Pag-IBIG Contributions
        $totalContributions = $employee['total_contributions'] ?? (
            ($employee['sss_contribution'] ?? 0) +
            ($employee['philhealth_contribution'] ?? 0) +
            ($employee['pagibig_contribution'] ?? 0)
        );
        if ($totalContributions > 0) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[36], $totalContributions);
        }

        // Item 37: Salaries and Other Forms of Compensation (non-taxable)
        if (isset($employee['other_non_taxable'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[37], $employee['other_non_taxable']);
        }

        // Item 38: Total Non-Taxable/Exempt Compensation (Sum of Items 29-37)
        if (isset($employee['total_non_taxable'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[38], $employee['total_non_taxable']);
        }

        // B. TAXABLE COMPENSATION INCOME - REGULAR
        // Item 39: Basic Salary (taxable)
        if (isset($employee['basic_salary'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[39], $employee['basic_salary']);
        }

        // Item 40: Representation
        if (isset($employee['representation'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[40], $employee['representation']);
        }

        // Item 41: Transportation
        if (isset($employee['transportation'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[41], $employee['transportation']);
        }

        // Item 42: COLA
        if (isset($employee['cola'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[42], $employee['cola']);
        }

        // Item 43: Fixed Housing Allowance
        if (isset($employee['housing_allowance'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[43], $employee['housing_allowance']);
        }

        // Item 44: Others
        if (isset($employee['other_taxable_regular'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[44], $employee['other_taxable_regular']);
        }

        // SUPPLEMENTARY
        // Item 45: Commission
        if (isset($employee['commission'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[45], $employee['commission']);
        }

        // Item 46: Profit Sharing
        if (isset($employee['profit_sharing'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[46], $employee['profit_sharing']);
        }

        // Item 47: Fees Including Director's Fees
        if (isset($employee['directors_fees'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[47], $employee['directors_fees']);
        }

        // Item 48: Taxable 13th Month Benefits (excess over P90,000)
        if (isset($employee['taxable_13th_month'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[48], $employee['taxable_13th_month']);
        }

        // Item 49: Hazard Pay (taxable)
        if (isset($employee['hazard_pay'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[49], $employee['hazard_pay']);
        }

        // Item 50: Overtime Pay (taxable)
        if (isset($employee['overtime_pay'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[50], $employee['overtime_pay']);
        }

        // Item 51: Others (supplementary)
        if (isset($employee['other_supplementary'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[51], $employee['other_supplementary']);
        }

        // Item 52: Total Taxable Compensation Income (Sum of Items 39-51B)
        if (isset($employee['taxable_compensation'])) {
            $this->setCellWithWhiteBackground($sheet, $amountColumn.$this->partIVBRows[52], $employee['taxable_compensation']);
        }
    }

    /**
     * Format TIN for template display.
     * Converts 12-digit TIN to XXX-XXX-XXX-XXX format.
     */
    protected function formatTinForTemplate(string $tin): string
    {
        $tin = preg_replace('/[^0-9]/', '', $tin);
        if (strlen($tin) >= 9) {
            return substr($tin, 0, 3).'-'.substr($tin, 3, 3).'-'.substr($tin, 6, 3).
                   (strlen($tin) > 9 ? '-'.substr($tin, 9) : '');
        }

        return $tin;
    }

    /**
     * Get the template path.
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    /**
     * Check if the template file exists.
     */
    public function templateExists(): bool
    {
        return file_exists($this->templatePath);
    }

    /**
     * Set white background on a cell or range.
     */
    protected function setWhiteBackground(Worksheet $sheet, string $cellOrRange): void
    {
        $sheet->getStyle($cellOrRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(Color::COLOR_WHITE);
    }

    /**
     * Set cell value with white background.
     *
     * @param  mixed  $value
     */
    protected function setCellWithWhiteBackground(Worksheet $sheet, string $cell, $value): void
    {
        $sheet->setCellValue($cell, $value);
        $this->setWhiteBackground($sheet, $cell);
    }
}
