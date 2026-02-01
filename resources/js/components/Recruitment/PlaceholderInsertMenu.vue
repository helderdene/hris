<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus } from 'lucide-vue-next';

const emit = defineEmits<{
    insert: [placeholder: string];
}>();

const placeholders = [
    { label: 'Candidate Name', value: '{{candidate_name}}' },
    { label: 'Position', value: '{{position}}' },
    { label: 'Salary', value: '{{salary}}' },
    { label: 'Salary Frequency', value: '{{salary_frequency}}' },
    { label: 'Start Date', value: '{{start_date}}' },
    { label: 'Benefits', value: '{{benefits}}' },
    { label: 'Department', value: '{{department}}' },
    { label: 'Work Location', value: '{{work_location}}' },
    { label: 'Employment Type', value: '{{employment_type}}' },
    { label: 'Company Name', value: '{{company_name}}' },
    { label: 'Expiry Date', value: '{{expiry_date}}' },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button type="button" variant="outline" size="sm" class="gap-1">
                <Plus class="h-3.5 w-3.5" />
                Insert Placeholder
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
            <DropdownMenuItem
                v-for="p in placeholders"
                :key="p.value"
                @click="emit('insert', p.value)"
            >
                <span class="mr-2 font-mono text-xs text-muted-foreground">{{ p.value }}</span>
                {{ p.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
