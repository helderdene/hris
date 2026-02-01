// =============================================================================
// Data Types
// =============================================================================

export interface Supervisor {
  id: string
  name: string
  position: string
}

export interface GovernmentIds {
  tin: string
  sss: string
  philhealth: string
  pagibig: string
}

export interface Employee {
  id: string
  employeeNumber: string
  firstName: string
  middleName: string | null
  lastName: string
  suffix: string | null
  email: string
  mobileNumber: string
  position: string
  department: string
  dateHired: string
  employmentStatus: 'regular' | 'probationary' | 'contractual' | 'project_based' | 'seasonal' | 'casual'
  photoUrl: string
  supervisor: Supervisor
  governmentIds: GovernmentIds
}

export interface PayslipDeductions {
  sss: number
  philhealth: number
  pagibig: number
  withholdingTax: number
  sssLoan: number
  others: number
}

export interface Payslip {
  id: string
  periodCode: string
  periodType: 'regular' | 'supplemental' | '13th_month' | 'final'
  startDate: string
  endDate: string
  payDate: string
  daysWorked: number
  hoursWorked: number
  basicPay: number
  overtimePay: number
  nightDiffPay: number
  holidayPay: number
  allowances: number
  grossPay: number
  deductions: PayslipDeductions
  totalDeductions: number
  netPay: number
  status: 'draft' | 'processing' | 'approved' | 'paid' | 'closed'
}

export interface LeaveBalance {
  id: string
  leaveTypeId: string
  leaveTypeName: string
  leaveTypeCode: string
  year: number
  broughtForward: number
  earned: number
  used: number
  pending: number
  available: number
}

export interface Approver {
  id: string
  name: string
}

export interface LeaveApplication {
  id: string
  applicationNumber: string
  leaveTypeId: string
  leaveTypeName: string
  startDate: string
  endDate: string
  daysApplied: number
  halfDayType: 'am' | 'pm' | null
  reason: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  filedDate: string
  approver: Approver
  approvedAt: string | null
  rejectionReason: string | null
}

export interface DailyTimeRecord {
  id: string
  workDate: string
  dayType: 'regular' | 'rest_day' | 'regular_holiday' | 'special_holiday' | 'double_holiday'
  status: 'present' | 'absent' | 'leave' | 'holiday' | 'rest_day'
  expectedTimeIn: string | null
  expectedTimeOut: string | null
  actualTimeIn: string | null
  actualTimeOut: string | null
  lateMinutes: number
  undertimeMinutes: number
  overtimeMinutes: number
  nightDiffMinutes: number
  hoursWorked: number
  remarks: string | null
}

export interface DocumentRequest {
  id: string
  requestNumber: string
  documentType: 'coe' | 'itr' | 'clearance' | 'service_record' | 'employment_certificate'
  documentTypeName: string
  purpose: string
  additionalNotes: string | null
  status: 'pending' | 'processing' | 'completed' | 'cancelled'
  requestedDate: string
  expectedCompletionDate: string
  completedDate: string | null
  downloadUrl: string | null
}

export interface OvertimeRequest {
  id: string
  requestNumber: string
  workDate: string
  startTime: string
  endTime: string
  hours: number
  reason: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  filedDate: string
  approver: Approver
  approvedAt: string | null
}

export interface Loan {
  id: string
  loanType: 'sss' | 'pagibig' | 'company'
  loanTypeName: string
  loanNumber: string
  principalAmount: number
  monthlyAmortization: number
  totalPaid: number
  remainingBalance: number
  startDate: string
  endDate: string
  remainingMonths: number
  status: 'active' | 'paid' | 'defaulted'
}

export interface TeamMember {
  id: string
  employeeNumber: string
  name: string
  position: string
  department: string
  photoUrl: string
  dateHired: string
  leaveBalance: number
  pendingLeaves: number
  attendanceStatus: 'present' | 'absent' | 'leave' | 'late'
}

export interface PendingApproval {
  id: string
  type: 'leave' | 'overtime' | 'dtr_correction'
  referenceNumber: string
  employeeId: string
  employeeName: string
  employeePhoto: string
  description: string
  reason: string
  filedDate: string
  urgency: 'low' | 'normal' | 'high'
}

// =============================================================================
// Component Props
// =============================================================================

export interface SelfServicePortalProps {
  /** The current logged-in employee's profile */
  currentEmployee: Employee
  /** List of payslips for the employee */
  payslips: Payslip[]
  /** Leave balances by type for the current year */
  leaveBalances: LeaveBalance[]
  /** Leave applications filed by the employee */
  leaveApplications: LeaveApplication[]
  /** Daily time records for the employee */
  dailyTimeRecords: DailyTimeRecord[]
  /** Document requests filed by the employee */
  documentRequests: DocumentRequest[]
  /** Overtime requests filed by the employee */
  overtimeRequests: OvertimeRequest[]
  /** Active loans for the employee */
  loans: Loan[]
  /** Direct reports (for managers) */
  teamMembers: TeamMember[]
  /** Pending approval items (for managers) */
  pendingApprovals: PendingApproval[]

  // Employee actions
  /** Called when user wants to update their personal information */
  onUpdateProfile?: () => void
  /** Called when user wants to view a payslip's details */
  onViewPayslip?: (id: string) => void
  /** Called when user wants to download a payslip as PDF */
  onDownloadPayslip?: (id: string) => void

  // Leave actions
  /** Called when user wants to file a new leave request */
  onFileLeave?: () => void
  /** Called when user wants to view leave application details */
  onViewLeaveApplication?: (id: string) => void
  /** Called when user wants to cancel a pending leave application */
  onCancelLeaveApplication?: (id: string) => void

  // DTR actions
  /** Called when user wants to view DTR details for a specific date */
  onViewDTR?: (id: string) => void
  /** Called when user wants to request a DTR correction */
  onRequestDTRCorrection?: (id: string) => void

  // Overtime actions
  /** Called when user wants to file a new overtime request */
  onFileOvertime?: () => void
  /** Called when user wants to view overtime request details */
  onViewOvertimeRequest?: (id: string) => void
  /** Called when user wants to cancel a pending overtime request */
  onCancelOvertimeRequest?: (id: string) => void

  // Document request actions
  /** Called when user wants to request a new document */
  onRequestDocument?: () => void
  /** Called when user wants to view document request details */
  onViewDocumentRequest?: (id: string) => void
  /** Called when user wants to download a completed document */
  onDownloadDocument?: (id: string) => void

  // Loan actions
  /** Called when user wants to view loan details */
  onViewLoan?: (id: string) => void
  /** Called when user wants to apply for a new loan */
  onApplyLoan?: () => void

  // Manager actions
  /** Called when manager wants to view a team member's profile */
  onViewTeamMember?: (id: string) => void
  /** Called when manager approves a pending request */
  onApproveRequest?: (id: string) => void
  /** Called when manager rejects a pending request */
  onRejectRequest?: (id: string, reason: string) => void
  /** Called when manager approves multiple requests at once */
  onBatchApprove?: (ids: string[]) => void
  /** Called when manager rejects multiple requests at once */
  onBatchReject?: (ids: string[], reason: string) => void
}
