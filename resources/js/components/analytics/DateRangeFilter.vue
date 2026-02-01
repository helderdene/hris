<script setup lang="ts">
/**
 * DateRangeFilter Component
 *
 * Provides date range filtering with preset options.
 */
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Calendar, ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const startDate = defineModel<string>('startDate', { default: '' });
const endDate = defineModel<string>('endDate', { default: '' });

const showCustom = ref(false);
const customStartDate = ref('');
const customEndDate = ref('');

type PresetKey = 'last30' | 'quarter' | 'year' | 'custom';

interface Preset {
    label: string;
    getRange: () => { start: string; end: string };
}

const presets: Record<PresetKey, Preset> = {
    last30: {
        label: 'Last 30 Days',
        getRange: () => {
            const end = new Date();
            const start = new Date();
            start.setDate(start.getDate() - 30);
            return {
                start: start.toISOString().split('T')[0],
                end: end.toISOString().split('T')[0],
            };
        },
    },
    quarter: {
        label: 'This Quarter',
        getRange: () => {
            const now = new Date();
            const quarter = Math.floor(now.getMonth() / 3);
            const start = new Date(now.getFullYear(), quarter * 3, 1);
            const end = new Date(now.getFullYear(), quarter * 3 + 3, 0);
            return {
                start: start.toISOString().split('T')[0],
                end: end.toISOString().split('T')[0],
            };
        },
    },
    year: {
        label: 'This Year',
        getRange: () => {
            const now = new Date();
            return {
                start: `${now.getFullYear()}-01-01`,
                end: `${now.getFullYear()}-12-31`,
            };
        },
    },
    custom: {
        label: 'Custom Range',
        getRange: () => ({ start: '', end: '' }),
    },
};

const activePreset = ref<PresetKey | null>(null);

const displayLabel = computed(() => {
    if (activePreset.value && activePreset.value !== 'custom') {
        return presets[activePreset.value].label;
    }
    if (startDate.value && endDate.value) {
        return `${startDate.value} - ${endDate.value}`;
    }
    if (startDate.value) {
        return `From ${startDate.value}`;
    }
    if (endDate.value) {
        return `Until ${endDate.value}`;
    }
    return 'Last 30 Days';
});

function selectPreset(key: PresetKey) {
    activePreset.value = key;

    if (key === 'custom') {
        showCustom.value = true;
        return;
    }

    showCustom.value = false;
    const range = presets[key].getRange();
    startDate.value = range.start;
    endDate.value = range.end;
}

function applyCustomRange() {
    if (customStartDate.value && customEndDate.value) {
        startDate.value = customStartDate.value;
        endDate.value = customEndDate.value;
        showCustom.value = false;
    }
}

// Initialize with last 30 days if no dates set
if (!startDate.value && !endDate.value) {
    selectPreset('last30');
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" class="gap-2">
                <Calendar class="h-4 w-4" />
                <span class="hidden sm:inline">{{ displayLabel }}</span>
                <ChevronDown class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem
                v-for="(preset, key) in presets"
                :key="key"
                :class="{ 'bg-slate-100 dark:bg-slate-800': activePreset === key }"
                @click="selectPreset(key as PresetKey)"
            >
                {{ preset.label }}
            </DropdownMenuItem>

            <template v-if="showCustom">
                <DropdownMenuSeparator />
                <div class="p-2">
                    <div class="flex flex-col gap-2">
                        <div>
                            <label
                                class="mb-1 block text-xs text-slate-500 dark:text-slate-400"
                            >
                                Start Date
                            </label>
                            <Input
                                v-model="customStartDate"
                                type="date"
                                class="h-8"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs text-slate-500 dark:text-slate-400"
                            >
                                End Date
                            </label>
                            <Input
                                v-model="customEndDate"
                                type="date"
                                class="h-8"
                            />
                        </div>
                        <Button
                            size="sm"
                            class="mt-1"
                            @click="applyCustomRange"
                        >
                            Apply
                        </Button>
                    </div>
                </div>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
