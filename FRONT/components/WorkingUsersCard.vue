<template>
  <div class="w-full h-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-green-500/10 rounded-full blur-2xl pointer-events-none"></div>

      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">En trabajo</h3>
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse shrink-0"></div>
          <div class="flex-1 h-4 bg-gray-800 animate-pulse rounded"></div>
        </div>
      </div>

      <div v-else class="flex-1 overflow-y-auto">
        <div v-if="users && users.length" class="flex flex-col gap-3">
          <div v-for="u in users" :key="u.id" class="flex items-center gap-3">
            <div class="relative shrink-0">
              <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-800 flex items-center justify-center text-gray-400">
                <img v-if="u.photo" :src="u.photo" class="w-full h-full object-cover" />
                <span v-else class="text-sm">{{ (u.first_name || u.full_name || '?').charAt(0) }}</span>
              </div>
              <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full ring-2 ring-gray-900"></span>
            </div>
            <span class="text-xs text-gray-200 truncate">{{ u.full_name || u.first_name }}</span>
          </div>
        </div>
        <div v-else class="text-sm text-gray-400">No hay trabajadores activos</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

defineProps({
  users: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false }
})
</script>
