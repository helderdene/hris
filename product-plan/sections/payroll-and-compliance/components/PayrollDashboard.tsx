import type { PayrollDashboardProps, UpcomingDeadline } from '../types'

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

function getAgencyColor(agency: string): string {
  const colors: Record<string, string> = {
    bir: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    sss: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    philhealth: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    pagibig: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  }
  return colors[agency] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
}

function getAgencyLabel(agency: string): string {
  const labels: Record<string, string> = {
    bir: 'BIR',
    sss: 'SSS',
    philhealth: 'PhilHealth',
    pagibig: 'Pag-IBIG',
  }
  return labels[agency] || agency.toUpperCase()
}

function DeadlineCard({ deadline }: { deadline: UpcomingDeadline }) {
  const dueDate = new Date(deadline.dueDate)
  const today = new Date()
  const daysUntil = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  const isUrgent = daysUntil <= 3

  return (
    <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-700/50 last:border-0">
      <div className="flex items-center gap-3">
        <span className={`px-2 py-0.5 text-xs font-semibold rounded ${getAgencyColor(deadline.agency)}`}>
          {getAgencyLabel(deadline.agency)}
        </span>
        <div>
          <p className="text-sm font-medium text-slate-800 dark:text-slate-200">{deadline.title}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">Due {formatDate(deadline.dueDate)}</p>
        </div>
      </div>
      <div className="flex items-center gap-2">
        {isUrgent && (
          <span className="px-2 py-0.5 text-xs font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 rounded">
            {daysUntil <= 0 ? 'Overdue' : `${daysUntil}d left`}
          </span>
        )}
        <span className={`w-2 h-2 rounded-full ${deadline.status === 'pending' ? 'bg-amber-400' : 'bg-emerald-400'}`} />
      </div>
    </div>
  )
}

export function PayrollDashboard({
  stats,
  onViewPeriod,
  onCreatePeriod,
  onProcessPayroll,
  onViewReports,
}: PayrollDashboardProps) {
  const maxGross = Math.max(...stats.monthlyTrend.map((m) => m.grossPay))

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/30 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
              Payroll Dashboard
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Current Period: <span className="font-semibold text-blue-600 dark:text-blue-400">{stats.currentPeriod}</span>
            </p>
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => onViewReports?.()}
              className="px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-sm"
            >
              View Reports
            </button>
            <button
              onClick={() => onProcessPayroll?.()}
              className="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm shadow-blue-200 dark:shadow-blue-900/30"
            >
              Process Payroll
            </button>
          </div>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-5 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <div className="flex items-center gap-3 mb-3">
              <div className="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                <svg className="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <span className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total Employees</span>
            </div>
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{stats.totalEmployees}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{stats.processedEmployees} processed this period</p>
          </div>

          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-5 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <div className="flex items-center gap-3 mb-3">
              <div className="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg">
                <svg className="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <span className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Last Gross Pay</span>
            </div>
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{formatCurrency(stats.lastPayrollRun.totalGrossPay)}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{stats.lastPayrollRun.periodCode}</p>
          </div>

          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-5 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <div className="flex items-center gap-3 mb-3">
              <div className="p-2 bg-violet-50 dark:bg-violet-900/30 rounded-lg">
                <svg className="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
              </div>
              <span className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Last Net Pay</span>
            </div>
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{formatCurrency(stats.lastPayrollRun.totalNetPay)}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{stats.lastPayrollRun.employeesProcessed} employees paid</p>
          </div>

          <div className="bg-white dark:bg-slate-800/50 rounded-xl p-5 border border-slate-100 dark:border-slate-700/50 shadow-sm">
            <div className="flex items-center gap-3 mb-3">
              <div className="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                <svg className="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <span className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Pending Periods</span>
            </div>
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{stats.pendingPeriods}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{stats.periodsThisMonth} periods this month</p>
          </div>
        </div>

        <div className="grid lg:grid-cols-3 gap-4">
          {/* Monthly Trend Chart */}
          <div className="lg:col-span-2 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50">
              <h2 className="font-semibold text-slate-900 dark:text-white">Payroll Trend</h2>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Gross vs Net pay over recent months</p>
            </div>
            <div className="p-6">
              <div className="flex items-end gap-4 h-48">
                {stats.monthlyTrend.map((month, index) => {
                  const grossHeight = (month.grossPay / maxGross) * 100
                  const netHeight = (month.netPay / maxGross) * 100
                  return (
                    <div key={index} className="flex-1 flex flex-col items-center gap-2">
                      <div className="w-full flex items-end justify-center gap-1 h-40">
                        <div
                          className="w-5 bg-blue-500/80 dark:bg-blue-400/80 rounded-t transition-all duration-500"
                          style={{ height: `${grossHeight}%` }}
                          title={`Gross: ${formatCurrency(month.grossPay)}`}
                        />
                        <div
                          className="w-5 bg-emerald-500/80 dark:bg-emerald-400/80 rounded-t transition-all duration-500"
                          style={{ height: `${netHeight}%` }}
                          title={`Net: ${formatCurrency(month.netPay)}`}
                        />
                      </div>
                      <span className="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{month.month.split(' ')[0]}</span>
                    </div>
                  )
                })}
              </div>
              <div className="flex items-center justify-center gap-6 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-blue-500 rounded" />
                  <span className="text-xs text-slate-600 dark:text-slate-400">Gross Pay</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-emerald-500 rounded" />
                  <span className="text-xs text-slate-600 dark:text-slate-400">Net Pay</span>
                </div>
              </div>
            </div>
          </div>

          {/* Upcoming Deadlines */}
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
              <div>
                <h2 className="font-semibold text-slate-900 dark:text-white">Upcoming Deadlines</h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Government remittances</p>
              </div>
              <span className="px-2 py-1 text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded">
                {stats.upcomingDeadlines.filter((d) => d.status === 'pending').length} pending
              </span>
            </div>
            <div className="px-6 py-2">
              {stats.upcomingDeadlines.map((deadline, index) => (
                <DeadlineCard key={index} deadline={deadline} />
              ))}
            </div>
            <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30">
              <button
                onClick={() => onViewReports?.()}
                className="w-full text-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
              >
                View All Reports
              </button>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <button
            onClick={() => onCreatePeriod?.()}
            className="group flex items-center gap-4 p-5 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:border-blue-200 dark:hover:border-blue-800 hover:shadow-md transition-all"
          >
            <div className="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
              <svg className="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
            </div>
            <div className="text-left">
              <p className="font-medium text-slate-900 dark:text-white">New Period</p>
              <p className="text-xs text-slate-500 dark:text-slate-400">Create payroll period</p>
            </div>
          </button>

          <button
            onClick={() => onProcessPayroll?.()}
            className="group flex items-center gap-4 p-5 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:border-emerald-200 dark:hover:border-emerald-800 hover:shadow-md transition-all"
          >
            <div className="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/50 transition-colors">
              <svg className="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
            <div className="text-left">
              <p className="font-medium text-slate-900 dark:text-white">Process Payroll</p>
              <p className="text-xs text-slate-500 dark:text-slate-400">Run computation</p>
            </div>
          </button>

          <button
            onClick={() => onViewPeriod?.(stats.lastPayrollRun.periodCode)}
            className="group flex items-center gap-4 p-5 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:border-violet-200 dark:hover:border-violet-800 hover:shadow-md transition-all"
          >
            <div className="p-3 bg-violet-50 dark:bg-violet-900/30 rounded-xl group-hover:bg-violet-100 dark:group-hover:bg-violet-900/50 transition-colors">
              <svg className="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div className="text-left">
              <p className="font-medium text-slate-900 dark:text-white">View Records</p>
              <p className="text-xs text-slate-500 dark:text-slate-400">Last payroll details</p>
            </div>
          </button>

          <button
            onClick={() => onViewReports?.()}
            className="group flex items-center gap-4 p-5 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm hover:border-amber-200 dark:hover:border-amber-800 hover:shadow-md transition-all"
          >
            <div className="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-xl group-hover:bg-amber-100 dark:group-hover:bg-amber-900/50 transition-colors">
              <svg className="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div className="text-left">
              <p className="font-medium text-slate-900 dark:text-white">Government Reports</p>
              <p className="text-xs text-slate-500 dark:text-slate-400">BIR, SSS, PhilHealth</p>
            </div>
          </button>
        </div>
      </div>
    </div>
  )
}
