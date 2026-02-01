<script setup lang="ts">
import type { SelectSeparatorProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { SelectSeparator, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectSeparatorProps & { class?: HTMLAttributes["class"] }>()

const forwarded = useForwardProps(props)
</script>

<template>
  <SelectSeparator
    v-bind="forwarded"
    :class="cn('bg-muted -mx-1 my-1 h-px', props.class)"
  />
</template>
