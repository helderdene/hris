<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    method: string;
    label?: string;
}>();

const methodConfig = computed(() => {
    switch (props.method) {
        case 'in_person':
            return {
                label: props.label || 'In-Person',
                bgClass: 'bg-blue-100 dark:bg-blue-900/30',
                textClass: 'text-blue-700 dark:text-blue-400',
                icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            };
        case 'virtual':
            return {
                label: props.label || 'Virtual',
                bgClass: 'bg-purple-100 dark:bg-purple-900/30',
                textClass: 'text-purple-700 dark:text-purple-400',
                icon: 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25',
            };
        case 'e_learning':
            return {
                label: props.label || 'E-Learning',
                bgClass: 'bg-teal-100 dark:bg-teal-900/30',
                textClass: 'text-teal-700 dark:text-teal-400',
                icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
            };
        case 'blended':
            return {
                label: props.label || 'Blended',
                bgClass: 'bg-indigo-100 dark:bg-indigo-900/30',
                textClass: 'text-indigo-700 dark:text-indigo-400',
                icon: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
            };
        default:
            return {
                label: props.label || props.method,
                bgClass: 'bg-slate-100 dark:bg-slate-800',
                textClass: 'text-slate-700 dark:text-slate-300',
                icon: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
            };
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
            methodConfig.bgClass,
            methodConfig.textClass,
        ]"
    >
        <svg
            class="h-3 w-3"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                :d="methodConfig.icon"
            />
        </svg>
        {{ methodConfig.label }}
    </span>
</template>
