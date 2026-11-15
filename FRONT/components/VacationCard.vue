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

      <!-- boxes without data (no upcoming vacation, 0 requested days) are hidden so the card shrinks -->
      <div v-else class="flex flex-col gap-3 relative">
        <div v-if="hasUpcoming" class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Próximas vacaciones en</div>
          <div class="text-3xl font-extrabold text-teal-300 leading-none tabular-nums mt-1">
            {{ daysUntilVacation === 0 ? 'Hoy' : daysText(daysUntilVacation!) }}
          </div>
        </div>

        <div v-if="requested" class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Días de vacaciones solicitadas</div>
          <div class="text-3xl font-extrabold text-teal-300 leading-none tabular-nums mt-1">
            {{ daysText(requested) }}
          </div>
        </div>

        <!-- always shown: it's what the person can still request -->
        <div class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-xs text-gray-400">Días de vacaciones restantes</div>
          <div class="text-3xl font-extrabold text-emerald-300 leading-none tabular-nums mt-1">
            {{ remaining != null ? daysText(remaining) : '--' }}
          </div>
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
// vacationDaysTotal = days requested this year (/api/vacations/me vacation_days_requested_year)
const requested = computed(() => (props.vacationDaysTotal && props.vacationDaysTotal > 0 ? props.vacationDaysTotal : 0))
const daysText = (n: number) => `${n} ${n === 1 ? 'día' : 'días'}`
</script>
