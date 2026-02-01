import { useState } from 'react'
import type { Payslip } from '../types'

// =============================================================================
// Component Props
// =============================================================================

export interface PayslipViewerProps {
  payslips: Payslip[]
  onViewPayslip?: (id: string) => void
  onDownloadPayslip?: (id: string) => void
  onBack?: () => void
}

// =============================================================================
// Utility Functions
// =============================================================================

function formatCurrency(amount: number): string {
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

function formatDateRange(start: string, end: string): string {
  const startDate = new Date(start)
  const endDate = new Date(end)
  const startMonth = startDate.toLocaleDateString('en-PH', { month: 'short' })
  const endMonth = endDate.toLocaleDateString('en-PH', { month: 'short' })

  if (startMonth === endMonth) {
    return `${startMonth} ${startDate.getDate()}-${endDate.getDate()}, ${startDate.getFullYear()}`
  }
  return `${formatDate(start)} - ${formatDate(end)}`
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'paid':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'approved':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'processing':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    case 'draft':
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
  }
}

// =============================================================================
// Sub-components
// =============================================================================

interface PayslipCardProps {
  payslip: Payslip
  isSelected: boolean
  onSelect: () => void
}

function PayslipCard({ payslip, isSelected, onSelect }: PayslipCardProps) {
  return (
    <button
      onClick={onSelect}
      className={`w-full text-left p-4 rounded-xl border-2 transition-all duration-200 ${
        isSelected
          ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
          : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
      }`}
    >
      <div className="flex items-start justify-between mb-2">
        <div>
          <p className="font-semibold text-slate-900 dark:text-white">{payslip.periodCode}</p>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            {formatDateRange(payslip.startDate, payslip.endDate)}
          </p>
        </div>
        <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getStatusColor(payslip.status)}`}>
          {payslip.status}
        </span>
      </div>
      <div className="flex items-baseline justify-between">
        <span className="text-xs text-slate-400 dark:text-slate-500">Net Pay</span>
        <span className="text-lg font-bold text-slate-900 dark:text-white font-mono">
          {formatCurrency(payslip.netPay)}
        </span>
      </div>
    </button>
  )
}

interface PayslipDetailProps {
  payslip: Payslip
  onDownload?: () => void
}

function PayslipDetail({ payslip, onDownload }: PayslipDetailProps) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      {/* Header */}
      <div className="p-6 border-b border-slate-100 dark:border-slate-800 bg-gradient-to-r from-blue-600 to-blue-700">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-blue-100 text-sm mb-1">Payslip</p>
            <h2 className="text-2xl font-bold text-white">{payslip.periodCode}</h2>
            <p className="text-blue-200 text-sm mt-1">
              {formatDateRange(payslip.startDate, payslip.endDate)}
            </p>
          </div>
          <button
            onClick={onDownload}
            className="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors text-sm font-medium"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Download PDF
          </button>
        </div>
      </div>

      {/* Summary */}
      <div className="p-6 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
        <div className="grid grid-cols-3 gap-4">
          <div className="text-center p-4 bg-white dark:bg-slate-900 rounded-xl">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Gross Pay</p>
            <p className="text-xl font-bold text-slate-900 dark:text-white font-mono">
              {formatCurrency(payslip.grossPay)}
            </p>
          </div>
          <div className="text-center p-4 bg-white dark:bg-slate-900 rounded-xl">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Deductions</p>
            <p className="text-xl font-bold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.totalDeductions)}
            </p>
          </div>
          <div className="text-center p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border-2 border-emerald-200 dark:border-emerald-800">
            <p className="text-sm text-emerald-600 dark:text-emerald-400 mb-1">Net Pay</p>
            <p className="text-xl font-bold text-emerald-700 dark:text-emerald-300 font-mono">
              {formatCurrency(payslip.netPay)}
            </p>
          </div>
        </div>
      </div>

      {/* Earnings */}
      <div className="p-6 border-b border-slate-100 dark:border-slate-800">
        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">
          Earnings
        </h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3">
              <div className="w-2 h-2 rounded-full bg-blue-500" />
              <span className="text-slate-700 dark:text-slate-300">Basic Pay</span>
            </div>
            <span className="font-semibold text-slate-900 dark:text-white font-mono">
              {formatCurrency(payslip.basicPay)}
            </span>
          </div>
          {payslip.overtimePay > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-2 h-2 rounded-full bg-amber-500" />
                <span className="text-slate-700 dark:text-slate-300">Overtime Pay</span>
              </div>
              <span className="font-semibold text-slate-900 dark:text-white font-mono">
                {formatCurrency(payslip.overtimePay)}
              </span>
            </div>
          )}
          {payslip.nightDiffPay > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-2 h-2 rounded-full bg-purple-500" />
                <span className="text-slate-700 dark:text-slate-300">Night Differential</span>
              </div>
              <span className="font-semibold text-slate-900 dark:text-white font-mono">
                {formatCurrency(payslip.nightDiffPay)}
              </span>
            </div>
          )}
          {payslip.holidayPay > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-2 h-2 rounded-full bg-rose-500" />
                <span className="text-slate-700 dark:text-slate-300">Holiday Pay</span>
              </div>
              <span className="font-semibold text-slate-900 dark:text-white font-mono">
                {formatCurrency(payslip.holidayPay)}
              </span>
            </div>
          )}
          {payslip.allowances > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-2 h-2 rounded-full bg-emerald-500" />
                <span className="text-slate-700 dark:text-slate-300">Allowances</span>
              </div>
              <span className="font-semibold text-slate-900 dark:text-white font-mono">
                {formatCurrency(payslip.allowances)}
              </span>
            </div>
          )}
          <div className="flex items-center justify-between py-3 border-t border-slate-200 dark:border-slate-700 mt-2">
            <span className="font-semibold text-slate-900 dark:text-white">Total Earnings</span>
            <span className="font-bold text-lg text-slate-900 dark:text-white font-mono">
              {formatCurrency(payslip.grossPay)}
            </span>
          </div>
        </div>
      </div>

      {/* Deductions */}
      <div className="p-6 border-b border-slate-100 dark:border-slate-800">
        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">
          Deductions
        </h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <span className="text-xs font-bold text-blue-600 dark:text-blue-400">SSS</span>
              </div>
              <span className="text-slate-700 dark:text-slate-300">SSS Contribution</span>
            </div>
            <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.deductions.sss)}
            </span>
          </div>
          <div className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400">PH</span>
              </div>
              <span className="text-slate-700 dark:text-slate-300">PhilHealth</span>
            </div>
            <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.deductions.philhealth)}
            </span>
          </div>
          <div className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                <span className="text-xs font-bold text-orange-600 dark:text-orange-400">PI</span>
              </div>
              <span className="text-slate-700 dark:text-slate-300">Pag-IBIG</span>
            </div>
            <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.deductions.pagibig)}
            </span>
          </div>
          <div className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                <span className="text-xs font-bold text-purple-600 dark:text-purple-400">TX</span>
              </div>
              <span className="text-slate-700 dark:text-slate-300">Withholding Tax</span>
            </div>
            <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.deductions.withholdingTax)}
            </span>
          </div>
          {payslip.deductions.sssLoan > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                  <span className="text-xs font-bold text-slate-600 dark:text-slate-400">LN</span>
                </div>
                <span className="text-slate-700 dark:text-slate-300">SSS Loan</span>
              </div>
              <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
                -{formatCurrency(payslip.deductions.sssLoan)}
              </span>
            </div>
          )}
          {payslip.deductions.others > 0 && (
            <div className="flex items-center justify-between py-2">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                  <span className="text-xs font-bold text-slate-600 dark:text-slate-400">OT</span>
                </div>
                <span className="text-slate-700 dark:text-slate-300">Other Deductions</span>
              </div>
              <span className="font-semibold text-red-600 dark:text-red-400 font-mono">
                -{formatCurrency(payslip.deductions.others)}
              </span>
            </div>
          )}
          <div className="flex items-center justify-between py-3 border-t border-slate-200 dark:border-slate-700 mt-2">
            <span className="font-semibold text-slate-900 dark:text-white">Total Deductions</span>
            <span className="font-bold text-lg text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(payslip.totalDeductions)}
            </span>
          </div>
        </div>
      </div>

      {/* Work Summary */}
      <div className="p-6">
        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">
          Work Summary
        </h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-center">
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{payslip.daysWorked}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400">Days Worked</p>
          </div>
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-center">
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{payslip.hoursWorked}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400">Hours Worked</p>
          </div>
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-center">
            <p className="text-sm font-medium text-slate-900 dark:text-white">{formatDate(payslip.payDate)}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400">Pay Date</p>
          </div>
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-center">
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(payslip.status)}`}>
              {payslip.status}
            </span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Status</p>
          </div>
        </div>
      </div>
    </div>
  )
}

// =============================================================================
// Main Component
// =============================================================================

export function PayslipViewer({
  payslips,
  onViewPayslip,
  onDownloadPayslip,
  onBack,
}: PayslipViewerProps) {
  const [selectedPayslipId, setSelectedPayslipId] = useState<string>(payslips[0]?.id || '')
  const [yearFilter, setYearFilter] = useState<string>('all')

  const years = [...new Set(payslips.map(p => new Date(p.payDate).getFullYear()))].sort((a, b) => b - a)

  const filteredPayslips = yearFilter === 'all'
    ? payslips
    : payslips.filter(p => new Date(p.payDate).getFullYear().toString() === yearFilter)

  const selectedPayslip = payslips.find(p => p.id === selectedPayslipId)

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-4 mb-4">
            <button
              onClick={onBack}
              className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                Payslips
              </h1>
              <p className="mt-1 text-slate-500 dark:text-slate-400">
                View and download your payslips
              </p>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Payslip List */}
          <div className="lg:col-span-1">
            <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div className="p-4 border-b border-slate-100 dark:border-slate-800">
                <div className="flex items-center gap-2">
                  <label className="text-sm text-slate-500 dark:text-slate-400">Filter by year:</label>
                  <select
                    value={yearFilter}
                    onChange={(e) => setYearFilter(e.target.value)}
                    className="flex-1 px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    <option value="all">All Years</option>
                    {years.map(year => (
                      <option key={year} value={year.toString()}>{year}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                {filteredPayslips.map((payslip) => (
                  <PayslipCard
                    key={payslip.id}
                    payslip={payslip}
                    isSelected={payslip.id === selectedPayslipId}
                    onSelect={() => {
                      setSelectedPayslipId(payslip.id)
                      onViewPayslip?.(payslip.id)
                    }}
                  />
                ))}
                {filteredPayslips.length === 0 && (
                  <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                    <svg className="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p>No payslips found</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Payslip Detail */}
          <div className="lg:col-span-2">
            {selectedPayslip ? (
              <PayslipDetail
                payslip={selectedPayslip}
                onDownload={() => onDownloadPayslip?.(selectedPayslip.id)}
              />
            ) : (
              <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-12 text-center">
                <svg className="w-16 h-16 mx-auto mb-4 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p className="text-slate-500 dark:text-slate-400">Select a payslip to view details</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
