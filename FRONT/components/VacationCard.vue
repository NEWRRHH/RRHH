<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-teal-500/10 rounded-full blur-2xl pointer-events-none"></div>

      <div class="flex items-center gap-2 mb-4">
        <div class="w-7 h-7 rounded-lg bg-teal-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Vacaciones</h3>
      </div>

      <div v-if="loading" class="space-y-3 animate-pulse">
        <div class="h-14 rounded-xl bg-gray-800"></div>
        <div class="h-16 rounded-xl bg-gray-800"></div>
      </div>

      <div v-else class="flex flex-col gap-3 relative">
        <!-- only when there are requested vacations ahead -->
        <div v-if="hasUpcoming" class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Próximas vacaciones</div>
          <div class="text-3xl font-extrabold text-teal-300 leading-none tabular-nums mt-1">
            {{ daysUntilVacation === 0 ? 'Hoy' : `en ${daysText(daysUntilVacation!)}` }}
          </div>
        </div>

        <div class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Días de vacaciones restantes</div>
          <div class="flex items-baseline gap-2 mt-1">
            <span class="text-3xl font-extrabold text-emerald-300 leading-none tabular-nums">
              {{ remaining ?? '--' }}
            </span>
            <span class="text-sm text-gray-400">
              {{ remaining === 1 ? 'día' : 'días' }}<template v-if="vacationDaysTotal != null"> de {{ vacationDaysTotal }}</template>
            </span>
          </div>
          <p v-if="!hasUpcoming" class="mt-2 text-[11px] text-gray-500">No tienes vacaciones solicitadas.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  daysUntilVacation?: number | null
  vacationDaysTotal?: number | null
  pendingToRequest?: number | null
  remainingVacationDays?: number | null
  loading?: boolean
}>()

const hasUpcoming = computed(() => props.daysUntilVacation != null && props.daysUntilVacation >= 0)
const remaining = computed(() => props.remainingVacationDays ?? props.pendingToRequest ?? null)
const daysText = (n: number) => `${n} ${n === 1 ? 'día' : 'días'}`
</script>
