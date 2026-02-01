// =============================================================================
// Data Types
// =============================================================================

export interface LeaveType {
  id: string
  code: string
  name: string
  description: string
  isPaid: boolean
  isStatutory: boolean
  isConvertible: boolean
  isCumulative: boolean
  defaultCredits: number
  maxAccumulation: number | null
  eligibilityMonths: number
  requiresDocument: boolean
  documentRequiredAfterDays?: number
  eligibleGender?: 'male' | 'female'
  color: string
}

export interface Employee {
  id: string
  employeeNumber: string
  firstName: string
  lastName: string
  email: string
  position: string
  department: string
  hireDate: string
  avatarUrl: string | null
}

export interface LeaveBalance {
  id: string
  employeeId: string
  leaveTypeId: string
  year: number
  broughtForward: number
  earned: number
  used: number
  pending: number
  available: number
}

export interface Attachment {
  id: string
  fileName: string
  fileSize: number
  uploadedAt: string
}

export interface LeaveApplication {
  id: string
  employeeId: string
  leaveTypeId: string
  startDate: string
  endDate: string
  totalDays: number
  reason: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  approverId: string
  approverName: string
  filedAt: string
  approvedAt: string | null
  rejectedAt: string | null
  cancelledAt: string | null
  approverRemarks: string | null
  attachments: Attachment[]
}

export interface BalanceAdjustment {
  id: string
  employeeId: string
  leaveTypeId: string
  adjustmentType: 'credit' | 'debit'
  days: number
  reason: string
  adjustedBy: string
  adjustedByName: string
  adjustedAt: string
  previousBalance: number
  newBalance: number
}

export interface DashboardStats {
  totalEmployees: number
  pendingRequests: number
  approvedThisMonth: number
  onLeaveToday: number
  upcomingLeaves: number
  averageBalanceUtilization: number
}

export interface CalendarEvent {
  id: string
  employeeId: string
  employeeName: string
  leaveTypeId: string
  leaveTypeName: string
  startDate: string
  endDate: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  color: string
}

// =============================================================================
// Component Props
// =============================================================================

export interface LeaveDashboardProps {
  /** Dashboard summary statistics */
  stats: DashboardStats
  /** Current user's leave balances */
  balances: LeaveBalance[]
  /** Leave types for balance display */
  leaveTypes: LeaveType[]
  /** Pending requests for current user or team */
  pendingRequests: LeaveApplication[]
  /** Employees for displaying request details */
  employees: Employee[]
  /** Upcoming calendar events */
  calendarEvents: CalendarEvent[]
  /** Called when user wants to file a new leave */
  onFileLeave?: () => void
  /** Called when user wants to view a leave request */
  onViewRequest?: (id: string) => void
  /** Called when user clicks on a calendar date */
  onDateClick?: (date: string) => void
}

export interface LeaveTypeListProps {
  /** Available leave types */
  leaveTypes: LeaveType[]
  /** Current user's balances for each type */
  balances: LeaveBalance[]
  /** Called when user selects a leave type to file */
  onSelectType?: (id: string) => void
  /** Called when user wants to view type details */
  onViewDetails?: (id: string) => void
}

export interface LeaveBalanceListProps {
  /** Employee's leave balances */
  balances: LeaveBalance[]
  /** Leave types for display */
  leaveTypes: LeaveType[]
  /** Leave applications for history */
  applications: LeaveApplication[]
  /** Called when user filters by leave type */
  onFilterByType?: (typeId: string | null) => void
  /** Called when user filters by year */
  onFilterByYear?: (year: number) => void
}

export interface LeaveApplicationFormProps {
  /** Available leave types */
  leaveTypes: LeaveType[]
  /** Current user's balances */
  balances: LeaveBalance[]
  /** Called when form is submitted */
  onSubmit?: (data: {
    leaveTypeId: string
    startDate: string
    endDate: string
    reason: string
    attachments: File[]
  }) => void
  /** Called when form is cancelled */
  onCancel?: () => void
}

export interface LeaveApprovalQueueProps {
  /** Pending leave requests to review */
  requests: LeaveApplication[]
  /** Employees for displaying request details */
  employees: Employee[]
  /** Leave types for display */
  leaveTypes: LeaveType[]
  /** Called when approver approves a request */
  onApprove?: (id: string, remarks?: string) => void
  /** Called when approver rejects a request */
  onReject?: (id: string, remarks: string) => void
  /** Called when user wants to view request details */
  onViewDetails?: (id: string) => void
  /** Called when filtering by department */
  onFilterByDepartment?: (department: string | null) => void
  /** Called when filtering by leave type */
  onFilterByType?: (typeId: string | null) => void
}

export interface LeaveCalendarProps {
  /** Calendar events to display */
  events: CalendarEvent[]
  /** Employees for filtering */
  employees: Employee[]
  /** Leave types for filtering */
  leaveTypes: LeaveType[]
  /** Called when user clicks on an event */
  onEventClick?: (id: string) => void
  /** Called when filtering by employee */
  onFilterByEmployee?: (employeeId: string | null) => void
  /** Called when filtering by department */
  onFilterByDepartment?: (department: string | null) => void
  /** Called when filtering by leave type */
  onFilterByType?: (typeId: string | null) => void
  /** Called when month changes */
  onMonthChange?: (year: number, month: number) => void
}

export interface BalanceAdjustmentModalProps {
  /** Employee to adjust balance for */
  employee: Employee
  /** Current balance to adjust */
  balance: LeaveBalance
  /** Leave type being adjusted */
  leaveType: LeaveType
  /** Previous adjustments for audit trail */
  adjustments: BalanceAdjustment[]
  /** Called when adjustment is submitted */
  onSubmit?: (data: {
    adjustmentType: 'credit' | 'debit'
    days: number
    reason: string
  }) => void
  /** Called when modal is closed */
  onClose?: () => void
}

export interface LeaveReportGeneratorProps {
  /** Employees for filtering */
  employees: Employee[]
  /** Leave types for filtering */
  leaveTypes: LeaveType[]
  /** Departments for filtering */
  departments: string[]
  /** Called when report is generated */
  onGenerate?: (params: {
    startDate: string
    endDate: string
    department?: string
    leaveTypeId?: string
    format: 'pdf' | 'excel' | 'csv'
  }) => void
}
