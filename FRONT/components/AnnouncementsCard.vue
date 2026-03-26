<template>
  <div class="w-full h-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-fuchsia-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-fuchsia-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-fuchsia-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5h8M11 9h8M11 13h8M3 5h.01M3 9h.01M3 13h.01M4 21h16a1 1 0 001-1V4a1 1 0 00-1-1H4a1 1 0 00-1 1v16a1 1 0 001 1z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Comunicados</h3>
      </div>

      <div v-if="loading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-14 rounded-lg bg-gray-800 animate-pulse"></div>
      </div>
      <div v-else class="flex-1 overflow-y-auto space-y-2">
        <div v-for="a in items" :key="a.id" class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-sm text-white truncate">{{ a.title }}</div>
          <div class="text-[11px] text-gray-400 truncate">{{ a.author }}</div>
        </div>
        <div v-if="!items?.length" class="text-sm text-gray-400">Sin comunicados</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
defineProps({
  items: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
})
</script>

