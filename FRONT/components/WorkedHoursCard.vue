<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl pointer-events-none"></div>

      <div class="flex items-center gap-2 mb-4">
        <div class="w-7 h-7 rounded-lg bg-cyan-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-cyan-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Horas trabajadas</h3>
      </div>

      <div class="space-y-3 h-full flex flex-col">
        <div class="rounded-xl bg-cyan-500/5 border border-cyan-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Hoy</div>
          <div class="text-3xl font-extrabold text-cyan-200 leading-none tabular-nums">{{ workedHHMM || '--:--' }}</div>
        </div>

        <div class="grid grid-cols-2 gap-3 mt-auto">
          <div class="rounded-xl bg-cyan-500/5 border border-cyan-500/10 px-4 py-3">
            <div class="text-xs text-gray-400">Mes trabajado</div>
            <div class="text-xl font-bold text-cyan-200 tabular-nums">{{ monthWorkedHHMM || '--:--' }}</div>
          </div>

          <div class="rounded-xl bg-cyan-500/5 border border-cyan-500/10 px-4 py-3">
            <div class="text-xs text-gray-400">Objetivo mensual</div>
            <div class="text-xl font-bold text-cyan-200 tabular-nums">{{ monthTargetHHMM || '--:--' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  workedHHMM?: string | null
  monthWorkedHHMM?: string | null
  monthTargetHHMM?: string | null
}>()
</script>
