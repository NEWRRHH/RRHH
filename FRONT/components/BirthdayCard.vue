<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-8 -right-8 w-28 h-28 bg-rose-500/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-10 -left-10 w-28 h-28 bg-orange-400/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-rose-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-rose-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13a3 3 0 013-3h1a2 2 0 012 2v1a3 3 0 01-3 3m-3-3a3 3 0 00-3-3H8a2 2 0 00-2 2v1a3 3 0 003 3m0 0v2m0 0h6"/>
            </svg>
          </div>
          <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Cumpleanos</h3>
        </div>
        <span class="text-[11px] px-2 py-1 rounded-full border border-rose-400/30 bg-rose-500/10 text-rose-200">
          {{ birthdays?.length || 0 }} proximos
        </span>
      </div>

      <div v-if="loading" class="space-y-3">
        <div class="h-24 rounded-xl bg-gray-800 animate-pulse"></div>
        <div class="h-12 rounded-xl bg-gray-800 animate-pulse"></div>
        <div class="h-12 rounded-xl bg-gray-800 animate-pulse"></div>
      </div>

      <div v-else-if="birthdays && birthdays.length" class="flex flex-col gap-3">
        <div class="rounded-xl border border-rose-500/20 bg-gradient-to-r from-rose-500/10 to-orange-400/10 p-3">
          <div class="text-[11px] uppercase tracking-wide text-rose-200/90 mb-2">Siguiente</div>
          <div class="flex items-center gap-3">
            <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-800 border border-rose-400/30 flex items-center justify-center text-white font-semibold">
              <img v-if="birthdays[0].photo" :src="birthdays[0].photo" class="w-full h-full object-cover" />
              <span v-else>{{ initials(birthdays[0]) }}</span>
            </div>
            <div class="min-w-0">
              <p class="text-white font-semibold truncate">{{ birthdays[0].full_name || birthdays[0].first_name }}</p>
              <p class="text-xs text-rose-100/90">{{ formatDate(birthdays[0].next_birthday) }}</p>
              <p class="text-[11px] text-gray-300">Cumple {{ birthdays[0].age }} anos</p>
            </div>
          </div>
        </div>

        <div class="space-y-2 pr-1 birthday-scroll max-h-40 overflow-y-auto">
          <div
            v-for="b in birthdays.slice(1)"
            :key="b.id"
            class="rounded-xl border border-gray-800 bg-gray-950/70 px-3 py-2 flex items-center gap-3"
          >
            <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-200 text-xs font-semibold shrink-0">
              <img v-if="b.photo" :src="b.photo" class="w-full h-full object-cover" />
              <span v-else>{{ initials(b) }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm text-gray-100 truncate">{{ b.full_name || b.first_name }}</p>
              <p class="text-[11px] text-gray-400">{{ formatDate(b.next_birthday) }}</p>
            </div>
            <span class="text-[11px] text-rose-200 border border-rose-500/30 bg-rose-500/10 rounded-full px-2 py-0.5">
              {{ b.age }}
            </span>
          </div>
        </div>
      </div>

      <div v-else class="flex-1 rounded-xl border border-gray-800 bg-gray-950/60 p-5 text-center text-gray-400 flex items-center justify-center">
        No hay cumpleanos proximos
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

defineProps({
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

const initials = (b: Record<string, any>) => {
  const full = String(b?.full_name || b?.first_name || '?').trim()
  if (!full) return '?'
  const parts = full.split(' ').filter(Boolean)
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase()
  return `${parts[0].slice(0, 1)}${parts[1].slice(0, 1)}`.toUpperCase()
}
</script>

<style scoped>
.birthday-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(251, 113, 133, 0.45) rgba(31, 41, 55, 0.4);
}

.birthday-scroll::-webkit-scrollbar {
  width: 8px;
}

.birthday-scroll::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.4);
  border-radius: 9999px;
}

.birthday-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, rgba(251, 113, 133, 0.8), rgba(249, 115, 22, 0.8));
  border-radius: 9999px;
}
</style>
