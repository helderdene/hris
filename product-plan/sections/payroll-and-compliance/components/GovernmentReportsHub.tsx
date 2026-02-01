import type {
  GovernmentReportListProps,
  GovernmentReport,
  GovernmentAgency,
  ReportStatus,
  ReportType,
} from '../types'
import { useState } from 'react'

function formatCurrency(amount: number | null): string {
  if (amount === null) return '—'
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(amount)
}

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function formatDateTime(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function getAgencyConfig(agency: GovernmentAgency): { bg: string; text: string; icon: string; label: string; color: string } {
  const configs: Record<GovernmentAgency, { bg: string; text: string; icon: string; label: string; color: string }> = {
    bir: {
      bg: 'bg-red-100 dark:bg-red-900/30',
      text: 'text-red-700 dark:text-red-400',
      icon: 'B',
      label: 'Bureau of Internal Revenue',
      color: 'red',
    },
    sss: {
      bg: 'bg-blue-100 dark:bg-blue-900/30',
      text: 'text-blue-700 dark:text-blue-400',
      icon: 'S',
      label: 'Social Security System',
      color: 'blue',
    },
    philhealth: {
      bg: 'bg-emerald-100 dark:bg-emerald-900/30',
      text: 'text-emerald-700 dark:text-emerald-400',
      icon: 'P',
      label: 'Philippine Health Insurance',
      color: 'emerald',
    },
    pagibig: {
      bg: 'bg-amber-100 dark:bg-amber-900/30',
      text: 'text-amber-700 dark:text-amber-400',
      icon: 'H',
      label: 'Pag-IBIG Fund',
      color: 'amber',
    },
  }
  return configs[agency]
}

function getStatusConfig(status: ReportStatus): { bg: string; text: string; dot: string; label: string } {
  const configs: Record<ReportStatus, { bg: string; text: string; dot: string; label: string }> = {
    pending: {
      bg: 'bg-slate-100 dark:bg-slate-700/50',
      text: 'text-slate-600 dark:text-slate-300',
      dot: 'bg-slate-400',
      label: 'Pending',
    },
    generated: {
      bg: 'bg-blue-100 dark:bg-blue-900/30',
      text: 'text-blue-700 dark:text-blue-400',
      dot: 'bg-blue-500',
      label: 'Generated',
    },
    submitted: {
      bg: 'bg-emerald-100 dark:bg-emerald-900/30',
      text: 'text-emerald-700 dark:text-emerald-400',
      dot: 'bg-emerald-500',
      label: 'Submitted',
    },
    acknowledged: {
      bg: 'bg-violet-100 dark:bg-violet-900/30',
      text: 'text-violet-700 dark:text-violet-400',
      dot: 'bg-violet-500',
      label: 'Acknowledged',
    },
  }
  return configs[status]
}

function getAgencyFromReportType(reportType: ReportType): GovernmentAgency {
  if (reportType.startsWith('bir_')) return 'bir'
  if (reportType.startsWith('sss_')) return 'sss'
  if (reportType.startsWith('philhealth_')) return 'philhealth'
  return 'pagibig'
}

function ReportCard({
  report,
  onGenerate,
  onDownload,
  onSubmit,
}: {
  report: GovernmentReport
  onGenerate?: (reportType: ReportType, periodId?: string) => void
  onDownload?: (id: string) => void
  onSubmit?: (id: string) => void
}) {
  const agency = getAgencyFromReportType(report.reportType)
  const agencyConfig = getAgencyConfig(agency)
  const statusConfig = getStatusConfig(report.status)

  const dueDate = new Date(report.dueDate)
  const today = new Date()
  const daysUntil = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  const isUrgent = daysUntil <= 5 && report.status !== 'submitted' && report.status !== 'acknowledged'

  return (
    <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
      <div className="p-5">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-start gap-3">
            <div className={`w-10 h-10 ${agencyConfig.bg} rounded-lg flex items-center justify-center shrink-0`}>
              <span className={`text-sm font-bold ${agencyConfig.text}`}>{agencyConfig.icon}</span>
            </div>
            <div>
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                {report.reportName}
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {report.periodCovered}
              </p>
            </div>
          </div>
          <div className={`flex items-center gap-1.5 px-2 py-1 rounded-full ${statusConfig.bg}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${statusConfig.dot}`} />
            <span className={`text-xs font-medium ${statusConfig.text}`}>{statusConfig.label}</span>
          </div>
        </div>

        {/* Description */}
        <p className="text-xs text-slate-500 dark:text-slate-400 mb-4 line-clamp-2">
          {report.description}
        </p>

        {/* Details Grid */}
        <div className="grid grid-cols-2 gap-3 mb-4">
          <div>
            <p className="text-xs text-slate-400 dark:text-slate-500">Amount</p>
            <p className="text-sm font-semibold text-slate-900 dark:text-white">
              {formatCurrency(report.totalAmount)}
            </p>
          </div>
          <div>
            <p className="text-xs text-slate-400 dark:text-slate-500">Due Date</p>
            <p className={`text-sm font-semibold ${isUrgent ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'}`}>
              {formatDate(report.dueDate)}
              {isUrgent && daysUntil > 0 && (
                <span className="ml-1 text-xs font-normal">({daysUntil}d)</span>
              )}
              {isUrgent && daysUntil <= 0 && (
                <span className="ml-1 text-xs font-normal">(Overdue)</span>
              )}
            </p>
          </div>
        </div>

        {/* Generated Info */}
        {report.generatedAt && report.generatedBy && (
          <div className="text-xs text-slate-500 dark:text-slate-400 mb-4 pt-3 border-t border-slate-100 dark:border-slate-700/50">
            Generated by <span className="font-medium text-slate-700 dark:text-slate-300">{report.generatedBy.name}</span>
            <br />
            {formatDateTime(report.generatedAt)}
          </div>
        )}
      </div>

      {/* Actions Footer */}
      <div className="px-5 py-3 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
        <div className="flex items-center gap-2">
          {report.status === 'pending' && (
            <button
              onClick={() => onGenerate?.(report.reportType, report.payrollPeriodId ?? undefined)}
              className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
            >
              Generate
            </button>
          )}
          {(report.status === 'generated' || report.status === 'submitted') && (
            <>
              <button
                onClick={() => onDownload?.(report.id)}
                className="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 rounded-lg transition-colors"
              >
                Download
              </button>
              {report.status === 'generated' && (
                <button
                  onClick={() => onSubmit?.(report.id)}
                  className="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                >
                  Mark Submitted
                </button>
              )}
            </>
          )}
          {report.status === 'acknowledged' && (
            <button
              onClick={() => onDownload?.(report.id)}
              className="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 rounded-lg transition-colors"
            >
              Download
            </button>
          )}
        </div>
        {report.fileUrl && (
          <span className="text-xs text-slate-400 dark:text-slate-500">
            {report.fileUrl.split('/').pop()}
          </span>
        )}
      </div>
    </div>
  )
}

const agencyTabs: { key: GovernmentAgency | 'all'; label: string }[] = [
  { key: 'all', label: 'All Agencies' },
  { key: 'bir', label: 'BIR' },
  { key: 'sss', label: 'SSS' },
  { key: 'philhealth', label: 'PhilHealth' },
  { key: 'pagibig', label: 'Pag-IBIG' },
]

const statusTabs: { key: ReportStatus | 'all'; label: string }[] = [
  { key: 'all', label: 'All Status' },
  { key: 'pending', label: 'Pending' },
  { key: 'generated', label: 'Generated' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'acknowledged', label: 'Acknowledged' },
]

export function GovernmentReportsHub({
  reports,
  onGenerate,
  onDownload,
  onSubmit,
  onFilterByAgency,
  onFilterByStatus,
}: GovernmentReportListProps) {
  const [activeAgency, setActiveAgency] = useState<GovernmentAgency | 'all'>('all')
  const [activeStatus, setActiveStatus] = useState<ReportStatus | 'all'>('all')

  const handleAgencyFilter = (agency: GovernmentAgency | 'all') => {
    setActiveAgency(agency)
    onFilterByAgency?.(agency)
  }

  const handleStatusFilter = (status: ReportStatus | 'all') => {
    setActiveStatus(status)
    onFilterByStatus?.(status)
  }

  // Group reports by agency for summary
  const reportsByAgency = reports.reduce((acc, report) => {
    const agency = getAgencyFromReportType(report.reportType)
    if (!acc[agency]) acc[agency] = []
    acc[agency].push(report)
    return acc
  }, {} as Record<GovernmentAgency, GovernmentReport[]>)

  // Count pending reports
  const pendingCount = reports.filter((r) => r.status === 'pending').length
  const generatedCount = reports.filter((r) => r.status === 'generated').length

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/30 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
              Government Reports
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {reports.length} reports
              {pendingCount > 0 && (
                <span className="ml-2 px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded">
                  {pendingCount} pending
                </span>
              )}
              {generatedCount > 0 && (
                <span className="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded">
                  {generatedCount} ready
                </span>
              )}
            </p>
          </div>
        </div>

        {/* Agency Summary Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          {(['bir', 'sss', 'philhealth', 'pagibig'] as GovernmentAgency[]).map((agency) => {
            const config = getAgencyConfig(agency)
            const agencyReports = reportsByAgency[agency] || []
            const totalAmount = agencyReports.reduce((sum, r) => sum + (r.totalAmount || 0), 0)
            const pending = agencyReports.filter((r) => r.status === 'pending').length

            return (
              <button
                key={agency}
                onClick={() => handleAgencyFilter(activeAgency === agency ? 'all' : agency)}
                className={`text-left p-4 rounded-xl border transition-all ${
                  activeAgency === agency
                    ? `border-${config.color}-300 dark:border-${config.color}-700 bg-${config.color}-50/50 dark:bg-${config.color}-900/20 shadow-md`
                    : 'border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800/50 hover:border-slate-200 dark:hover:border-slate-600 shadow-sm hover:shadow-md'
                }`}
              >
                <div className="flex items-center gap-3 mb-3">
                  <div className={`w-8 h-8 ${config.bg} rounded-lg flex items-center justify-center`}>
                    <span className={`text-xs font-bold ${config.text}`}>{config.icon}</span>
                  </div>
                  <span className="text-sm font-semibold text-slate-900 dark:text-white">
                    {agency.toUpperCase()}
                  </span>
                </div>
                <p className="text-lg font-bold text-slate-900 dark:text-white mb-1">
                  {formatCurrency(totalAmount)}
                </p>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  {agencyReports.length} reports
                  {pending > 0 && <span className="ml-1">• {pending} pending</span>}
                </p>
              </button>
            )
          })}
        </div>

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          {/* Agency Tabs */}
          <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0">
            {agencyTabs.map((tab) => (
              <button
                key={tab.key}
                onClick={() => handleAgencyFilter(tab.key)}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                  activeAgency === tab.key
                    ? 'bg-blue-600 text-white'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* Status Tabs */}
          <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0">
            {statusTabs.map((tab) => (
              <button
                key={tab.key}
                onClick={() => handleStatusFilter(tab.key)}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                  activeStatus === tab.key
                    ? 'bg-emerald-600 text-white'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        {/* Reports Grid */}
        {reports.length === 0 ? (
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 p-12 text-center">
            <div className="w-16 h-16 mx-auto mb-4 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">No reports found</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              No government reports match your current filters.
            </p>
          </div>
        ) : (
          <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            {reports.map((report) => (
              <ReportCard
                key={report.id}
                report={report}
                onGenerate={onGenerate}
                onDownload={onDownload}
                onSubmit={onSubmit}
              />
            ))}
          </div>
        )}

        {/* Quick Actions */}
        <div className="mt-8 p-6 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4">Quick Generate</h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <button
              onClick={() => onGenerate?.('bir_1601c')}
              className="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-red-300 dark:hover:border-red-700 hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-all"
            >
              <div className="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                <span className="text-sm font-bold text-red-700 dark:text-red-400">B</span>
              </div>
              <div className="text-left">
                <p className="text-sm font-medium text-slate-900 dark:text-white">BIR 1601-C</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">Monthly Remittance</p>
              </div>
            </button>

            <button
              onClick={() => onGenerate?.('sss_r3')}
              className="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all"
            >
              <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                <span className="text-sm font-bold text-blue-700 dark:text-blue-400">S</span>
              </div>
              <div className="text-left">
                <p className="text-sm font-medium text-slate-900 dark:text-white">SSS R3</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">Collection List</p>
              </div>
            </button>

            <button
              onClick={() => onGenerate?.('philhealth_er2')}
              className="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition-all"
            >
              <div className="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                <span className="text-sm font-bold text-emerald-700 dark:text-emerald-400">P</span>
              </div>
              <div className="text-left">
                <p className="text-sm font-medium text-slate-900 dark:text-white">PhilHealth ER2</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">Remittance Report</p>
              </div>
            </button>

            <button
              onClick={() => onGenerate?.('pagibig_mcrf')}
              className="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-700 hover:bg-amber-50/50 dark:hover:bg-amber-900/10 transition-all"
            >
              <div className="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                <span className="text-sm font-bold text-amber-700 dark:text-amber-400">H</span>
              </div>
              <div className="text-left">
                <p className="text-sm font-medium text-slate-900 dark:text-white">Pag-IBIG MCRF</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">Contribution Form</p>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
