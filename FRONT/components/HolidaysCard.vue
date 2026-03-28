<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-orange-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-orange-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Proximos festivos</h3>
      </div>
      <div v-if="loading" class="space-y-2">
        <div v-for="i in 4" :key="i" class="h-10 rounded-lg bg-gray-800 animate-pulse"></div>
      </div>
      <div v-else class="holiday-scroll space-y-2 pr-1 max-h-40 overflow-y-auto">
        <div v-for="h in holidays" :key="`${h.date}-${h.name}`" class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-sm text-white">{{ h.name }}</div>
          <div class="text-xs text-gray-400">{{ formatDate(h.date) }}</div>
        </div>
        <div v-if="!holidays?.length" class="text-sm text-gray-400">No hay festivos proximos</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
const props = defineProps({
  holidays: { type: Array as PropType<Array<{ date: string; name: string }>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
})
const formatDate = (iso: string) => {
  try {
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return iso
  }
}
</script>

<style scoped>
.holiday-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(251, 146, 60, 0.7) rgba(31, 41, 55, 0.45);
}

.holiday-scroll::-webkit-scrollbar {
  width: 8px;
}

.holiday-scroll::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.45);
  border-radius: 9999px;
}

.holiday-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, rgba(253, 186, 116, 0.95) 0%, rgba(249, 115, 22, 0.95) 100%);
  border-radius: 9999px;
  border: 1px solid rgba(17, 24, 39, 0.5);
}

.holiday-scroll::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, rgba(254, 215, 170, 1) 0%, rgba(251, 146, 60, 1) 100%);
}
</style>
