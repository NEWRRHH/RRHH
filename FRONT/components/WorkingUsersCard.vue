<template>
  <div class="w-full max-w-[180px]">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-3 h-full flex flex-col shadow-lg shadow-black/30 transition-transform hover:-translate-y-0.5">
      <h3 class="text-xs text-gray-300 uppercase tracking-wide mb-3">En trabajo</h3>
      <div class="flex-1">
        <div v-if="loading" class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
          <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
          <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
        </div>
        <div v-else>
          <div v-if="users && users.length" class="flex items-center gap-3 overflow-x-auto py-1">
            <div v-for="u in users" :key="u.id" class="flex items-center gap-2 min-w-[130px] flex-shrink-0">
              <div class="relative">
                <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-800 flex items-center justify-center text-gray-400">
                  <img v-if="u.photo" :src="u.photo" class="w-full h-full object-cover" />
                  <span v-else class="text-sm">{{ (u.first_name || u.full_name || '?').charAt(0) }}</span>
                </div>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full ring-2 ring-gray-900"></span>
              </div>
              <span class="text-xs text-gray-200 truncate">{{ u.full_name || u.first_name }}</span>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400">No hay trabajadores en trabajo</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

const props = defineProps({
  users: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false }
})
</script>
