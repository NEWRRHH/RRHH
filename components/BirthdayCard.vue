<template>
  <div class="w-full max-w-[230px]">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <h3 class="text-xs text-gray-300 uppercase tracking-wide mb-3">Cumpleaños</h3>

      <div v-if="loading" class="space-y-4">
        <div class="h-28 rounded-2xl bg-gray-800 animate-pulse"></div>
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
          <div class="flex-1 h-4 bg-gray-800 animate-pulse rounded"></div>
        </div>
      </div>

      <div v-else>
        <div v-if="birthdays && birthdays.length">
          <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-white text-base font-bold overflow-hidden">
              <img v-if="birthdays[0].photo" :src="birthdays[0].photo" class="w-full h-full object-cover" />
              <span v-else>{{ (birthdays[0].first_name || birthdays[0].full_name || '?').charAt(0) }}</span>
            </div>
            <div class="mt-2">
              <div class="text-white font-semibold">{{ birthdays[0].full_name || birthdays[0].first_name }}</div>
              <div class="text-sm text-gray-400 mt-1">{{ formatDate(birthdays[0].next_birthday) }} — {{ birthdays[0].age }} años</div>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2 justify-items-center">
            <div v-for="(b, i) in birthdays.slice(1,3)" :key="b.id" class="flex flex-col items-center text-center gap-1">
              <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 overflow-hidden">
                <img v-if="b.photo" :src="b.photo" class="w-full h-full object-cover" />
                <span v-else class="text-sm">{{ (b.first_name || b.full_name || '?').charAt(0) }}</span>
              </div>
              <div class="text-xs text-gray-200 truncate">{{ b.full_name || b.first_name }}</div>
              <div class="text-[10px] text-gray-400">{{ formatDate(b.next_birthday) }}</div>
            </div>
          </div>
        </div>

        <div v-else class="p-6 rounded-xl bg-gray-800/50 text-center text-gray-400">
          <div class="animate-pulse h-6 w-3/4 mx-auto bg-gray-700 rounded mb-3"></div>
          <div class="text-sm">No upcoming birthdays</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

const props = defineProps({
  birthdays: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false }
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
