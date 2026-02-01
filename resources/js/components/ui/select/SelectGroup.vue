<script setup lang="ts">
import type { SelectGroupProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { SelectGroup, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectGroupProps & { class?: HTMLAttributes["class"] }>()

const forwarded = useForwardProps(props)
</script>

<template>
  <SelectGroup v-bind="forwarded" :class="cn('p-1 w-full', props.class)">
    <slot />
  </SelectGroup>
</template>
