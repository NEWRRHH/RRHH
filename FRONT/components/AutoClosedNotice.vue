<template>
  <!-- clock-ins the system closed because the employee forgot to clock out -->
  <div
    v-if="records.length"
    class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"
    role="status"
  >
    <div class="flex items-start gap-3">
      <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div class="min-w-0 flex-1">
        <p class="font-medium">
          {{ records.length === 1 ? 'Olvidaste fichar la salida' : `Olvidaste fichar la salida ${records.length} días` }}
        </p>
        <p class="text-xs text-amber-200/80 mt-0.5">
          El sistema cerró el fichaje al final de tu jornada. Si la hora no es correcta, solicita la corrección desde Reportes &gt; Ausencias.
        </p>
        <ul class="mt-2 space-y-0.5 text-xs">
          <li v-for="r in records" :key="r.id">
            <strong>{{ formatDate(r.date) }}</strong>: entrada {{ r.start_time || '—' }} · salida automática {{ r.end_time || '—' }}
            <span v-if="r.hours_worked" class="text-amber-200/70">({{ r.hours_worked }} h)</span>
          </li>
        </ul>
      </div>
      <button
        type="button"
        class="shrink-0 text-xs px-2.5 py-1.5 rounded-lg border border-amber-400/40 hover:bg-amber-500/20 disabled:opacity-50"
        :disabled="dismissing"
        @click="dismiss"
      >Entendido</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAuth } from '../composables/useAuth'

declare const $fetch: any

type AutoClosed = { id: number; date: string; start_time: string | null; end_time: string | null; hours_worked: string | null; note: string | null }

const { lastAttendanceChange } = useAuth()
const records = ref<AutoClosed[]>([])
const dismissing = ref(false)

function formatDate(iso: string) {
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}

async function load() {
  try {
    const res: any = await $fetch('/api/attendance/auto-closed')
    records.value = res?.records || []
  } catch {
    records.value = []
  }
}

async function dismiss() {
  dismissing.value = true
  try {
    await $fetch('/api/attendance/auto-closed/seen', { method: 'POST' })
    records.value = []
  } catch {
    // keep it: shown again next time
  } finally {
    dismissing.value = false
  }
}

onMounted(load)
// the system closes it at night: refresh when the clock-in status is pushed
watch(lastAttendanceChange, load)
</script>
