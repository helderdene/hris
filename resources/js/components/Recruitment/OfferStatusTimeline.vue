<script setup lang="ts">
import { Check, Clock, Eye, Mail, Send, X, AlertTriangle, Ban } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface TimelineEvent {
    status: string;
    label: string;
    timestamp: string | null;
    color: string;
}

const props = defineProps<{
    offer: {
        status: string;
        created_at: string | null;
        sent_at: string | null;
        viewed_at: string | null;
        accepted_at: string | null;
        declined_at: string | null;
        expired_at: string | null;
        revoked_at: string | null;
    };
}>();

const iconMap: Record<string, Component> = {
    draft: Clock,
    sent: Send,
    viewed: Eye,
    accepted: Check,
    declined: X,
    expired: AlertTriangle,
    revoked: Ban,
};

const colorMap: Record<string, string> = {
    draft: 'text-slate-500 bg-slate-100 dark:bg-slate-800',
    pending: 'text-amber-500 bg-amber-100 dark:bg-amber-900',
    sent: 'text-blue-500 bg-blue-100 dark:bg-blue-900',
    viewed: 'text-purple-500 bg-purple-100 dark:bg-purple-900',
    accepted: 'text-green-500 bg-green-100 dark:bg-green-900',
    declined: 'text-red-500 bg-red-100 dark:bg-red-900',
    expired: 'text-orange-500 bg-orange-100 dark:bg-orange-900',
    revoked: 'text-rose-500 bg-rose-100 dark:bg-rose-900',
};

const events = computed<TimelineEvent[]>(() => {
    const items: TimelineEvent[] = [
        { status: 'draft', label: 'Created', timestamp: props.offer.created_at, color: 'draft' },
    ];

    if (props.offer.sent_at) {
        items.push({ status: 'sent', label: 'Sent to Candidate', timestamp: props.offer.sent_at, color: 'sent' });
    }

    if (props.offer.viewed_at) {
        items.push({ status: 'viewed', label: 'Viewed by Candidate', timestamp: props.offer.viewed_at, color: 'viewed' });
    }

    if (props.offer.accepted_at) {
        items.push({ status: 'accepted', label: 'Accepted', timestamp: props.offer.accepted_at, color: 'accepted' });
    }

    if (props.offer.declined_at) {
        items.push({ status: 'declined', label: 'Declined', timestamp: props.offer.declined_at, color: 'declined' });
    }

    if (props.offer.expired_at) {
        items.push({ status: 'expired', label: 'Expired', timestamp: props.offer.expired_at, color: 'expired' });
    }

    if (props.offer.revoked_at) {
        items.push({ status: 'revoked', label: 'Revoked', timestamp: props.offer.revoked_at, color: 'revoked' });
    }

    return items;
});

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-0">
        <div
            v-for="(event, index) in events"
            :key="event.status"
            class="relative flex gap-3 pb-6 last:pb-0"
        >
            <!-- Connecting line -->
            <div
                v-if="index < events.length - 1"
                class="absolute left-[15px] top-8 h-[calc(100%-16px)] w-px bg-border"
            />

            <!-- Icon -->
            <div
                class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="colorMap[event.color] ?? colorMap.draft"
            >
                <component :is="iconMap[event.status] ?? Clock" class="h-4 w-4" />
            </div>

            <!-- Content -->
            <div class="pt-1">
                <p class="text-sm font-medium">{{ event.label }}</p>
                <p v-if="event.timestamp" class="text-xs text-muted-foreground">
                    {{ formatDate(event.timestamp) }}
                </p>
            </div>
        </div>
    </div>
</template>
