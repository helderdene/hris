import type {
  PayrollRecordListProps,
  PayrollRecord,
} from '../types'
import { useState } from 'react'

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(amount)
}

function RecordRow({
  record,
  onView,
  onViewPayslip,
}: {
  record: PayrollRecord
  onView?: () => void
  onViewPayslip?: () => void
}) {
  const [expanded, setExpanded] = useState(false)

  return (
    <div className="bg-white dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
      {/* Main Row */}
      <div className="p-4 sm:p-5">
        <div className="flex flex-col sm:flex-row sm:items-center gap-4">
          {/* Employee Info */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0">
                {record.employee.fullName.split(' ').map((n) => n[0]).slice(0, 2).join('')}
              </div>
              <div className="min-w-0">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white truncate">
                  {record.employee.fullName}
                </h3>
                <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                  <span>{record.employee.employeeNumber}</span>
                  <span className="w-1 h-1 bg-slate-300 dark:bg-slate-600 rounded-full" />
                  <span className="truncate">{record.employee.department}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Pay Summary */}
          <div className="grid grid-cols-3 gap-4 sm:gap-6 text-right sm:text-left">
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Gross Pay</p>
              <p className="text-sm font-semibold text-slate-900 dark:text-white">
                {formatCurrency(record.grossPay)}
              </p>
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Deductions</p>
              <p className="text-sm font-semibold text-red-600 dark:text-red-400">
                -{formatCurrency(record.totalDeductions)}
              </p>
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Net Pay</p>
              <p className="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                {formatCurrency(record.netPay)}
              </p>
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center gap-2 sm:ml-4">
            <button
              onClick={() => setExpanded(!expanded)}
              className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
              title={expanded ? 'Collapse' : 'Expand'}
            >
              <svg
                className={`w-5 h-5 transition-transform ${expanded ? 'rotate-180' : ''}`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <button
              onClick={onViewPayslip}
              className="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
              title="View Payslip"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </button>
            <button
              onClick={onView}
              className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
              title="View Details"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Expanded Details */}
      {expanded && (
        <div className="px-4 sm:px-5 pb-5 pt-0">
          <div className="pt-4 border-t border-slate-100 dark:border-slate-700/50">
            <div className="grid sm:grid-cols-2 gap-6">
              {/* Earnings Breakdown */}
              <div>
                <h4 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                  Earnings
                </h4>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600 dark:text-slate-300">Basic Pay</span>
                    <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.basicPay)}</span>
                  </div>
                  {record.overtimePay > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Overtime ({record.overtimeHours}h)</span>
                      <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.overtimePay)}</span>
                    </div>
                  )}
                  {record.nightDiffPay > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Night Diff ({record.nightDiffHours}h)</span>
                      <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.nightDiffPay)}</span>
                    </div>
                  )}
                  {record.holidayPay > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Holiday Pay</span>
                      <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.holidayPay)}</span>
                    </div>
                  )}
                  {record.restDayPay > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Rest Day Pay</span>
                      <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.restDayPay)}</span>
                    </div>
                  )}
                  {record.allowances > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Allowances</span>
                      <span className="font-medium text-slate-900 dark:text-white">{formatCurrency(record.allowances)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-sm pt-2 border-t border-slate-100 dark:border-slate-700/50">
                    <span className="font-semibold text-slate-700 dark:text-slate-200">Total Earnings</span>
                    <span className="font-bold text-slate-900 dark:text-white">{formatCurrency(record.grossPay)}</span>
                  </div>
                </div>
              </div>

              {/* Deductions Breakdown */}
              <div>
                <h4 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                  Deductions
                </h4>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600 dark:text-slate-300">SSS</span>
                    <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.sssDeduction)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600 dark:text-slate-300">PhilHealth</span>
                    <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.philhealthDeduction)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600 dark:text-slate-300">Pag-IBIG</span>
                    <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.pagibigDeduction)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600 dark:text-slate-300">Withholding Tax</span>
                    <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.withholdingTax)}</span>
                  </div>
                  {record.loanDeductions > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Loan Deductions</span>
                      <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.loanDeductions)}</span>
                    </div>
                  )}
                  {record.otherDeductions > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600 dark:text-slate-300">Other Deductions</span>
                      <span className="font-medium text-red-600 dark:text-red-400">-{formatCurrency(record.otherDeductions)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-sm pt-2 border-t border-slate-100 dark:border-slate-700/50">
                    <span className="font-semibold text-slate-700 dark:text-slate-200">Total Deductions</span>
                    <span className="font-bold text-red-600 dark:text-red-400">-{formatCurrency(record.totalDeductions)}</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Work Summary */}
            <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
              <div className="flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400">
                <div className="flex items-center gap-1.5">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span>{record.daysWorked} days worked</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>{record.hoursWorked} hours</span>
                </div>
                {record.overtimeHours > 0 && (
                  <div className="flex items-center gap-1.5">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>{record.overtimeHours}h OT</span>
                  </div>
                )}
                {record.nightDiffHours > 0 && (
                  <div className="flex items-center gap-1.5">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span>{record.nightDiffHours}h ND</span>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export function PayrollRecordList({
  records,
  period,
  onView,
  onViewPayslip,
  onFilterByDepartment,
  onSearch,
  onExport,
}: PayrollRecordListProps) {
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedDepartment, setSelectedDepartment] = useState<string>('all')

  // Get unique departments
  const departments = [...new Set(records.map((r) => r.employee.department).filter(Boolean))]

  const handleSearch = (query: string) => {
    setSearchQuery(query)
    onSearch?.(query)
  }

  const handleDepartmentFilter = (department: string) => {
    setSelectedDepartment(department)
    onFilterByDepartment?.(department === 'all' ? 'all' : department)
  }

  // Calculate totals
  const totals = records.reduce(
    (acc, record) => ({
      grossPay: acc.grossPay + record.grossPay,
      deductions: acc.deductions + record.totalDeductions,
      netPay: acc.netPay + record.netPay,
    }),
    { grossPay: 0, deductions: 0, netPay: 0 }
  )

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/30 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
              Payroll Records
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Period: <span className="font-semibold text-blue-600 dark:text-blue-400">{period.periodCode}</span>
              <span className="mx-2">•</span>
              {records.length} employees
            </p>
          </div>
          <button
            onClick={() => onExport?.()}
            className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-sm"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export
          </button>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Gross Pay</p>
            <p className="text-lg font-bold text-slate-900 dark:text-white">{formatCurrency(totals.grossPay)}</p>
          </div>
          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Deductions</p>
            <p className="text-lg font-bold text-red-600 dark:text-red-400">-{formatCurrency(totals.deductions)}</p>
          </div>
          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Net Pay</p>
            <p className="text-lg font-bold text-emerald-600 dark:text-emerald-400">{formatCurrency(totals.netPay)}</p>
          </div>
        </div>

        {/* Search & Filters */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          {/* Search */}
          <div className="relative flex-1">
            <svg
              className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder="Search by name or employee number..."
              value={searchQuery}
              onChange={(e) => handleSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400 outline-none transition-shadow text-slate-900 dark:text-white placeholder-slate-400"
            />
          </div>

          {/* Department Filter */}
          <select
            value={selectedDepartment}
            onChange={(e) => handleDepartmentFilter(e.target.value)}
            className="px-4 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400 outline-none text-slate-900 dark:text-white"
          >
            <option value="all">All Departments</option>
            {departments.map((dept) => (
              <option key={dept} value={dept}>
                {dept}
              </option>
            ))}
          </select>
        </div>

        {/* Records List */}
        {records.length === 0 ? (
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 p-12 text-center">
            <div className="w-16 h-16 mx-auto mb-4 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">No payroll records</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              No records found for this payroll period. Process the payroll to generate records.
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {records.map((record) => (
              <RecordRow
                key={record.id}
                record={record}
                onView={() => onView?.(record.id)}
                onViewPayslip={() => onViewPayslip?.(record.id)}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
