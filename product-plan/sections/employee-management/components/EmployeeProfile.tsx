import { useState } from 'react'
import {
  ArrowLeft,
  Pencil,
  UserMinus,
  Mail,
  Phone,
  MapPin,
  Calendar,
  Building2,
  Briefcase,
  FileText,
  Upload,
  Download,
  Eye,
  Trash2,
  User,
  CreditCard,
} from 'lucide-react'
import type { EmployeeProfileProps, Document } from '../types'

const tabs = [
  { id: 'personal', label: 'Personal Info', icon: User },
  { id: 'employment', label: 'Employment', icon: Briefcase },
  { id: 'government', label: 'Government IDs', icon: CreditCard },
  { id: 'contact', label: 'Contact', icon: MapPin },
  { id: 'documents', label: 'Documents', icon: FileText },
]

const statusColors: Record<string, { bg: string; text: string }> = {
  regular: { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
  probationary: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-400' },
  contractual: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
  project_based: { bg: 'bg-slate-100 dark:bg-slate-700', text: 'text-slate-700 dark:text-slate-300' },
}

const categoryColors: Record<string, string> = {
  contract: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  memo: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  certification: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
  medical: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
  separation: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
  other: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400',
}

function InfoRow({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div className="py-3 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 border-b border-slate-100 dark:border-slate-700 last:border-0">
      <dt className="text-sm font-medium text-slate-500 dark:text-slate-400">
        {label}
      </dt>
      <dd className="text-sm text-slate-900 dark:text-slate-100 sm:col-span-2">
        {value || '—'}
      </dd>
    </div>
  )
}

function formatDate(dateString: string | null | undefined): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

export function EmployeeProfile({
  employee,
  documents,
  onEdit,
  onSeparate,
  onUploadDocument,
  onViewDocument,
  onDownloadDocument,
  onDeleteDocument,
  onBack,
}: EmployeeProfileProps) {
  const [activeTab, setActiveTab] = useState('personal')

  const initials = `${employee.firstName[0]}${employee.lastName[0]}`
  const statusStyle = statusColors[employee.employmentStatus] || statusColors.project_based
  const statusLabel = employee.employmentStatus.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())

  const employeeDocuments = documents.filter((d) => d.employeeId === employee.id)

  return (
    <div className="space-y-6">
      {/* Back Button & Actions */}
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={onBack}
          className="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Employees
        </button>
        <div className="flex gap-3">
          {employee.isActive && (
            <button
              type="button"
              onClick={onSeparate}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-slate-800 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
            >
              <UserMinus className="w-4 h-4" />
              Separate
            </button>
          )}
          <button
            type="button"
            onClick={onEdit}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Pencil className="w-4 h-4" />
            Edit
          </button>
        </div>
      </div>

      {/* Employee Header Card */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <div className="flex flex-col sm:flex-row gap-6">
          {/* Avatar */}
          {employee.photoUrl ? (
            <img
              src={employee.photoUrl}
              alt={employee.fullName}
              className="w-24 h-24 rounded-xl object-cover"
            />
          ) : (
            <div className="w-24 h-24 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center flex-shrink-0">
              <span className="text-white font-bold text-2xl">{initials}</span>
            </div>
          )}

          {/* Info */}
          <div className="flex-1">
            <div className="flex flex-wrap items-center gap-3 mb-2">
              <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                {employee.fullName}
              </h1>
              <span className={`px-3 py-1 rounded-full text-sm font-medium ${statusStyle.bg} ${statusStyle.text}`}>
                {statusLabel}
              </span>
              {!employee.isActive && (
                <span className="px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                  Inactive
                </span>
              )}
            </div>
            <p className="text-slate-600 dark:text-slate-400 mb-4">
              {employee.position.title} • {employee.department.name}
            </p>
            <div className="flex flex-wrap gap-4 text-sm">
              <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                <CreditCard className="w-4 h-4" />
                {employee.employeeNumber}
              </div>
              <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                <Mail className="w-4 h-4" />
                {employee.contactInfo.email}
              </div>
              <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                <Phone className="w-4 h-4" />
                {employee.contactInfo.mobileNumber}
              </div>
              <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                <Building2 className="w-4 h-4" />
                {employee.workLocation.name}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        {/* Tab Headers */}
        <div className="border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
          <nav className="flex min-w-max">
            {tabs.map((tab) => {
              const Icon = tab.icon
              const isActive = activeTab === tab.id
              return (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => setActiveTab(tab.id)}
                  className={`
                    flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors
                    ${isActive
                      ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                      : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'
                    }
                  `}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                </button>
              )
            })}
          </nav>
        </div>

        {/* Tab Content */}
        <div className="p-6">
          {/* Personal Info Tab */}
          {activeTab === 'personal' && (
            <dl className="max-w-2xl">
              <InfoRow label="Full Name" value={employee.fullName} />
              <InfoRow label="First Name" value={employee.firstName} />
              <InfoRow label="Middle Name" value={employee.middleName} />
              <InfoRow label="Last Name" value={employee.lastName} />
              <InfoRow label="Suffix" value={employee.suffix} />
              <InfoRow label="Birth Date" value={formatDate(employee.birthDate)} />
              <InfoRow label="Age" value={`${employee.age} years old`} />
              <InfoRow label="Gender" value={employee.gender.charAt(0).toUpperCase() + employee.gender.slice(1)} />
              <InfoRow label="Civil Status" value={employee.civilStatus.charAt(0).toUpperCase() + employee.civilStatus.slice(1)} />
              <InfoRow label="Nationality" value={employee.nationality} />
            </dl>
          )}

          {/* Employment Tab */}
          {activeTab === 'employment' && (
            <dl className="max-w-2xl">
              <InfoRow label="Employee Number" value={employee.employeeNumber} />
              <InfoRow label="Employment Status" value={statusLabel} />
              <InfoRow label="Position" value={employee.position.title} />
              <InfoRow label="Department" value={employee.department.name} />
              <InfoRow label="Work Location" value={employee.workLocation.name} />
              <InfoRow label="Supervisor" value={employee.supervisor?.name} />
              <InfoRow label="Date Hired" value={formatDate(employee.dateHired)} />
              <InfoRow label="Regularization Date" value={formatDate(employee.regularizationDate)} />
              <InfoRow label="Years of Service" value={`${employee.yearsOfService} years`} />
              <InfoRow label="Basic Salary" value={formatCurrency(employee.basicSalary)} />
              <InfoRow label="Pay Frequency" value={employee.payFrequency.replace('_', '-')} />
              {employee.separationDate && (
                <>
                  <InfoRow label="Separation Date" value={formatDate(employee.separationDate)} />
                  <InfoRow label="Separation Reason" value={employee.separationReason?.replace('_', ' ')} />
                </>
              )}
            </dl>
          )}

          {/* Government IDs Tab */}
          {activeTab === 'government' && (
            <dl className="max-w-2xl">
              <InfoRow label="TIN" value={employee.tin} />
              <InfoRow label="SSS Number" value={employee.sssNumber} />
              <InfoRow label="PhilHealth Number" value={employee.philhealthNumber} />
              <InfoRow label="Pag-IBIG Number" value={employee.pagibigNumber} />
            </dl>
          )}

          {/* Contact Tab */}
          {activeTab === 'contact' && (
            <dl className="max-w-2xl">
              <InfoRow label="Mobile Number" value={employee.contactInfo.mobileNumber} />
              <InfoRow label="Email" value={employee.contactInfo.email} />
              <InfoRow label="Present Address" value={employee.contactInfo.presentAddress} />
              <InfoRow label="Permanent Address" value={employee.contactInfo.permanentAddress} />
            </dl>
          )}

          {/* Documents Tab */}
          {activeTab === 'documents' && (
            <div>
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  201 File Documents
                </h3>
                <button
                  type="button"
                  onClick={onUploadDocument}
                  className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <Upload className="w-4 h-4" />
                  Upload Document
                </button>
              </div>

              {employeeDocuments.length === 0 ? (
                <div className="text-center py-12 bg-slate-50 dark:bg-slate-700/30 rounded-lg">
                  <FileText className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                  <p className="text-slate-500 dark:text-slate-400">No documents uploaded yet</p>
                  <button
                    type="button"
                    onClick={onUploadDocument}
                    className="mt-3 text-blue-600 dark:text-blue-400 hover:underline text-sm"
                  >
                    Upload the first document
                  </button>
                </div>
              ) : (
                <div className="space-y-3">
                  {employeeDocuments.map((doc) => (
                    <div
                      key={doc.id}
                      className="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-700/30 rounded-lg group hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
                    >
                      <div className="p-2 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-600">
                        <FileText className="w-6 h-6 text-slate-400" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-slate-900 dark:text-slate-100 truncate">
                          {doc.fileName}
                        </p>
                        <div className="flex items-center gap-3 mt-1">
                          <span className={`px-2 py-0.5 rounded text-xs font-medium ${categoryColors[doc.category] || categoryColors.other}`}>
                            {doc.category}
                          </span>
                          <span className="text-xs text-slate-500 dark:text-slate-400">
                            {formatFileSize(doc.fileSize)}
                          </span>
                          <span className="text-xs text-slate-500 dark:text-slate-400">
                            {new Date(doc.uploadedAt).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          type="button"
                          onClick={() => onViewDocument?.(doc.id)}
                          className="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-white dark:hover:bg-slate-800 rounded-lg transition-colors"
                          title="View"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onDownloadDocument?.(doc.id)}
                          className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-white dark:hover:bg-slate-800 rounded-lg transition-colors"
                          title="Download"
                        >
                          <Download className="w-4 h-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onDeleteDocument?.(doc.id)}
                          className="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-white dark:hover:bg-slate-800 rounded-lg transition-colors"
                          title="Delete"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
