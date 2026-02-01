<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIR Form 2316</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            line-height: 1.2;
            color: #000;
        }
        .form-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        .header-line {
            font-size: 9px;
        }
        .form-title {
            font-size: 11px;
            font-weight: bold;
            margin: 5px 0;
        }
        .form-subtitle {
            font-size: 9px;
            font-style: italic;
        }
        .form-number {
            position: absolute;
            top: 10px;
            right: 20px;
            text-align: right;
            font-size: 10px;
        }
        .form-number strong {
            font-size: 14px;
        }
        .instruction {
            font-size: 7px;
            font-style: italic;
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
            font-size: 7px;
        }
        .no-border {
            border: none !important;
        }
        .no-border-left {
            border-left: none !important;
        }
        .no-border-right {
            border-right: none !important;
        }
        .no-border-top {
            border-top: none !important;
        }
        .no-border-bottom {
            border-bottom: none !important;
        }
        .section-header {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            font-size: 8px;
            padding: 3px 5px;
        }
        .item-number {
            font-weight: bold;
            width: 20px;
            text-align: center;
            background-color: #f0f0f0;
        }
        .field-label {
            font-size: 7px;
        }
        .field-value {
            min-height: 14px;
            background-color: #ffffcc;
        }
        .amount-field {
            text-align: right;
            font-family: 'Courier New', monospace;
            background-color: #ffffcc;
        }
        .checkbox {
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            display: inline-block;
            text-align: center;
            line-height: 10px;
            font-size: 8px;
        }
        .two-column {
            display: table;
            width: 100%;
        }
        .left-column, .right-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .year-box {
            display: inline-block;
            border: 1px solid #000;
            width: 50px;
            height: 14px;
            text-align: center;
            background-color: #ffffcc;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            min-height: 30px;
            margin-top: 20px;
        }
        .signature-label {
            font-size: 7px;
            text-align: center;
        }
        .declaration {
            font-size: 7px;
            text-align: justify;
            padding: 5px;
            line-height: 1.3;
        }
        .dlc-box {
            border: 2px solid #000;
            padding: 3px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <!-- DLN Box -->
        <div style="text-align: left; margin-bottom: 5px;">
            <span style="font-size: 7px;">DLN:</span>
            <span style="border-bottom: 1px solid #000; display: inline-block; width: 150px;">&nbsp;</span>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="header-line">Republic of the Philippines</div>
            <div class="header-line">Department of Finance</div>
            <div class="header-line" style="font-weight: bold;">Bureau of Internal Revenue</div>
            <div class="form-title">Certificate of Compensation Payment/Tax Withheld</div>
            <div class="form-subtitle">For Compensation Payment With or Without Tax Withheld</div>
        </div>

        <!-- Form Number -->
        <div style="text-align: right; margin-top: -40px; margin-bottom: 20px;">
            <div style="font-size: 8px;">BIR Form No.</div>
            <div style="font-size: 16px; font-weight: bold;">2316</div>
            <div style="font-size: 7px;">September 2021 (ENCS)</div>
        </div>

        <div class="instruction">Fill in all applicable spaces. Mark all appropriate boxes with an "X".</div>

        <!-- Year and Period -->
        <table style="margin-bottom: 5px;">
            <tr>
                <td class="item-number">1</td>
                <td class="field-label" style="width: 80px;">For the Year</td>
                <td class="field-value" style="width: 60px; text-align: center;">{{ $tax_year }}</td>
                <td class="item-number">2</td>
                <td class="field-label" style="width: 80px;">For the Period</td>
                <td class="field-label">From (MM/DD)</td>
                <td class="field-value" style="width: 60px;">01/01</td>
                <td class="field-label">To (MM/DD)</td>
                <td class="field-value" style="width: 60px;">12/31</td>
            </tr>
        </table>

        <!-- Main Content - Two Column Layout -->
        <table>
            <tr>
                <!-- Left Column: Parts I, II, III, IVA -->
                <td style="width: 50%; vertical-align: top; padding: 0;">
                    <!-- Part I - Employee Information -->
                    <table>
                        <tr>
                            <td colspan="4" class="section-header">Part I - Employee Information</td>
                        </tr>
                        <tr>
                            <td class="item-number">3</td>
                            <td class="field-label">TIN</td>
                            <td colspan="2" class="field-value">{{ $employee->tin ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">4</td>
                            <td class="field-label">Employee's Name (Last Name, First Name, Middle Name)</td>
                            <td class="item-number">5</td>
                            <td class="field-label">RDO Code</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="field-value">{{ ($employee->last_name ?? '') . ', ' . ($employee->first_name ?? '') . ' ' . ($employee->middle_name ?? '') }}</td>
                            <td colspan="2" class="field-value">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="item-number">6</td>
                            <td class="field-label">Registered Address</td>
                            <td class="item-number">6A</td>
                            <td class="field-label">ZIP Code</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="field-value">{{ $employee->address ?? '' }}</td>
                            <td colspan="2" class="field-value">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="item-number">7</td>
                            <td class="field-label">Date of Birth (MM/DD/YYYY)</td>
                            <td class="item-number">8</td>
                            <td class="field-label">Contact Number</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="field-value">{{ $employee->date_of_birth ?? '' }}</td>
                            <td colspan="2" class="field-value">&nbsp;</td>
                        </tr>
                    </table>

                    <!-- Part II - Employer Information (Present) -->
                    <table style="margin-top: 3px;">
                        <tr>
                            <td colspan="4" class="section-header">Part II - Employer Information (Present)</td>
                        </tr>
                        <tr>
                            <td class="item-number">12</td>
                            <td class="field-label">TIN</td>
                            <td colspan="2" class="field-value">{{ $company['tin'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">13</td>
                            <td colspan="3" class="field-label">Employer's Name</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="field-value">{{ $company['name'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">14</td>
                            <td class="field-label">Registered Address</td>
                            <td class="item-number">14A</td>
                            <td class="field-label">ZIP Code</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="field-value">{{ $company['address'] ?? '' }}</td>
                            <td colspan="2" class="field-value">{{ $company['zip_code'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">15</td>
                            <td class="field-label">Type of Employer</td>
                            <td colspan="2" class="field-value"><span class="checkbox">X</span> Main Employer &nbsp; <span class="checkbox">&nbsp;</span> Secondary Employer</td>
                        </tr>
                    </table>

                    <!-- Part IVA - Summary -->
                    <table style="margin-top: 3px;">
                        <tr>
                            <td colspan="3" class="section-header">Part IVA - Summary</td>
                        </tr>
                        <tr>
                            <td class="item-number">19</td>
                            <td class="field-label">Gross Compensation Income from Present Employer (Sum of Items 38 and 52)</td>
                            <td class="amount-field" style="width: 80px;">{{ number_format($employee->gross_compensation ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">20</td>
                            <td class="field-label">Less: Total Non-Taxable/Exempt Compensation Income from Present Employer (From Item 38)</td>
                            <td class="amount-field">{{ number_format($employee->total_non_taxable ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">21</td>
                            <td class="field-label">Taxable Compensation Income from Present Employer (Item 19 Less Item 20)</td>
                            <td class="amount-field">{{ number_format($employee->taxable_compensation ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">22</td>
                            <td class="field-label">Add: Taxable Compensation Income from Previous Employer, if applicable</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">23</td>
                            <td class="field-label">Gross Taxable Compensation Income (Sum of Items 21 and 22)</td>
                            <td class="amount-field">{{ number_format($employee->taxable_compensation ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">24</td>
                            <td class="field-label">Tax Due</td>
                            <td class="amount-field">{{ number_format($employee->withholding_tax ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">25</td>
                            <td class="field-label">Amount of Taxes Withheld</td>
                            <td class="amount-field">&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td class="field-label" style="padding-left: 20px;">25A Present Employer</td>
                            <td class="amount-field">{{ number_format($employee->withholding_tax ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td class="field-label" style="padding-left: 20px;">25B Previous Employer, if applicable</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">26</td>
                            <td class="field-label">Total Amount of Taxes Withheld as adjusted (Sum of Items 25A and 25B)</td>
                            <td class="amount-field">{{ number_format($employee->withholding_tax ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">27</td>
                            <td class="field-label">5% Tax Credit (PERA Act of 2008)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">28</td>
                            <td class="field-label" style="font-weight: bold;">Total Taxes Withheld (Sum of Items 26 and 27)</td>
                            <td class="amount-field" style="font-weight: bold; background-color: #ffff99;">{{ number_format($employee->withholding_tax ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Right Column: Part IV-B -->
                <td style="width: 50%; vertical-align: top; padding: 0;">
                    <table>
                        <tr>
                            <td colspan="3" class="section-header">Part IV-B Details of Compensation Income & Tax Withheld from Present Employer</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="background-color: #e0e0e0; font-weight: bold; font-size: 7px;">A. NON-TAXABLE/EXEMPT COMPENSATION INCOME</td>
                        </tr>
                        <tr>
                            <td class="item-number">29</td>
                            <td class="field-label">Basic Salary (including the exempt P250,000 & below) or the Statutory Minimum Wage of the MWE</td>
                            <td class="amount-field" style="width: 80px;">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">30</td>
                            <td class="field-label">Holiday Pay (MWE)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">31</td>
                            <td class="field-label">Overtime Pay (MWE)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">32</td>
                            <td class="field-label">Night Shift Differential (MWE)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">33</td>
                            <td class="field-label">Hazard Pay (MWE)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">34</td>
                            <td class="field-label">13th Month Pay and Other Benefits (maximum of P90,000)</td>
                            <td class="amount-field">{{ number_format(min($employee->thirteenth_month_pay ?? 0, 90000), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">35</td>
                            <td class="field-label">De Minimis Benefits</td>
                            <td class="amount-field">{{ number_format($employee->de_minimis ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">36</td>
                            <td class="field-label">SSS, GSIS, PHIC & PAG-IBIG Contributions and Union Dues (Employee share only)</td>
                            <td class="amount-field">{{ number_format(($employee->sss_contributions ?? 0) + ($employee->philhealth_contributions ?? 0) + ($employee->pagibig_contributions ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">37</td>
                            <td class="field-label">Salaries and Other Forms of Compensation</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">38</td>
                            <td class="field-label" style="font-weight: bold;">Total Non-Taxable/Exempt Compensation Income (Sum of Items 29 to 37)</td>
                            <td class="amount-field" style="font-weight: bold;">{{ number_format($employee->total_non_taxable ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="background-color: #e0e0e0; font-weight: bold; font-size: 7px;">B. TAXABLE COMPENSATION INCOME - REGULAR</td>
                        </tr>
                        <tr>
                            <td class="item-number">39</td>
                            <td class="field-label">Basic Salary</td>
                            <td class="amount-field">{{ number_format($employee->basic_salary ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">40</td>
                            <td class="field-label">Representation</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">41</td>
                            <td class="field-label">Transportation</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">42</td>
                            <td class="field-label">Cost of Living Allowance (COLA)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">43</td>
                            <td class="field-label">Fixed Housing Allowance</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">44</td>
                            <td class="field-label">Others (specify)</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="background-color: #f0f0f0; font-size: 7px; font-style: italic;">SUPPLEMENTARY</td>
                        </tr>
                        <tr>
                            <td class="item-number">45</td>
                            <td class="field-label">Commission</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">46</td>
                            <td class="field-label">Profit Sharing</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">47</td>
                            <td class="field-label">Fees Including Director's Fees</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">48</td>
                            <td class="field-label">Taxable 13th Month Benefits</td>
                            <td class="amount-field">{{ number_format(max(0, ($employee->thirteenth_month_pay ?? 0) - 90000), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">49</td>
                            <td class="field-label">Hazard Pay</td>
                            <td class="amount-field">0.00</td>
                        </tr>
                        <tr>
                            <td class="item-number">50</td>
                            <td class="field-label">Overtime Pay</td>
                            <td class="amount-field">{{ number_format($employee->overtime_pay ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">51</td>
                            <td class="field-label">Others (specify)</td>
                            <td class="amount-field">{{ number_format(($employee->holiday_pay ?? 0) + ($employee->night_differential ?? 0) + ($employee->other_benefits ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="item-number">52</td>
                            <td class="field-label" style="font-weight: bold;">Total Taxable Compensation Income (Sum of Items 39 to 51B)</td>
                            <td class="amount-field" style="font-weight: bold;">{{ number_format($employee->taxable_compensation ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Declaration -->
        <div class="declaration" style="margin-top: 5px; border: 1px solid #000; padding: 5px;">
            I/We declare, under the penalties of perjury that this certificate has been made in good faith, verified by me/us, and to the best of my/our knowledge and belief, is true and correct, pursuant to the provisions of the National Internal Revenue Code, as amended, and the regulations issued under authority thereof. Further, I/we give my/our consent to the processing of my/our information as contemplated under the *Data Privacy Act of 2012 (R.A. No. 10173) for legitimate and lawful purposes.
        </div>

        <!-- Signatures -->
        <table style="margin-top: 5px;">
            <tr>
                <td style="width: 50%; border: 1px solid #000; padding: 5px;">
                    <table style="border: none;">
                        <tr>
                            <td class="item-number no-border">53</td>
                            <td class="no-border" style="font-size: 7px;">Date Signed</td>
                            <td class="field-value no-border" style="width: 80px;">&nbsp;</td>
                        </tr>
                    </table>
                    <div style="height: 25px; border-bottom: 1px solid #000; margin: 10px 20px;"></div>
                    <div style="text-align: center; font-size: 7px;">Present Employer/Authorized Agent Signature over Printed Name</div>
                </td>
                <td style="width: 50%; border: 1px solid #000; padding: 5px;">
                    <div style="font-size: 7px; font-weight: bold;">CONFORME:</div>
                    <table style="border: none;">
                        <tr>
                            <td class="item-number no-border">54</td>
                            <td class="no-border" style="font-size: 7px;">Date Signed</td>
                            <td class="field-value no-border" style="width: 80px;">&nbsp;</td>
                        </tr>
                    </table>
                    <div style="height: 25px; border-bottom: 1px solid #000; margin: 10px 20px;"></div>
                    <div style="text-align: center; font-size: 7px;">Employee Signature over Printed Name</div>
                    <table style="border: none; margin-top: 5px; font-size: 6px;">
                        <tr>
                            <td class="no-border">CTC/Valid ID No. of Employee</td>
                            <td class="field-value no-border" style="width: 60px;">&nbsp;</td>
                            <td class="no-border">Place of Issue</td>
                            <td class="field-value no-border" style="width: 50px;">&nbsp;</td>
                            <td class="no-border">Date Issued</td>
                            <td class="field-value no-border" style="width: 50px;">&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div style="margin-top: 10px; font-size: 6px; text-align: center; color: #666;">
            *NOTE: The BIR Data Privacy is in the BIR website (www.bir.gov.ph)
        </div>
        <div style="margin-top: 5px; font-size: 6px; text-align: center; color: #999;">
            Generated on {{ $generated_at }}
        </div>
    </div>
</body>
</html>
