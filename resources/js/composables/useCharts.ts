import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    type ChartOptions,
} from 'chart.js';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

/**
 * Common color palette for charts
 */
export const chartColors = {
    primary: '#3b82f6', // blue-500
    secondary: '#64748b', // slate-500
    success: '#22c55e', // green-500
    warning: '#f59e0b', // amber-500
    danger: '#ef4444', // red-500
    info: '#06b6d4', // cyan-500
    purple: '#8b5cf6', // purple-500
    pink: '#ec4899', // pink-500
    indigo: '#6366f1', // indigo-500
    teal: '#14b8a6', // teal-500
};

/**
 * Color palette for pie/doughnut charts
 */
export const pieChartColors = [
    '#3b82f6', // blue
    '#22c55e', // green
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // purple
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#14b8a6', // teal
];

/**
 * Get default line chart options with dark mode support
 */
export function useLineChartOptions(isDark = false): ChartOptions<'line'> {
    const textColor = isDark ? '#e2e8f0' : '#334155';
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    padding: 16,
                },
            },
            tooltip: {
                backgroundColor: isDark ? '#1e293b' : '#ffffff',
                titleColor: textColor,
                bodyColor: textColor,
                borderColor: gridColor,
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            },
        },
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    color: textColor,
                },
            },
            y: {
                grid: {
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                },
            },
        },
    };
}

/**
 * Get default bar chart options with dark mode support
 */
export function useBarChartOptions(isDark = false): ChartOptions<'bar'> {
    const textColor = isDark ? '#e2e8f0' : '#334155';
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: isDark ? '#1e293b' : '#ffffff',
                titleColor: textColor,
                bodyColor: textColor,
                borderColor: gridColor,
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            },
        },
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    color: textColor,
                },
            },
            y: {
                grid: {
                    color: gridColor,
                },
                ticks: {
                    color: textColor,
                },
                beginAtZero: true,
            },
        },
    };
}

/**
 * Get default doughnut/pie chart options with dark mode support
 */
export function useDoughnutChartOptions(isDark = false): ChartOptions<'doughnut'> {
    const textColor = isDark ? '#e2e8f0' : '#334155';
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    padding: 16,
                },
            },
            tooltip: {
                backgroundColor: isDark ? '#1e293b' : '#ffffff',
                titleColor: textColor,
                bodyColor: textColor,
                borderColor: gridColor,
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            },
        },
    };
}

/**
 * Check if the user prefers dark mode
 */
export function useDarkMode(): boolean {
    if (typeof window === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
}
