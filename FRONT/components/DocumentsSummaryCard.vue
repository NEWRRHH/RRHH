<template>
  <div class="w-full h-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-indigo-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7V3h10v4m-9 4h8m-8 4h8m-9 6h10a2 2 0 002-2V7H5v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Documentos</h3>
      </div>
      <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Medicos</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.medical ?? 0 }}</div>
        </div>
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Comprobantes</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.receipt ?? 0 }}</div>
        </div>
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Nominas</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.payroll ?? 0 }}</div>
        </div>
        <div class="rounded-lg bg-gray-800/70 px-3 py-2">
          <div class="text-gray-400">Total</div>
          <div class="text-white text-lg font-bold tabular-nums">{{ summary.total ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ summary: { medical?: number; receipt?: number; payroll?: number; total?: number } }>()
</script>

