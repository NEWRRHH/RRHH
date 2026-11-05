<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-fuchsia-500/10 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-fuchsia-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-fuchsia-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5h8M11 9h8M11 13h8M3 5h.01M3 9h.01M3 13h.01M4 21h16a1 1 0 001-1V4a1 1 0 00-1-1H4a1 1 0 00-1 1v16a1 1 0 001 1z"/>
          </svg>
        </div>
        <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Comunicados</h3>
      </div>

      <div v-if="loading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-14 rounded-lg bg-gray-800 animate-pulse"></div>
      </div>
      <div v-else class="space-y-2 max-h-40 overflow-y-auto pr-1 announcements-scroll">
        <button
          v-for="a in items"
          :key="a.id"
          type="button"
          class="w-full text-left rounded-lg px-3 py-2 transition cursor-pointer focus:outline-none focus:ring-1 focus:ring-fuchsia-500/50"
          :class="a.is_read ? 'bg-gray-800/70 hover:bg-gray-700/80' : 'bg-fuchsia-500/10 ring-1 ring-fuchsia-500/40 hover:bg-fuchsia-500/15'"
          @click="openAnnouncement(a)"
        >
          <div class="flex items-center gap-2">
            <span v-if="!a.is_read" class="w-2 h-2 rounded-full bg-fuchsia-400 shrink-0"></span>
            <div class="text-sm truncate" :class="a.is_read ? 'text-gray-300' : 'text-white font-medium'">{{ a.title }}</div>
          </div>
          <div class="text-[11px] text-gray-400 truncate" :class="a.is_read ? '' : 'pl-4'">{{ a.author }}</div>
        </button>
        <div v-if="!items?.length" class="text-sm text-gray-400">Sin comunicados</div>
      </div>
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="selected = null">
      <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-900 p-5 space-y-3 max-h-[80vh] overflow-y-auto">
        <div class="flex items-start justify-between gap-2">
          <h2 class="text-base font-semibold text-white">{{ selected.title }}</h2>
          <button class="text-gray-400 hover:text-white shrink-0" @click="selected = null">X</button>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <p class="text-xs text-gray-400">{{ selected.author }} · {{ formatDate(selected.created_at) }}</p>
          <span
            class="text-[11px] rounded-full px-2 py-0.5 border"
            :class="selected.scope === 'team' ? 'border-amber-500/50 bg-amber-500/15 text-amber-100' : 'border-blue-500/50 bg-blue-500/15 text-blue-100'"
          >
            {{ selected.scope === 'team' ? `Equipo: ${selected.team_name || '-'}` : 'Todos' }}
          </span>
        </div>
        <p class="text-sm text-gray-200 whitespace-pre-wrap">{{ selected.body }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { useAuth } from '../composables/useAuth'

declare const $fetch: any

defineProps({
  items: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
})

const { token, apiBase } = useAuth()
const selected = ref<Record<string, any> | null>(null)

function openAnnouncement(a: Record<string, any>) {
  selected.value = a
  if (!a.is_read) {
    a.is_read = true
    if (token.value) {
      $fetch(`${apiBase || 'http://localhost:8000'}/api/announcements/${a.id}/read`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
      }).catch((e: any) => console.error('announcement mark read failed', e))
    }
  }
}

function formatDate(dt?: string) {
  if (!dt) return '-'
  try {
    return new Date(dt).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return dt
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') selected.value = null
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
.announcements-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(217, 70, 239, 0.55) rgba(31, 41, 55, 0.4);
}

.announcements-scroll::-webkit-scrollbar {
  width: 8px;
}

.announcements-scroll::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.4);
  border-radius: 9999px;
}

.announcements-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, rgba(232, 121, 249, 0.85), rgba(217, 70, 239, 0.85));
  border-radius: 9999px;
}
</style>
