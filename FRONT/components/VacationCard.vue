<template>
  <div class="w-full max-w-[260px]">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col shadow-lg shadow-black/20">
      <h3 class="text-xs text-gray-300 uppercase tracking-wide mb-3">Próximas vacaciones</h3>

      <div v-if="loading" class="flex-1 flex flex-col justify-center items-center gap-3 py-6">
        <div class="h-6 w-24 bg-gray-800 rounded animate-pulse"></div>
        <div class="h-4 w-12 bg-gray-800 rounded animate-pulse"></div>
      </div>

      <div v-else>
        <div v-if="vacation">
          <div class="text-sm text-white font-semibold">{{ vacation.title || 'Vacaciones' }}</div>
          <div class="text-xs text-gray-400 mt-1">{{ formatDate(vacation.start_at) }} — {{ formatDate(vacation.end_at) }}</div>

          <div class="mt-4 flex items-center gap-3">
            <div class="flex flex-col items-center justify-center w-16 h-16 rounded-xl bg-gradient-to-br from-blue-700 to-blue-600 text-white">
              <div class="text-lg font-bold">{{ vacation.days_until }}</div>
              <div class="text-[10px]">días</div>
            </div>
            <div class="flex-1">
              <div class="text-xs text-gray-300">Faltan</div>
              <div class="text-sm text-white font-semibold">{{ vacation.days_until === 0 ? 'Comienzan hoy' : vacation.days_until + ' días' }}</div>
              <div class="text-xs text-gray-400 mt-1">Duración: {{ vacation.duration_days }} días</div>
            </div>
          </div>
        </div>
        <div v-else class="py-6 text-center text-gray-400">No hay próximas vacaciones</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

const props = defineProps({
  vacation: { type: Object as PropType<Record<string, any> | null>, default: null },
  loading: { type: Boolean as PropType<boolean>, default: false },
})

const formatDate = (iso?: string) => {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' })
  } catch (e) {
    return iso || ''
  }
}
</script>
