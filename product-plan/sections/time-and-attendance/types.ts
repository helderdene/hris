// =============================================================================
// Data Types
// =============================================================================

export interface ScheduleDetail {
  dayOfWeek: number
  dayName: string
  isRestDay: boolean
  timeIn: string | null
  timeOut: string | null
  breakStart: string | null
  breakEnd: string | null
  breakMinutes: number
}

export interface WorkSchedule {
  id: string
  code: string
  name: string
  scheduleType: 'fixed' | 'flexible' | 'shifting' | 'compressed'
  workHoursPerDay: number
  workDaysPerWeek: number
  gracePeriodMinutes: number
  description: string
  employeeCount: number
  isActive: boolean
  details: ScheduleDetail[]
}

export interface WorkLocation {
  id: string
  name: string
}

export interface BiometricDevice {
  id: string
  deviceCode: string
  name: string
  model: string
  serialNumber: string
  workLocation: WorkLocation
  ipAddress: string
  mqttTopic: string
  status: 'online' | 'offline'
  lastSyncAt: string
  employeeCount: number
  firmwareVersion: string
  installedAt: string
  isActive: boolean
}

export interface EmployeeSummary {
  id: string
  employeeNumber: string
  fullName: string
  department: string
  position: string
}

export interface DeviceSummary {
  id: string
  code: string
  name: string
}

export interface ScheduleSummary {
  id: string
  name: string
}

export interface AttendanceLog {
  id: string
  employee: EmployeeSummary
  device: DeviceSummary | null
  logDatetime: string
  logType: 'time_in' | 'time_out' | 'break_out' | 'break_in'
  source: 'device' | 'manual' | 'mobile' | 'web'
  verificationMethod: 'face' | 'fingerprint' | 'card' | 'pin' | null
  confidenceScore: number | null
  photoUrl: string | null
  latitude: number | null
  longitude: number | null
}

export interface DailyTimeRecord {
  id: string
  employee: EmployeeSummary
  workDate: string
  schedule: ScheduleSummary | null
  expectedTimeIn: string | null
  expectedTimeOut: string | null
  actualTimeIn: string | null
  actualTimeOut: string | null
  lateMinutes: number
  undertimeMinutes: number
  overtimeMinutes: number
  nightDiffMinutes: number
  hoursWorked: number | null
  dayType: 'regular' | 'rest_day' | 'regular_holiday' | 'special_holiday' | 'double_holiday'
  status: 'present' | 'absent' | 'leave' | 'holiday' | 'rest_day'
  remarks: string | null
}

export interface PersonReference {
  id: string
  name: string
}

export interface DtrCorrection {
  id: string
  employee: EmployeeSummary
  workDate: string
  correctionType: 'missing_time_in' | 'missing_time_out' | 'late_justification' | 'undertime_justification' | 'wrong_log'
  requestedTimeIn: string | null
  requestedTimeOut: string | null
  reason: string
  attachmentUrl: string | null
  status: 'pending' | 'approved' | 'rejected'
  requestedAt: string
  requestedBy: PersonReference
  reviewedAt: string | null
  reviewedBy: PersonReference | null
  reviewRemarks: string | null
}

export interface OvertimeRequest {
  id: string
  employee: EmployeeSummary
  workDate: string
  plannedStartTime: string
  plannedEndTime: string
  plannedHours: number
  reason: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  requestedAt: string
  approvedAt: string | null
  approvedBy: PersonReference | null
  approverRemarks: string | null
}

export interface RecentActivity {
  employeeId: string
  employeeName: string
  action: 'time_in' | 'time_out' | 'break_out' | 'break_in'
  time: string
  device: string
}

export interface DepartmentAttendance {
  department: string
  present: number
  total: number
}

export interface AttendanceStats {
  date: string
  totalEmployees: number
  presentToday: number
  lateToday: number
  absentToday: number
  onLeaveToday: number
  pendingCorrections: number
  pendingOvertimeRequests: number
  devicesOnline: number
  devicesOffline: number
  recentActivity: RecentActivity[]
  departmentAttendance: DepartmentAttendance[]
}

// =============================================================================
// Component Props
// =============================================================================

/** Props for the Attendance Dashboard view */
export interface AttendanceDashboardProps {
  /** Real-time attendance statistics and KPIs */
  stats: AttendanceStats
  /** List of biometric devices for status display */
  devices: BiometricDevice[]
  /** Called when user wants to view all employees */
  onViewAllEmployees?: () => void
  /** Called when user wants to view late arrivals */
  onViewLateArrivals?: () => void
  /** Called when user wants to view absences */
  onViewAbsences?: () => void
  /** Called when user wants to view pending corrections */
  onViewPendingCorrections?: () => void
  /** Called when user wants to view pending OT requests */
  onViewPendingOvertimeRequests?: () => void
  /** Called when user wants to view device details */
  onViewDevice?: (id: string) => void
}

/** Props for the Work Schedules list view */
export interface WorkScheduleListProps {
  /** List of work schedules */
  schedules: WorkSchedule[]
  /** Called when user wants to view schedule details */
  onView?: (id: string) => void
  /** Called when user wants to edit a schedule */
  onEdit?: (id: string) => void
  /** Called when user wants to delete a schedule */
  onDelete?: (id: string) => void
  /** Called when user wants to create a new schedule */
  onCreate?: () => void
  /** Called when user wants to assign schedule to employees */
  onAssign?: (id: string) => void
}

/** Props for the Attendance Logs view */
export interface AttendanceLogListProps {
  /** List of attendance logs */
  logs: AttendanceLog[]
  /** Called when user wants to view log details */
  onView?: (id: string) => void
  /** Called when user wants to filter by employee */
  onFilterByEmployee?: (employeeId: string) => void
  /** Called when user wants to filter by device */
  onFilterByDevice?: (deviceId: string) => void
  /** Called when user wants to filter by date range */
  onFilterByDateRange?: (startDate: string, endDate: string) => void
  /** Called when user wants to export logs */
  onExport?: () => void
}

/** Props for the Daily Time Records view */
export interface DailyTimeRecordListProps {
  /** List of DTR entries */
  records: DailyTimeRecord[]
  /** Called when user wants to view DTR details */
  onView?: (id: string) => void
  /** Called when user wants to request a correction */
  onRequestCorrection?: (id: string) => void
  /** Called when user wants to filter by employee */
  onFilterByEmployee?: (employeeId: string) => void
  /** Called when user wants to filter by date range */
  onFilterByDateRange?: (startDate: string, endDate: string) => void
  /** Called when user wants to export DTR report */
  onExport?: () => void
}

/** Props for the DTR Corrections view */
export interface DtrCorrectionListProps {
  /** List of correction requests */
  corrections: DtrCorrection[]
  /** Called when user wants to view correction details */
  onView?: (id: string) => void
  /** Called when user wants to approve a correction */
  onApprove?: (id: string) => void
  /** Called when user wants to reject a correction */
  onReject?: (id: string) => void
  /** Called when user wants to create a new correction request */
  onCreate?: () => void
}

/** Props for the Overtime Requests view */
export interface OvertimeRequestListProps {
  /** List of overtime requests */
  requests: OvertimeRequest[]
  /** Called when user wants to view request details */
  onView?: (id: string) => void
  /** Called when user wants to approve an OT request */
  onApprove?: (id: string) => void
  /** Called when user wants to reject an OT request */
  onReject?: (id: string) => void
  /** Called when user wants to file a new OT request */
  onCreate?: () => void
  /** Called when user wants to cancel their request */
  onCancel?: (id: string) => void
}

/** Props for the Biometric Devices view */
export interface BiometricDeviceListProps {
  /** List of biometric devices */
  devices: BiometricDevice[]
  /** Called when user wants to view device details */
  onView?: (id: string) => void
  /** Called when user wants to edit device settings */
  onEdit?: (id: string) => void
  /** Called when user wants to register a new device */
  onCreate?: () => void
  /** Called when user wants to sync a device */
  onSync?: (id: string) => void
  /** Called when user wants to deactivate a device */
  onDeactivate?: (id: string) => void
}
