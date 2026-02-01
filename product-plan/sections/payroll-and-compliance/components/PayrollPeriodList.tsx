import type {
  PayrollPeriodListProps,
  PayrollPeriod,
  PayrollPeriodStatus,
  PayrollPeriodType,
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

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function getStatusConfig(status: PayrollPeriodStatus): { bg: string; text: string; dot: string; label: string } {
  const configs: Record<PayrollPeriodStatus, { bg: string; text: string; dot: string; label: string }> = {
    draft: {
      bg: 'bg-slate-100 dark:bg-slate-700/50',
      text: 'text-slate-700 dark:text-slate-300',
      dot: 'bg-slate-400',
      label: 'Draft',
    },
    processing: {
      bg: 'bg-amber-100 dark:bg-amber-900/30',
      text: 'text-amber-700 dark:text-amber-400',
      dot: 'bg-amber-500 animate-pulse',
      label: 'Processing',
    },
    approved: {
      bg: 'bg-blue-100 dark:bg-blue-900/30',
      text: 'text-blue-700 dark:text-blue-400',
      dot: 'bg-blue-500',
      label: 'Approved',
    },
    paid: {
      bg: 'bg-emerald-100 dark:bg-emerald-900/30',
      text: 'text-emerald-700 dark:text-emerald-400',
      dot: 'bg-emerald-500',
      label: 'Paid',
    },
    closed: {
      bg: 'bg-violet-100 dark:bg-violet-900/30',
      text: 'text-violet-700 dark:text-violet-400',
      dot: 'bg-violet-500',
      label: 'Closed',
    },
  }
  return configs[status]
}

function getPeriodTypeLabel(type: PayrollPeriodType): string {
  const labels: Record<PayrollPeriodType, string> = {
    regular: 'Regular',
    supplemental: 'Supplemental',
    '13th_month': '13th Month',
    final_pay: 'Final Pay',
  }
  return labels[type]
}

function PeriodCard({
  period,
  onView,
  onProcess,
  onApprove,
  onClose,
}: {
  period: PayrollPeriod
  onView?: (id: string) => void
  onProcess?: (id: string) => void
  onApprove?: (id: string) => void
  onClose?: (id: string) => void
}) {
  const statusConfig = getStatusConfig(period.status)

  return (
    <div className="group bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800/50 transition-all overflow-hidden">
      <div className="p-5">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <h3 className="text-lg font-bold text-slate-900 dark:text-white tracking-tight">
                {period.periodCode}
              </h3>
              <span className="px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 rounded">
                {getPeriodTypeLabel(period.periodType)}
              </span>
            </div>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {formatDate(period.startDate)} — {formatDate(period.endDate)}
            </p>
          </div>
          <div className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full ${statusConfig.bg}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${statusConfig.dot}`} />
            <span className={`text-xs font-semibold ${statusConfig.text}`}>{statusConfig.label}</span>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-3 gap-4 mb-4">
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Employees</p>
            <p className="text-sm font-semibold text-slate-900 dark:text-white">
              {period.processedCount} / {period.employeeCount}
            </p>
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Gross Pay</p>
            <p className="text-sm font-semibold text-slate-900 dark:text-white">
              {formatCurrency(period.totalGrossPay)}
            </p>
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Net Pay</p>
            <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
              {formatCurrency(period.totalNetPay)}
            </p>
          </div>
        </div>

        {/* Progress Bar */}
        {period.employeeCount > 0 && (
          <div className="mb-4">
            <div className="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div
                className="h-full bg-blue-500 dark:bg-blue-400 rounded-full transition-all duration-500"
                style={{ width: `${(period.processedCount / period.employeeCount) * 100}%` }}
              />
            </div>
          </div>
        )}

        {/* Dates Row */}
        <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
          <div className="flex items-center gap-1.5">
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Cutoff: {formatDate(period.cutoffDate)}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Pay: {formatDate(period.payDate)}</span>
          </div>
        </div>
      </div>

      {/* Actions Footer */}
      <div className="px-5 py-3 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
        <button
          onClick={() => onView?.(period.id)}
          className="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
        >
          View Details
        </button>
        <div className="flex items-center gap-2">
          {period.status === 'draft' && (
            <button
              onClick={() => onProcess?.(period.id)}
              className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
            >
              Process
            </button>
          )}
          {period.status === 'processing' && (
            <button
              onClick={() => onApprove?.(period.id)}
              className="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
            >
              Approve
            </button>
          )}
          {period.status === 'paid' && (
            <button
              onClick={() => onClose?.(period.id)}
              className="px-3 py-1.5 text-xs font-medium text-violet-700 dark:text-violet-300 bg-violet-100 dark:bg-violet-900/30 hover:bg-violet-200 dark:hover:bg-violet-900/50 rounded-lg transition-colors"
            >
              Close Period
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

export function PayrollPeriodList({
  periods,
  onView,
  onCreate,
  onProcess,
  onApprove,
  onClose,
  onFilterByStatus,
  onFilterByType,
}: PayrollPeriodListProps) {
  const [activeStatus, setActiveStatus] = useState<PayrollPeriodStatus | 'all'>('all')
  const [activeType, setActiveType] = useState<PayrollPeriodType | 'all'>('all')

  const handleStatusFilter = (status: PayrollPeriodStatus | 'all') => {
    setActiveStatus(status)
    onFilterByStatus?.(status)
  }

  const handleTypeFilter = (type: PayrollPeriodType | 'all') => {
    setActiveType(type)
    onFilterByType?.(type)
  }

  const statuses: (PayrollPeriodStatus | 'all')[] = ['all', 'draft', 'processing', 'approved', 'paid', 'closed']
  const types: (PayrollPeriodType | 'all')[] = ['all', 'regular', 'supplemental', '13th_month', 'final_pay']

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/30 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
              Payroll Periods
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {periods.length} total periods
            </p>
          </div>
          <button
            onClick={() => onCreate?.()}
            className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm shadow-blue-200 dark:shadow-blue-900/30"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Create Period
          </button>
        </div>

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          {/* Status Filter */}
          <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0">
            {statuses.map((status) => (
              <button
                key={status}
                onClick={() => handleStatusFilter(status)}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                  activeStatus === status
                    ? 'bg-blue-600 text-white'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'
                }`}
              >
                {status === 'all' ? 'All Status' : getStatusConfig(status).label}
              </button>
            ))}
          </div>

          {/* Type Filter */}
          <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0">
            {types.map((type) => (
              <button
                key={type}
                onClick={() => handleTypeFilter(type)}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                  activeType === type
                    ? 'bg-emerald-600 text-white'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'
                }`}
              >
                {type === 'all' ? 'All Types' : getPeriodTypeLabel(type)}
              </button>
            ))}
          </div>
        </div>

        {/* Period Cards Grid */}
        {periods.length === 0 ? (
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 p-12 text-center">
            <div className="w-16 h-16 mx-auto mb-4 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">No payroll periods</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
              Create your first payroll period to get started with payroll processing.
            </p>
            <button
              onClick={() => onCreate?.()}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Create Period
            </button>
          </div>
        ) : (
          <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            {periods.map((period) => (
              <PeriodCard
                key={period.id}
                period={period}
                onView={onView}
                onProcess={onProcess}
                onApprove={onApprove}
                onClose={onClose}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
