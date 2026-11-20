<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="relative w-7 h-7 rounded-lg bg-indigo-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7V3h10v4m-9 4h8m-8 4h8m-9 6h10a2 2 0 002-2V7H5v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Documentos</h3>
        <!-- new payslips / documents (real time) -->
        <NuxtLink
          v-if="unread > 0"
          to="/documentos?tab=payroll"
          class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white shadow shadow-red-900/40 hover:bg-red-500 badge-pop"
          :title="`${unread} documento(s) nuevo(s)`"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse motion-reduce:animate-none" aria-hidden="true"></span>
          {{ unread }} {{ unread === 1 ? 'nuevo' : 'nuevos' }}
        </NuxtLink>
      </div>
      <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Médicos</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.medical ?? 0 }}</div>
        </div>
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Comprobantes</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.receipt ?? 0 }}</div>
        </div>
        <NuxtLink
          to="/documentos?tab=payroll"
          class="relative rounded-lg px-3 py-2 transition"
          :class="unread > 0 ? 'bg-red-500/10 ring-1 ring-red-500/40 hover:bg-red-500/15' : 'bg-gray-800/70 hover:bg-gray-800'"
        >
          <div class="text-gray-400">Nóminas</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.payroll ?? 0 }}</div>
          <span v-if="unread > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500" aria-hidden="true"></span>
        </NuxtLink>
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Total</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.total ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineProps<{ summary: { medical?: number; receipt?: number; payroll?: number; total?: number; unread?: number } }>()

// shared, pushed over WebSocket (DocumentsUpdated)
const { unreadDocuments } = useAuth()
const unread = computed(() => unreadDocuments.value || 0)
</script>

<style scoped>
.badge-pop { animation: badge-pop 0.4s ease-out; }
@keyframes badge-pop {
  0% { transform: scale(0.6); }
  70% { transform: scale(1.15); }
  100% { transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) { .badge-pop { animation: none; } }
</style>
