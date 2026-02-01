// KasamaHR Data Model Types
// These interfaces define the core entities for the HR management system

// ===== Organization =====

export interface Tenant {
  id: string
  name: string
  subdomain: string
  logo?: string
  settings?: TenantSettings
  createdAt: string
  updatedAt: string
}

export interface TenantSettings {
  timezone: string
  dateFormat: string
  payrollCutoff: number
  gracePeriodMinutes: number
}

export interface Department {
  id: string
  tenantId: string
  name: string
  code: string
  parentId?: string
  headEmployeeId?: string
  description?: string
  isActive: boolean
}

export interface Position {
  id: string
  tenantId: string
  departmentId: string
  title: string
  code: string
  salaryGrade?: string
  minSalary?: number
  maxSalary?: number
  description?: string
  isActive: boolean
}

export interface WorkLocation {
  id: string
  tenantId: string
  name: string
  address: string
  city: string
  isActive: boolean
}

// ===== People =====

export interface Employee {
  id: string
  tenantId: string
  employeeId: string // Company-specific ID
  firstName: string
  lastName: string
  middleName?: string
  suffix?: string
  email: string
  phone?: string
  birthDate: string
  gender: 'male' | 'female' | 'other'
  civilStatus: 'single' | 'married' | 'widowed' | 'separated' | 'divorced'
  nationality: string
  address?: string
  city?: string
  province?: string
  zipCode?: string
  
  // Government IDs (Philippine)
  tin?: string
  sssNumber?: string
  philhealthNumber?: string
  pagibigNumber?: string
  
  // Employment
  hireDate: string
  regularizationDate?: string
  separationDate?: string
  status: 'active' | 'probationary' | 'resigned' | 'terminated' | 'retired'
  
  // Profile
  avatarUrl?: string
  emergencyContactName?: string
  emergencyContactPhone?: string
  
  createdAt: string
  updatedAt: string
}

export interface User {
  id: string
  tenantId: string
  employeeId?: string
  email: string
  role: 'admin' | 'hr' | 'manager' | 'employee'
  isActive: boolean
  lastLogin?: string
}

export interface EmployeeAssignment {
  id: string
  employeeId: string
  positionId: string
  departmentId: string
  workLocationId?: string
  supervisorId?: string
  startDate: string
  endDate?: string
  isPrimary: boolean
}

export interface Compensation {
  id: string
  employeeId: string
  basicSalary: number
  payFrequency: 'monthly' | 'semi-monthly' | 'weekly' | 'daily'
  payType: 'fixed' | 'hourly' | 'daily'
  bankName?: string
  bankAccountNumber?: string
  effectiveDate: string
  endDate?: string
}

export interface Document {
  id: string
  employeeId: string
  type: 'contract' | 'certification' | 'memo' | 'id' | 'other'
  name: string
  fileName: string
  fileUrl: string
  fileSize: number
  mimeType: string
  uploadedAt: string
  uploadedBy: string
}

// ===== Time & Attendance =====

export interface WorkSchedule {
  id: string
  tenantId: string
  name: string
  type: 'fixed' | 'flexible' | 'shifting' | 'compressed'
  timeIn: string // HH:mm format
  timeOut: string
  breakStart?: string
  breakEnd?: string
  gracePeriodMinutes: number
  workDays: number[] // 0=Sunday, 1=Monday, etc.
  isActive: boolean
}

export interface AttendanceLog {
  id: string
  employeeId: string
  deviceId: string
  timestamp: string
  logType: 'time_in' | 'time_out' | 'break_start' | 'break_end'
  verificationMethod: 'face' | 'fingerprint' | 'card' | 'manual'
  confidenceScore?: number
  capturedPhotoUrl?: string
}

export interface DailyTimeRecord {
  id: string
  employeeId: string
  workScheduleId: string
  date: string
  timeIn?: string
  timeOut?: string
  breakStart?: string
  breakEnd?: string
  hoursWorked: number
  lateMinutes: number
  undertimeMinutes: number
  overtimeMinutes: number
  nightDiffMinutes: number
  status: 'present' | 'absent' | 'leave' | 'holiday' | 'rest_day'
  dayType: 'regular' | 'rest_day' | 'special_holiday' | 'regular_holiday' | 'double_holiday'
  remarks?: string
}

export interface BiometricDevice {
  id: string
  tenantId: string
  workLocationId: string
  name: string
  serialNumber: string
  deviceType: 'facial_recognition' | 'fingerprint' | 'card_reader'
  mqttTopic: string
  status: 'online' | 'offline' | 'error'
  lastSyncAt?: string
}

export interface Holiday {
  id: string
  tenantId: string
  name: string
  date: string
  type: 'regular' | 'special' | 'double'
  isRecurring: boolean
}

// ===== Payroll =====

export interface PayrollPeriod {
  id: string
  tenantId: string
  type: 'regular' | 'supplemental' | '13th_month' | 'final_pay'
  startDate: string
  endDate: string
  cutoffDate: string
  payDate: string
  status: 'draft' | 'processing' | 'approved' | 'paid' | 'closed'
  totalGross: number
  totalDeductions: number
  totalNet: number
  processedAt?: string
  approvedAt?: string
  approvedBy?: string
}

export interface PayrollRecord {
  id: string
  payrollPeriodId: string
  employeeId: string
  
  // Earnings
  basicPay: number
  overtimePay: number
  nightDiffPay: number
  holidayPay: number
  allowances: number
  otherEarnings: number
  grossPay: number
  
  // Deductions
  sssDeduction: number
  philhealthDeduction: number
  pagibigDeduction: number
  taxDeduction: number
  loanDeductions: number
  otherDeductions: number
  totalDeductions: number
  
  netPay: number
}

export interface PayrollDeduction {
  id: string
  payrollRecordId: string
  type: 'sss' | 'philhealth' | 'pagibig' | 'tax' | 'loan' | 'adjustment' | 'other'
  description: string
  amount: number
  employeeShare: number
  employerShare: number
}

export interface ContributionTable {
  id: string
  tenantId: string
  type: 'sss' | 'philhealth' | 'pagibig'
  effectiveDate: string
  brackets: ContributionBracket[]
}

export interface ContributionBracket {
  minSalary: number
  maxSalary: number
  employeeShare: number
  employerShare: number
}

export interface TaxTable {
  id: string
  tenantId: string
  effectiveDate: string
  brackets: TaxBracket[]
}

export interface TaxBracket {
  minIncome: number
  maxIncome: number
  baseTax: number
  excessRate: number
}

export interface Loan {
  id: string
  employeeId: string
  type: 'sss' | 'pagibig' | 'company'
  loanNumber?: string
  principalAmount: number
  monthlyAmortization: number
  totalPaid: number
  balance: number
  startDate: string
  endDate?: string
  status: 'active' | 'paid' | 'defaulted'
}

export interface GovernmentReport {
  id: string
  tenantId: string
  payrollPeriodId?: string
  reportType: 'bir_1601c' | 'bir_1604cf' | 'bir_2316' | 'bir_alphalist' | 'sss_r3' | 'sss_r5' | 'philhealth_er2' | 'pagibig_mcrf'
  periodStart: string
  periodEnd: string
  fileUrl?: string
  generatedAt: string
  generatedBy: string
}

// ===== Leave =====

export interface LeaveType {
  id: string
  tenantId: string
  code: string
  name: string
  description?: string
  isPaid: boolean
  isStatutory: boolean
  isConvertible: boolean
  isCumulative: boolean
  defaultCredits: number
  maxCarryover: number
  accrualRate?: number // Credits per month
  minServiceMonths?: number // Eligibility
  requiresDocument: boolean
  documentTypes?: string[]
}

export interface LeaveBalance {
  id: string
  employeeId: string
  leaveTypeId: string
  year: number
  entitlement: number
  carryover: number
  adjustment: number
  used: number
  pending: number
  available: number
}

export interface LeaveApplication {
  id: string
  employeeId: string
  leaveTypeId: string
  applicationNumber: string
  startDate: string
  endDate: string
  days: number
  isHalfDay: boolean
  halfDayType?: 'am' | 'pm'
  reason: string
  documentUrls?: string[]
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  approverEmployeeId?: string
  approvedAt?: string
  rejectionReason?: string
  filedAt: string
}
