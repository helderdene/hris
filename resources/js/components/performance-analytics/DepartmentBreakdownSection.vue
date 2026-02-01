<script setup lang="ts">
/**
 * DepartmentBreakdownSection Component
 *
 * Displays performance metrics breakdown by department.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Star, Target, BookOpen, CheckCircle } from 'lucide-vue-next';

interface DepartmentItem {
    department: string;
    departmentId: number;
    evaluations: number;
    completedEvaluations: number;
    averageRating: number | null;
    developmentPlans: number;
    goals: number;
    goalsAchieved: number;
}

interface Props {
    data?: DepartmentItem[];
}

const props = defineProps<Props>();

function getRatingColor(rating: number | null): string {
    if (rating === null) return 'text-slate-400';
    if (rating >= 4) return 'text-emerald-500';
    if (rating >= 3) return 'text-blue-500';
    if (rating >= 2) return 'text-amber-500';
    return 'text-red-500';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Building2 class="h-5 w-5" />
                By Department
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <div v-if="data.length > 0" class="space-y-4">
                    <div
                        v-for="dept in data"
                        :key="dept.departmentId"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <h4 class="mb-3 font-semibold text-slate-900 dark:text-slate-100">
                            {{ dept.department }}
                        </h4>

                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <!-- Evaluations -->
                            <div class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-blue-500" />
                                <div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Evaluations</div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ dept.completedEvaluations }}/{{ dept.evaluations }}
                                    </div>
                                </div>
                            </div>

                            <!-- Average Rating -->
                            <div class="flex items-center gap-2">
                                <Star :class="['h-4 w-4', getRatingColor(dept.averageRating)]" />
                                <div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Avg. Rating</div>
                                    <div :class="['font-semibold', getRatingColor(dept.averageRating)]">
                                        {{ dept.averageRating !== null ? dept.averageRating.toFixed(1) : '-' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Development Plans -->
                            <div class="flex items-center gap-2">
                                <BookOpen class="h-4 w-4 text-purple-500" />
                                <div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Dev. Plans</div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ dept.developmentPlans }}
                                    </div>
                                </div>
                            </div>

                            <!-- Goals -->
                            <div class="flex items-center gap-2">
                                <Target class="h-4 w-4 text-emerald-500" />
                                <div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Goals</div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ dept.goalsAchieved }}/{{ dept.goals }} achieved
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Evaluation Completion Bar -->
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>Evaluation Completion</span>
                                <span>
                                    {{ dept.evaluations > 0 ? Math.round((dept.completedEvaluations / dept.evaluations) * 100) : 0 }}%
                                </span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-blue-500 transition-all duration-500"
                                    :style="{ width: dept.evaluations > 0 ? `${(dept.completedEvaluations / dept.evaluations) * 100}%` : '0%' }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                >
                    <p class="text-sm text-slate-500">No department data available</p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
