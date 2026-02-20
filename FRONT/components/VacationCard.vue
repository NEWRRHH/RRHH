<template>
  <div class="w-full max-w-[230px] h-72">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">

      <!-- subtle teal glow top-right -->
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-teal-500/10 rounded-full blur-2xl pointer-events-none"></div>

      <!-- header -->
      <div class="flex items-center gap-2 mb-4">
        <div class="w-7 h-7 rounded-lg bg-teal-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Vacaciones</h3>
      </div>

      <!-- loading -->
      <div v-if="loading" class="space-y-3 animate-pulse">
        <div class="h-14 rounded-xl bg-gray-800"></div>
        <div class="h-px bg-gray-800"></div>
        <div class="h-14 rounded-xl bg-gray-800"></div>
      </div>

      <!-- content -->
      <div v-else class="flex flex-col gap-3 relative">

        <div class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-3xl font-extrabold text-teal-300 leading-none tabular-nums">
            {{ daysUntilVacation ?? '—' }}
          </div>
          <div class="text-xs text-gray-400 mt-1">días para vacaciones</div>
        </div>

        <div class="h-px bg-gray-800"></div>

        <div class="rounded-xl bg-teal-500/5 border border-teal-500/10 px-4 py-3">
          <div class="text-3xl font-extrabold text-teal-300 leading-none tabular-nums">
            {{ vacationDays ?? '—' }}
          </div>
          <div class="text-xs text-gray-400 mt-1">días de vacaciones</div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  daysUntilVacation?: number | null
  vacationDays?: number | null
  loading?: boolean
}>()
</script>
