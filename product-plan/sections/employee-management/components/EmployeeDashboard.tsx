import {
  Users,
  UserPlus,
  UserMinus,
  TrendingDown,
  Building2,
  FileText,
  Upload,
  ChevronRight,
} from 'lucide-react'
import type { DashboardProps } from '../types'
import { StatCard } from './StatCard'

export function EmployeeDashboard({
  stats,
  onViewAllEmployees,
  onViewNewHires,
  onViewSeparations,
}: DashboardProps) {
  const tenureData = [
    { label: '< 1 year', value: stats.tenureDistribution.lessThan1Year, color: 'bg-blue-500' },
    { label: '1-3 years', value: stats.tenureDistribution.oneToThreeYears, color: 'bg-blue-400' },
    { label: '3-5 years', value: stats.tenureDistribution.threeToFiveYears, color: 'bg-emerald-500' },
    { label: '5-10 years', value: stats.tenureDistribution.fiveToTenYears, color: 'bg-emerald-400' },
    { label: '> 10 years', value: stats.tenureDistribution.moreThan10Years, color: 'bg-amber-500' },
  ]

  const maxTenure = Math.max(...tenureData.map((d) => d.value))

  const statusData = [
    { label: 'Regular', value: stats.employmentStatusBreakdown.regular, color: 'bg-emerald-500' },
    { label: 'Probationary', value: stats.employmentStatusBreakdown.probationary, color: 'bg-blue-500' },
    { label: 'Contractual', value: stats.employmentStatusBreakdown.contractual, color: 'bg-amber-500' },
    { label: 'Project-based', value: stats.employmentStatusBreakdown.projectBased, color: 'bg-slate-500' },
  ]

  const totalStatus = statusData.reduce((sum, d) => sum + d.value, 0)

  const maxDeptCount = Math.max(...stats.departmentHeadcount.map((d) => d.count))

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
            Employee Dashboard
          </h1>
          <p className="mt-1 text-slate-500 dark:text-slate-400">
            Overview of your workforce metrics and trends
          </p>
        </div>
        <div className="flex gap-3">
          <button
            type="button"
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            <FileText className="w-4 h-4" />
            Export Report
          </button>
          <button
            type="button"
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Upload className="w-4 h-4" />
            Import Employees
          </button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="Total Headcount"
          value={stats.totalHeadcount}
          subtitle={`${stats.activeEmployees} active`}
          icon={<Users className="w-5 h-5" />}
          variant="primary"
          onClick={onViewAllEmployees}
        />
        <StatCard
          title="New Hires"
          value={stats.newHiresThisMonth}
          subtitle="This month"
          icon={<UserPlus className="w-5 h-5" />}
          trend={{ value: 15, label: 'vs last month', isPositive: true }}
          onClick={onViewNewHires}
        />
        <StatCard
          title="Separations"
          value={stats.separationsThisMonth}
          subtitle="This month"
          icon={<UserMinus className="w-5 h-5" />}
          trend={{ value: 8, label: 'vs last month', isPositive: false }}
          onClick={onViewSeparations}
        />
        <StatCard
          title="Turnover Rate"
          value={`${stats.turnoverRate}%`}
          subtitle={`Avg tenure: ${stats.averageTenure} years`}
          icon={<TrendingDown className="w-5 h-5" />}
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Tenure Distribution */}
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">
            Tenure Distribution
          </h3>
          <div className="space-y-4">
            {tenureData.map((item) => (
              <div key={item.label} className="flex items-center gap-4">
                <span className="w-20 text-sm text-slate-600 dark:text-slate-400 flex-shrink-0">
                  {item.label}
                </span>
                <div className="flex-1 h-8 bg-slate-100 dark:bg-slate-700 rounded-lg overflow-hidden">
                  <div
                    className={`h-full ${item.color} transition-all duration-500 ease-out flex items-center justify-end pr-3`}
                    style={{ width: `${(item.value / maxTenure) * 100}%` }}
                  >
                    <span className="text-sm font-medium text-white">
                      {item.value}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Employment Status Breakdown */}
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">
            Employment Status
          </h3>
          {/* Stacked bar */}
          <div className="h-12 flex rounded-lg overflow-hidden mb-6">
            {statusData.map((item) => (
              <div
                key={item.label}
                className={`${item.color} transition-all duration-500`}
                style={{ width: `${(item.value / totalStatus) * 100}%` }}
                title={`${item.label}: ${item.value}`}
              />
            ))}
          </div>
          {/* Legend */}
          <div className="grid grid-cols-2 gap-4">
            {statusData.map((item) => (
              <div key={item.label} className="flex items-center gap-3">
                <div className={`w-3 h-3 rounded-full ${item.color}`} />
                <div>
                  <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
                    {item.value}
                  </p>
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    {item.label}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Department Headcount */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Department Headcount
          </h3>
          <button
            type="button"
            className="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium inline-flex items-center gap-1"
          >
            View Organization Chart
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          {stats.departmentHeadcount.map((dept) => (
            <div
              key={dept.department}
              className="relative bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 overflow-hidden group hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
            >
              {/* Background bar */}
              <div
                className="absolute bottom-0 left-0 right-0 bg-blue-500/10 dark:bg-blue-400/10 transition-all duration-500"
                style={{ height: `${(dept.count / maxDeptCount) * 100}%` }}
              />
              <div className="relative">
                <div className="flex items-center gap-2 mb-2">
                  <Building2 className="w-4 h-4 text-slate-400" />
                  <span className="text-sm text-slate-600 dark:text-slate-300 truncate">
                    {dept.department}
                  </span>
                </div>
                <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                  {dept.count}
                </p>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  employees
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Quick Actions */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">
          Quick Actions
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <button
            type="button"
            onClick={onViewAllEmployees}
            className="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors text-left group"
          >
            <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 group-hover:bg-blue-200 dark:group-hover:bg-blue-900 transition-colors">
              <Users className="w-5 h-5" />
            </div>
            <div>
              <p className="font-medium text-slate-900 dark:text-slate-100">
                View All Employees
              </p>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Browse employee directory
              </p>
            </div>
          </button>
          <button
            type="button"
            className="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors text-left group"
          >
            <div className="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900 transition-colors">
              <UserPlus className="w-5 h-5" />
            </div>
            <div>
              <p className="font-medium text-slate-900 dark:text-slate-100">
                Add New Employee
              </p>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Create employee record
              </p>
            </div>
          </button>
          <button
            type="button"
            className="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors text-left group"
          >
            <div className="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 group-hover:bg-amber-200 dark:group-hover:bg-amber-900 transition-colors">
              <Building2 className="w-5 h-5" />
            </div>
            <div>
              <p className="font-medium text-slate-900 dark:text-slate-100">
                Manage Departments
              </p>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Edit organization structure
              </p>
            </div>
          </button>
          <button
            type="button"
            className="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left group"
          >
            <div className="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-600 transition-colors">
              <FileText className="w-5 h-5" />
            </div>
            <div>
              <p className="font-medium text-slate-900 dark:text-slate-100">
                Generate Reports
              </p>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Export employee data
              </p>
            </div>
          </button>
        </div>
      </div>
    </div>
  )
}
