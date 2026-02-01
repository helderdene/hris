// =============================================================================
// Data Types
// =============================================================================

export interface WorkLocation {
  id: string
  code: string
  name: string
  address: string
  isActive: boolean
}

export interface Department {
  id: string
  code: string
  name: string
  parentId: string | null
  headEmployeeId: string | null
  headEmployeeName: string | null
  costCenter: string
  employeeCount: number
  isActive: boolean
}

export interface Position {
  id: string
  code: string
  title: string
  departmentId: string
  departmentName: string
  level: number
  salaryGrade: string
  minSalary: number
  maxSalary: number
  isActive: boolean
}

export interface ContactInfo {
  mobileNumber: string
  email: string
  presentAddress: string
  permanentAddress: string
}

export interface EmployeeDepartment {
  id: string
  name: string
}

export interface EmployeePosition {
  id: string
  title: string
}

export interface EmployeeWorkLocation {
  id: string
  name: string
}

export interface EmployeeSupervisor {
  id: string
  name: string
}

export type Gender = 'male' | 'female'

export type CivilStatus = 'single' | 'married' | 'widowed' | 'separated' | 'divorced'

export type EmploymentStatus = 'regular' | 'probationary' | 'contractual' | 'project_based' | 'seasonal' | 'casual'

export type SeparationReason = 'resignation' | 'termination' | 'retirement' | 'end_of_contract' | 'death' | 'awol'

export type PayFrequency = 'monthly' | 'semi_monthly' | 'weekly' | 'daily'

export interface Employee {
  id: string
  employeeNumber: string
  firstName: string
  middleName: string | null
  lastName: string
  suffix: string | null
  fullName: string
  photoUrl: string | null
  birthDate: string
  age: number
  gender: Gender
  civilStatus: CivilStatus
  nationality: string
  tin: string
  sssNumber: string
  philhealthNumber: string
  pagibigNumber: string
  employmentStatus: EmploymentStatus
  dateHired: string
  regularizationDate: string | null
  yearsOfService: number
  separationDate: string | null
  separationReason: SeparationReason | null
  isActive: boolean
  department: EmployeeDepartment
  position: EmployeePosition
  workLocation: EmployeeWorkLocation
  supervisor: EmployeeSupervisor | null
  contactInfo: ContactInfo
  basicSalary: number
  payFrequency: PayFrequency
}

export type DocumentCategory = 'contract' | 'memo' | 'certification' | 'medical' | 'separation' | 'other'

export interface Document {
  id: string
  employeeId: string
  employeeName: string
  fileName: string
  fileType: string
  fileSize: number
  category: DocumentCategory
  description: string
  uploadedAt: string
  uploadedBy: string
}

export interface TenureDistribution {
  lessThan1Year: number
  oneToThreeYears: number
  threeToFiveYears: number
  fiveToTenYears: number
  moreThan10Years: number
}

export interface EmploymentStatusBreakdown {
  regular: number
  probationary: number
  contractual: number
  projectBased: number
}

export interface DepartmentHeadcount {
  department: string
  count: number
}

export interface DashboardStats {
  totalHeadcount: number
  activeEmployees: number
  newHiresThisMonth: number
  separationsThisMonth: number
  turnoverRate: number
  averageTenure: number
  tenureDistribution: TenureDistribution
  employmentStatusBreakdown: EmploymentStatusBreakdown
  departmentHeadcount: DepartmentHeadcount[]
}

// =============================================================================
// Component Props
// =============================================================================

export interface EmployeeManagementProps {
  /** Dashboard statistics for KPIs display */
  dashboardStats: DashboardStats
  /** List of employees to display */
  employees: Employee[]
  /** List of departments */
  departments: Department[]
  /** List of positions */
  positions: Position[]
  /** List of work locations */
  workLocations: WorkLocation[]
  /** List of 201 file documents */
  documents: Document[]

  // Employee actions
  /** Called when user wants to view an employee's profile */
  onViewEmployee?: (id: string) => void
  /** Called when user wants to add a new employee */
  onAddEmployee?: () => void
  /** Called when user wants to edit an employee */
  onEditEmployee?: (id: string) => void
  /** Called when user wants to delete/archive an employee */
  onDeleteEmployee?: (id: string) => void
  /** Called when user wants to process employee separation */
  onSeparateEmployee?: (id: string) => void
  /** Called when user wants to export employees to Excel/CSV */
  onExportEmployees?: (format: 'excel' | 'csv') => void
  /** Called when user wants to bulk import employees */
  onBulkImport?: () => void

  // Department actions
  /** Called when user wants to view department details */
  onViewDepartment?: (id: string) => void
  /** Called when user wants to add a new department */
  onAddDepartment?: () => void
  /** Called when user wants to edit a department */
  onEditDepartment?: (id: string) => void
  /** Called when user wants to delete a department */
  onDeleteDepartment?: (id: string) => void

  // Position actions
  /** Called when user wants to view position details */
  onViewPosition?: (id: string) => void
  /** Called when user wants to add a new position */
  onAddPosition?: () => void
  /** Called when user wants to edit a position */
  onEditPosition?: (id: string) => void
  /** Called when user wants to delete a position */
  onDeletePosition?: (id: string) => void

  // Document actions
  /** Called when user wants to view/preview a document */
  onViewDocument?: (id: string) => void
  /** Called when user wants to upload a new document */
  onUploadDocument?: (employeeId: string) => void
  /** Called when user wants to download a document */
  onDownloadDocument?: (id: string) => void
  /** Called when user wants to delete a document */
  onDeleteDocument?: (id: string) => void
}

// =============================================================================
// Sub-component Props
// =============================================================================

export interface EmployeeListProps {
  employees: Employee[]
  onView?: (id: string) => void
  onEdit?: (id: string) => void
  onDelete?: (id: string) => void
  onExport?: (format: 'excel' | 'csv') => void
  onCreate?: () => void
}

export interface EmployeeProfileProps {
  employee: Employee
  documents: Document[]
  onEdit?: () => void
  onSeparate?: () => void
  onUploadDocument?: () => void
  onViewDocument?: (id: string) => void
  onDownloadDocument?: (id: string) => void
  onDeleteDocument?: (id: string) => void
  onBack?: () => void
}

export interface DepartmentListProps {
  departments: Department[]
  onView?: (id: string) => void
  onEdit?: (id: string) => void
  onDelete?: (id: string) => void
  onCreate?: () => void
}

export interface PositionListProps {
  positions: Position[]
  onView?: (id: string) => void
  onEdit?: (id: string) => void
  onDelete?: (id: string) => void
  onCreate?: () => void
}

export interface OrganizationChartProps {
  departments: Department[]
  employees: Employee[]
  onSelectDepartment?: (id: string) => void
  onSelectEmployee?: (id: string) => void
}

export interface DashboardProps {
  stats: DashboardStats
  onViewAllEmployees?: () => void
  onViewNewHires?: () => void
  onViewSeparations?: () => void
}
