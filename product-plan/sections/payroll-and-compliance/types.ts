// Payroll & Compliance Types
// TypeScript interfaces for Philippine payroll processing and government compliance

// ============================================================================
// Core Entities
// ============================================================================

export type PayrollPeriodStatus = 'draft' | 'processing' | 'approved' | 'paid' | 'closed'
export type PayrollPeriodType = 'regular' | 'supplemental' | '13th_month' | 'final_pay'
export type PayFrequency = 'monthly' | 'semi_monthly' | 'weekly' | 'daily'
export type PayType = 'fixed' | 'hourly' | 'daily'
export type LoanType = 'sss_salary' | 'sss_calamity' | 'pagibig_mpl' | 'pagibig_calamity' | 'company'
export type LoanStatus = 'active' | 'paid' | 'cancelled' | 'defaulted'
export type GovernmentAgency = 'bir' | 'sss' | 'philhealth' | 'pagibig'
export type ReportStatus = 'pending' | 'generated' | 'submitted' | 'acknowledged'
export type ReportType =
  | 'bir_1601c' | 'bir_1604cf' | 'bir_2316' | 'bir_alphalist'
  | 'sss_r3' | 'sss_r5' | 'sss_sbr' | 'sss_ecl'
  | 'philhealth_er2' | 'philhealth_rf1' | 'philhealth_mdr'
  | 'pagibig_mcrf' | 'pagibig_loan_schedule'

export interface EmployeeSummary {
  id: string
  employeeNumber: string
  fullName: string
  department?: string
  position?: string
}

export interface UserSummary {
  id: string
  name: string
}

// ============================================================================
// Payroll Period
// ============================================================================

export interface PayrollPeriod {
  id: string
  periodCode: string
  periodType: PayrollPeriodType
  startDate: string
  endDate: string
  cutoffDate: string
  payDate: string
  status: PayrollPeriodStatus
  employeeCount: number
  processedCount: number
  totalGrossPay: number | null
  totalDeductions: number | null
  totalNetPay: number | null
  processedBy: UserSummary | null
  processedAt: string | null
  approvedBy: UserSummary | null
  approvedAt: string | null
}

// ============================================================================
// Payroll Record
// ============================================================================

export interface PayrollRecord {
  id: string
  payrollPeriodId: string
  employee: EmployeeSummary
  // Earnings
  basicPay: number
  daysWorked: number
  hoursWorked: number
  overtimeHours: number
  overtimePay: number
  nightDiffHours: number
  nightDiffPay: number
  holidayPay: number
  restDayPay: number
  allowances: number
  grossPay: number
  // Deductions
  sssDeduction: number
  philhealthDeduction: number
  pagibigDeduction: number
  withholdingTax: number
  loanDeductions: number
  otherDeductions: number
  totalDeductions: number
  // Net
  netPay: number
}

// ============================================================================
// Compensation
// ============================================================================

export interface SalaryHistoryEntry {
  basicSalary: number
  effectiveDate: string
  reason: string
}

export interface Compensation {
  id: string
  employeeId: string
  employee: EmployeeSummary
  basicSalary: number
  payFrequency: PayFrequency
  payType: PayType
  hourlyRate: number
  dailyRate: number
  bankName: string
  bankAccountNumber: string
  effectiveDate: string
  isCurrent: boolean
  salaryHistory: SalaryHistoryEntry[]
}

// ============================================================================
// Loans
// ============================================================================

export interface Loan {
  id: string
  employeeId: string
  employee: Pick<EmployeeSummary, 'id' | 'employeeNumber' | 'fullName'>
  loanType: LoanType
  loanNumber: string
  principalAmount: number
  monthlyAmortization: number
  totalPaid: number
  remainingBalance: number
  startDate: string
  endDate: string
  status: LoanStatus
}

// ============================================================================
// Government Contribution Tables
// ============================================================================

export interface SSSBracket {
  minSalary: number
  maxSalary: number | null
  msc: number // Monthly Salary Credit
  employeeShare: number
  employerShare: number
  ecContribution: number // Employees' Compensation
}

export interface SSSContributionTable {
  effectiveDate: string
  brackets: SSSBracket[]
}

export interface PhilHealthContributionTable {
  effectiveDate: string
  premiumRate: number
  incomeFloor: number
  incomeCeiling: number
  notes: string
}

export interface PagibigBracket {
  minSalary: number
  maxSalary: number | null
  employeeRate: number
  employerRate: number
}

export interface PagibigContributionTable {
  effectiveDate: string
  brackets: PagibigBracket[]
  maxMonthlyCompensation: number
  notes: string
}

export interface TaxBracket {
  minIncome: number
  maxIncome: number | null
  baseTax: number
  excessRate: number
  description: string
}

export interface TaxTable {
  effectiveDate: string
  description: string
  brackets: TaxBracket[]
}

// ============================================================================
// Government Reports
// ============================================================================

export interface GovernmentReport {
  id: string
  reportType: ReportType
  reportName: string
  description: string
  periodCovered: string
  payrollPeriodId: string | null
  status: ReportStatus
  generatedAt: string | null
  generatedBy: UserSummary | null
  submittedAt?: string | null
  fileUrl: string | null
  totalAmount: number | null
  dueDate: string
}

// ============================================================================
// Dashboard Stats
// ============================================================================

export interface UpcomingDeadline {
  title: string
  dueDate: string
  agency: GovernmentAgency
  status: 'pending' | 'submitted' | 'acknowledged'
}

export interface LastPayrollRun {
  periodCode: string
  payDate: string
  totalGrossPay: number
  totalNetPay: number
  employeesProcessed: number
}

export interface MonthlyTrendEntry {
  month: string
  grossPay: number
  netPay: number
}

export interface DashboardStats {
  currentPeriod: string
  totalEmployees: number
  processedEmployees: number
  totalGrossPay: number
  totalNetPay: number
  totalDeductions: number
  pendingPeriods: number
  periodsThisMonth: number
  upcomingDeadlines: UpcomingDeadline[]
  lastPayrollRun: LastPayrollRun
  monthlyTrend: MonthlyTrendEntry[]
}

// ============================================================================
// Component Props
// ============================================================================

export interface PayrollDashboardProps {
  stats: DashboardStats
  onViewPeriod: (periodCode: string) => void
  onCreatePeriod: () => void
  onProcessPayroll: () => void
  onViewReports: () => void
}

export interface PayrollPeriodListProps {
  periods: PayrollPeriod[]
  onView: (id: string) => void
  onCreate: () => void
  onProcess: (id: string) => void
  onApprove: (id: string) => void
  onClose: (id: string) => void
  onFilterByStatus: (status: PayrollPeriodStatus | 'all') => void
  onFilterByType: (type: PayrollPeriodType | 'all') => void
}

export interface PayrollRecordListProps {
  records: PayrollRecord[]
  period: PayrollPeriod
  onView: (id: string) => void
  onViewPayslip: (id: string) => void
  onFilterByDepartment: (department: string | 'all') => void
  onSearch: (query: string) => void
  onExport: () => void
}

export interface PayrollRecordDetailProps {
  record: PayrollRecord
  period: PayrollPeriod
  onBack: () => void
  onViewPayslip: () => void
  onRequestCorrection: () => void
}

export interface CompensationListProps {
  compensations: Compensation[]
  onView: (id: string) => void
  onEdit: (id: string) => void
  onViewHistory: (employeeId: string) => void
  onFilterByDepartment: (department: string | 'all') => void
  onSearch: (query: string) => void
}

export interface LoanListProps {
  loans: Loan[]
  onView: (id: string) => void
  onCreate: () => void
  onEdit: (id: string) => void
  onFilterByType: (type: LoanType | 'all') => void
  onFilterByStatus: (status: LoanStatus | 'all') => void
  onSearch: (query: string) => void
}

export interface GovernmentReportListProps {
  reports: GovernmentReport[]
  onGenerate: (reportType: ReportType, periodId?: string) => void
  onDownload: (id: string) => void
  onSubmit: (id: string) => void
  onFilterByAgency: (agency: GovernmentAgency | 'all') => void
  onFilterByStatus: (status: ReportStatus | 'all') => void
}

export interface ContributionTableEditorProps {
  sssTable: SSSContributionTable
  philhealthTable: PhilHealthContributionTable
  pagibigTable: PagibigContributionTable
  taxTable: TaxTable
  onUpdateSSS: (table: SSSContributionTable) => void
  onUpdatePhilHealth: (table: PhilHealthContributionTable) => void
  onUpdatePagibig: (table: PagibigContributionTable) => void
  onUpdateTax: (table: TaxTable) => void
}

export interface PayslipPreviewProps {
  record: PayrollRecord
  period: PayrollPeriod
  companyName: string
  companyAddress: string
  onDownload: () => void
  onEmail: () => void
  onClose: () => void
}
