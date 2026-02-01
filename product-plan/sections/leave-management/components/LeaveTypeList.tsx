import type { LeaveTypeListProps, LeaveType, LeaveBalance } from '../types'

const colorMap: Record<string, { bg: string; text: string; border: string; accent: string }> = {
  blue: { bg: 'bg-blue-50 dark:bg-blue-950/30', text: 'text-blue-600 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800', accent: 'bg-blue-500' },
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-950/30', text: 'text-emerald-600 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800', accent: 'bg-emerald-500' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-950/30', text: 'text-amber-600 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800', accent: 'bg-amber-500' },
  pink: { bg: 'bg-pink-50 dark:bg-pink-950/30', text: 'text-pink-600 dark:text-pink-400', border: 'border-pink-200 dark:border-pink-800', accent: 'bg-pink-500' },
  sky: { bg: 'bg-sky-50 dark:bg-sky-950/30', text: 'text-sky-600 dark:text-sky-400', border: 'border-sky-200 dark:border-sky-800', accent: 'bg-sky-500' },
  violet: { bg: 'bg-violet-50 dark:bg-violet-950/30', text: 'text-violet-600 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800', accent: 'bg-violet-500' },
  rose: { bg: 'bg-rose-50 dark:bg-rose-950/30', text: 'text-rose-600 dark:text-rose-400', border: 'border-rose-200 dark:border-rose-800', accent: 'bg-rose-500' },
  fuchsia: { bg: 'bg-fuchsia-50 dark:bg-fuchsia-950/30', text: 'text-fuchsia-600 dark:text-fuchsia-400', border: 'border-fuchsia-200 dark:border-fuchsia-800', accent: 'bg-fuchsia-500' },
  slate: { bg: 'bg-slate-50 dark:bg-slate-950/30', text: 'text-slate-600 dark:text-slate-400', border: 'border-slate-200 dark:border-slate-800', accent: 'bg-slate-500' },
}

function LeaveTypeCard({
  leaveType,
  balance,
  onSelect,
  onViewDetails,
}: {
  leaveType: LeaveType
  balance?: LeaveBalance
  onSelect?: () => void
  onViewDetails?: () => void
}) {
  const colors = colorMap[leaveType.color] || colorMap.slate
  const available = balance?.available ?? leaveType.defaultCredits

  return (
    <div className={`relative overflow-hidden rounded-xl border ${colors.border} bg-white dark:bg-slate-800/50 transition-all hover:shadow-lg group`}>
      {/* Color accent bar */}
      <div className={`absolute top-0 left-0 right-0 h-1 ${colors.accent}`} />

      <div className="p-5">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className={`w-12 h-12 rounded-xl ${colors.bg} ${colors.text} flex items-center justify-center font-bold text-lg`}>
              {leaveType.code}
            </div>
            <div>
              <h3 className="font-semibold text-slate-900 dark:text-white">{leaveType.name}</h3>
              <div className="flex items-center gap-2 mt-0.5">
                {leaveType.isStatutory && (
                  <span className="text-[10px] font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 px-1.5 py-0.5 rounded">
                    Statutory
                  </span>
                )}
                {leaveType.isPaid ? (
                  <span className="text-[10px] font-medium bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 px-1.5 py-0.5 rounded">
                    Paid
                  </span>
                ) : (
                  <span className="text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded">
                    Unpaid
                  </span>
                )}
              </div>
            </div>
          </div>
          {balance && (
            <div className="text-right">
              <p className={`text-2xl font-bold ${colors.text}`}>{available}</p>
              <p className="text-xs text-slate-500 dark:text-slate-400">available</p>
            </div>
          )}
        </div>

        {/* Description */}
        <p className="text-sm text-slate-600 dark:text-slate-300 mb-4 line-clamp-2">
          {leaveType.description}
        </p>

        {/* Details Grid */}
        <div className="grid grid-cols-2 gap-3 mb-4">
          <div className="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-2.5">
            <p className="text-xs text-slate-500 dark:text-slate-400">Default Credits</p>
            <p className="font-semibold text-slate-900 dark:text-white">{leaveType.defaultCredits} days</p>
          </div>
          <div className="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-2.5">
            <p className="text-xs text-slate-500 dark:text-slate-400">Max Accumulation</p>
            <p className="font-semibold text-slate-900 dark:text-white">
              {leaveType.maxAccumulation ? `${leaveType.maxAccumulation} days` : 'Unlimited'}
            </p>
          </div>
        </div>

        {/* Features */}
        <div className="flex flex-wrap gap-2 mb-4">
          {leaveType.isConvertible && (
            <span className="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/50 px-2 py-1 rounded-full">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Convertible to cash
            </span>
          )}
          {leaveType.isCumulative && (
            <span className="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/50 px-2 py-1 rounded-full">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
              </svg>
              Cumulative
            </span>
          )}
          {leaveType.requiresDocument && (
            <span className="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/50 px-2 py-1 rounded-full">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Requires document
            </span>
          )}
          {leaveType.eligibleGender && (
            <span className="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/50 px-2 py-1 rounded-full">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {leaveType.eligibleGender === 'female' ? 'Female only' : 'Male only'}
            </span>
          )}
        </div>

        {/* Eligibility */}
        {leaveType.eligibilityMonths > 0 && (
          <div className="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400 mb-4">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Requires {leaveType.eligibilityMonths} months of service
          </div>
        )}

        {/* Actions */}
        <div className="flex items-center gap-2 pt-4 border-t border-slate-100 dark:border-slate-700">
          <button
            onClick={onSelect}
            className={`flex-1 py-2 px-4 rounded-lg font-medium text-sm transition-colors ${colors.bg} ${colors.text} hover:opacity-80`}
          >
            File This Leave
          </button>
          <button
            onClick={onViewDetails}
            className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 dark:hover:text-slate-300 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  )
}

export function LeaveTypeList({ leaveTypes, balances, onSelectType, onViewDetails }: LeaveTypeListProps) {
  const getBalance = (typeId: string) => balances.find(b => b.leaveTypeId === typeId)

  // Group by statutory vs company-provided
  const statutoryTypes = leaveTypes.filter(t => t.isStatutory)
  const companyTypes = leaveTypes.filter(t => !t.isStatutory)

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Leave Types</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Browse available leave types and their eligibility requirements
        </p>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">Total Types</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white">{leaveTypes.length}</p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">Statutory Leaves</p>
          <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{statutoryTypes.length}</p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">Company-Provided</p>
          <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{companyTypes.length}</p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">Total Available</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white">
            {balances.reduce((sum, b) => sum + b.available, 0)} days
          </p>
        </div>
      </div>

      {/* Statutory Leaves */}
      {statutoryTypes.length > 0 && (
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <svg className="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Statutory Leaves</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">Mandated by Philippine labor law</p>
            </div>
          </div>
          <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            {statutoryTypes.map(type => (
              <LeaveTypeCard
                key={type.id}
                leaveType={type}
                balance={getBalance(type.id)}
                onSelect={() => onSelectType?.(type.id)}
                onViewDetails={() => onViewDetails?.(type.id)}
              />
            ))}
          </div>
        </div>
      )}

      {/* Company-Provided Leaves */}
      {companyTypes.length > 0 && (
        <div>
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
              <svg className="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Company-Provided Leaves</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">Additional benefits from your employer</p>
            </div>
          </div>
          <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            {companyTypes.map(type => (
              <LeaveTypeCard
                key={type.id}
                leaveType={type}
                balance={getBalance(type.id)}
                onSelect={() => onSelectType?.(type.id)}
                onViewDetails={() => onViewDetails?.(type.id)}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
