<template>
  <div class="w-full h-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-emerald-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div class="min-w-0">
          <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Tu equipo</h3>
          <p class="text-[11px] text-emerald-200 truncate">{{ teamName || 'Sin equipo' }}</p>
        </div>
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="i in 4" :key="i" class="h-9 rounded-lg bg-gray-800 animate-pulse"></div>
      </div>
      <div v-else class="flex-1 overflow-y-auto">
        <div v-if="members && members.length" class="space-y-2">
          <div v-for="m in members" :key="m.id" class="flex items-center gap-2 p-2 rounded-lg bg-gray-800/70">
            <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs text-gray-300">
              <img v-if="m.photo" :src="m.photo" class="w-full h-full object-cover" />
              <span v-else>{{ (m.full_name || '?').charAt(0) }}</span>
            </div>
            <span class="text-xs text-gray-200 truncate">{{ m.full_name }}</span>
          </div>
        </div>
        <div v-else class="text-sm text-gray-400">Sin equipo asignado</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
defineProps({
  members: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  teamName: { type: String as PropType<string>, default: 'Sin equipo' },
  loading: { type: Boolean as PropType<boolean>, default: false },
})
</script>
