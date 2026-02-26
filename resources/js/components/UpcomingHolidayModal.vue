<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { CalendarHeart, Briefcase, TreePalm } from 'lucide-vue-next';

export interface UpcomingHoliday {
    id: number;
    name: string;
    date: string;
    holiday_type: string;
    holiday_type_label: string;
    is_working: boolean;
}

const props = defineProps<{
    holidays: UpcomingHoliday[];
}>();

const isOpen = ref(false);
const undismissedHolidays = ref<UpcomingHoliday[]>([]);

function dismissalKey(date: string): string {
    return `dismissed-holiday-${date}`;
}

onMounted(() => {
    undismissedHolidays.value = props.holidays.filter(
        (h) => !localStorage.getItem(dismissalKey(h.date)),
    );
    if (undismissedHolidays.value.length > 0) {
        isOpen.value = true;
    }
});

function dismiss(): void {
    undismissedHolidays.value.forEach((h) => {
        localStorage.setItem(dismissalKey(h.date), '1');
    });
    isOpen.value = false;
}

const title = computed(() => {
    const count = undismissedHolidays.value.length;
    return count === 1 ? 'Upcoming Holiday' : 'Upcoming Holidays';
});

const description = computed(() => {
    const count = undismissedHolidays.value.length;
    return count === 1
        ? 'A holiday is coming up in the next few days.'
        : `${count} holidays are coming up in the next few days.`;
});

interface TypeStyle {
    bg: string;
    text: string;
    dot: string;
}

function typeStyle(holidayType: string): TypeStyle {
    const map: Record<string, TypeStyle> = {
        regular: {
            bg: 'bg-red-50 dark:bg-red-950/30',
            text: 'text-red-700 dark:text-red-400',
            dot: 'bg-red-500 dark:bg-red-400',
        },
        special_non_working: {
            bg: 'bg-amber-50 dark:bg-amber-950/30',
            text: 'text-amber-700 dark:text-amber-400',
            dot: 'bg-amber-500 dark:bg-amber-400',
        },
        special_working: {
            bg: 'bg-sky-50 dark:bg-sky-950/30',
            text: 'text-sky-700 dark:text-sky-400',
            dot: 'bg-sky-500 dark:bg-sky-400',
        },
        double: {
            bg: 'bg-purple-50 dark:bg-purple-950/30',
            text: 'text-purple-700 dark:text-purple-400',
            dot: 'bg-purple-500 dark:bg-purple-400',
        },
    };
    return (
        map[holidayType] ?? {
            bg: 'bg-slate-50 dark:bg-slate-800/40',
            text: 'text-slate-600 dark:text-slate-400',
            dot: 'bg-slate-400 dark:bg-slate-500',
        }
    );
}
</script>

<template>
    <Dialog
        :open="isOpen"
        @update:open="
            (val: boolean) => {
                if (!val) dismiss();
            }
        "
    >
        <DialogContent :show-close-button="false" class="gap-0 overflow-hidden p-0 sm:max-w-sm">
            <!-- Header band -->
            <div
                class="relative bg-gradient-to-br from-blue-50 via-sky-50 to-indigo-100 px-5 pt-5 pb-4 dark:from-blue-950/40 dark:via-sky-950/30 dark:to-indigo-950/50"
            >
                <div
                    class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-blue-200/40 blur-2xl dark:bg-blue-500/10"
                />
                <DialogHeader class="relative gap-1.5">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400 dark:shadow-lg dark:shadow-blue-500/10"
                        >
                            <CalendarHeart class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ title }}
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ description }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
            </div>

            <!-- Holiday list -->
            <div class="flex flex-col gap-2 px-5 pt-4 pb-2">
                <div
                    v-for="holiday in undismissedHolidays"
                    :key="holiday.id"
                    class="rounded-lg border border-slate-200/70 bg-white px-3.5 py-3 dark:border-slate-700/60 dark:bg-slate-800/50"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ holiday.name }}
                            </p>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ holiday.date }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5 pt-0.5">
                            <span
                                :class="[
                                    'inline-block h-1.5 w-1.5 rounded-full',
                                    typeStyle(holiday.holiday_type).dot,
                                ]"
                            />
                            <span
                                :class="[
                                    'text-xs font-medium',
                                    typeStyle(holiday.holiday_type).text,
                                ]"
                            >
                                {{ holiday.holiday_type_label }}
                            </span>
                        </div>
                    </div>

                    <!-- Working / Non-working indicator -->
                    <div class="mt-2 flex items-center gap-1.5">
                        <component
                            :is="holiday.is_working ? Briefcase : TreePalm"
                            :class="[
                                'h-3 w-3',
                                holiday.is_working
                                    ? 'text-sky-500 dark:text-sky-400'
                                    : 'text-emerald-500 dark:text-emerald-400',
                            ]"
                        />
                        <span
                            :class="[
                                'text-xs font-medium',
                                holiday.is_working
                                    ? 'text-sky-600 dark:text-sky-400'
                                    : 'text-emerald-600 dark:text-emerald-400',
                            ]"
                        >
                            {{ holiday.is_working ? 'Working Day' : 'Non-Working Day' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="px-5 pt-2 pb-5">
                <Button class="w-full" @click="dismiss"> Got it </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
