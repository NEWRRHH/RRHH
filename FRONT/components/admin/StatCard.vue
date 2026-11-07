<template>
  <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
    <div class="text-xs text-gray-400">{{ label }}</div>
    <div class="mt-1 text-2xl font-semibold text-white tabular-nums">
      <span v-if="loading" class="inline-block h-7 w-16 rounded bg-gray-800 animate-pulse align-middle"></span>
      <template v-else>{{ value }}</template>
    </div>
    <div v-if="hint && !loading" class="mt-1 text-xs" :class="tone === 'warning' ? 'text-amber-300' : 'text-gray-500'">
      <span v-if="tone === 'warning'" aria-hidden="true">⚠ </span>{{ hint }}
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  hint?: string
  tone?: 'neutral' | 'warning'
  loading?: boolean
}>()
</script>
