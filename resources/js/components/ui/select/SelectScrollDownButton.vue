<script setup lang="ts">
import type { SelectScrollDownButtonProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { SelectScrollDownButton, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectScrollDownButtonProps & { class?: HTMLAttributes["class"] }>()

const forwarded = useForwardProps(props)
</script>

<template>
  <SelectScrollDownButton
    v-bind="forwarded"
    :class="cn('flex cursor-default items-center justify-center py-1', props.class)"
  >
    <slot>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="h-4 w-4"
      >
        <path d="m6 9 6 6 6-6" />
      </svg>
    </slot>
  </SelectScrollDownButton>
</template>
