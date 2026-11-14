<template>
  <!-- One pill, same shape in every state: status dot + time + progress + actions -->
  <div
    class="inline-flex items-center gap-2 sm:gap-3 h-10 pl-3 pr-1 rounded-full border border-gray-700/80 bg-gray-900/80 text-xs text-white"
    role="group"
    aria-label="Fichaje"
  >
    <span class="relative flex w-2.5 h-2.5 shrink-0" :title="statusLabel">
      <span v-if="status === 'working'" class="absolute inline-flex w-full h-full rounded-full bg-emerald-400 opacity-60 animate-ping motion-reduce:animate-none"></span>
      <span class="relative inline-flex w-2.5 h-2.5 rounded-full" :class="dotClass"></span>
    </span>
    <span class="sr-only">{{ statusLabel }}</span>

    <div class="flex flex-col justify-center leading-tight">
      <span class="tabular-nums whitespace-nowrap">
        <span v-if="status === 'paused'" class="text-amber-300 mr-1 hidden sm:inline">En pausa</span>
        <span class="font-semibold">{{ elapsedText }}</span>
        <span class="text-gray-500 hidden sm:inline"> / {{ targetText }}</span>
      </span>
      <span class="hidden sm:block mt-1 h-1 w-24 rounded-full bg-gray-800 overflow-hidden" aria-hidden="true">
        <span class="block h-full rounded-full transition-[width] duration-500" :class="barClass" :style="{ width: `${progressPercent}%` }"></span>
      </span>
    </div>

    <!-- not clocked in -->
    <button
      v-if="status === 'idle'"
      type="button"
      :disabled="loading"
      class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition disabled:opacity-50"
      title="Fichar entrada"
      @click="handleStart"
    >
      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 001.5.86l10.5-6.5a1 1 0 000-1.72L9.5 4.64A1 1 0 008 5.5z"/></svg>
      Fichar
    </button>

    <!-- clocked in: pause/resume + clock out -->
    <div v-else class="flex items-center gap-1">
      <button
        type="button"
        :disabled="loading"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center transition disabled:opacity-50"
        :class="status === 'paused' ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
        :title="status === 'paused' ? 'Reanudar' : 'Pausar'"
        :aria-label="status === 'paused' ? 'Reanudar' : 'Pausar'"
        @click="togglePause"
      >
        <svg v-if="status === 'paused'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 001.5.86l10.5-6.5a1 1 0 000-1.72L9.5 4.64A1 1 0 008 5.5z"/></svg>
        <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
      </button>
      <button
        type="button"
        :disabled="loading"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center text-red-300 hover:bg-red-500/20 hover:text-red-200 transition disabled:opacity-50"
        title="Fichar salida"
        aria-label="Fichar salida"
        @click="handleStop"
      >
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { useAuth } from '../composables/useAuth'

declare const $fetch: any

const { authenticated, apiBase, user } = useAuth()
const emit = defineEmits<{
  changed: []
}>()
const loading = ref(false)
const started = ref(false)
const paused = ref(false)
const attendance = ref<any>(null)
const schedule = ref<any>(null)
const elapsed = ref(0) // minutes
let timer: ReturnType<typeof setInterval> | null = null

const { $swal } = useNuxtApp()

const status = computed<'idle' | 'working' | 'paused'>(() => (!started.value ? 'idle' : paused.value ? 'paused' : 'working'))
const statusLabel = computed(() => ({ idle: 'Sin fichar', working: 'Trabajando', paused: 'En pausa' })[status.value])
const dotClass = computed(() => ({ idle: 'bg-gray-500', working: 'bg-emerald-400', paused: 'bg-amber-400' })[status.value])
const barClass = computed(() => (status.value === 'paused' ? 'bg-amber-400' : 'bg-emerald-500'))

// "2:05" (hours:minutes)
const formatMinutes = (mins: number) => {
  const safe = Math.max(0, Math.floor(mins || 0))
  return `${Math.floor(safe / 60)}:${(safe % 60).toString().padStart(2, '0')}`
}

const targetMinutes = computed(() => {
  if (!schedule.value?.start_time || !schedule.value?.end_time) return 0
  const start = new Date(`1970-01-01T${schedule.value.start_time}`)
  const end = new Date(`1970-01-01T${schedule.value.end_time}`)
  let diff = (end.getTime() - start.getTime()) / 60000
  if (diff < 0) diff += 24 * 60
  return Math.round(diff)
})

const elapsedText = computed(() => formatMinutes(elapsed.value))
const targetText = computed(() => formatMinutes(targetMinutes.value))
const progressPercent = computed(() =>
  targetMinutes.value > 0 ? Math.min(100, Math.round((elapsed.value / targetMinutes.value) * 100)) : 0,
)

// Worked minutes today, without the pause (same rule as the API's dashboard):
// paused span = pause_time .. resume_time, or .. now while still paused.
function workedMinutesNow(): number {
  const att = attendance.value
  const start = att?.start_time ? parseTimeString(att.start_time) : null
  if (!start) return 0
  const now = Date.now()
  let paused = 0
  const pauseStart = att.pause_time ? parseTimeString(att.pause_time) : null
  if (pauseStart) {
    const resume = att.resume_time ? parseTimeString(att.resume_time) : null
    const pauseEnd = resume && resume > pauseStart ? Math.min(resume.getTime(), now) : now
    paused = Math.max(0, pauseEnd - pauseStart.getTime())
  }
  return Math.max(0, Math.floor((now - start.getTime() - paused) / 60000))
}

function parseTimeString(timeStr: string) {
  // convert "HH:MM:SS" into a Date object on today's date
  const parts = timeStr.split(':').map((x) => parseInt(x, 10))
  if (parts.length < 2 || parts.some(isNaN)) return null
  const d = new Date()
  d.setHours(parts[0], parts[1], parts[2] || 0, 0)
  return d
}

function startTimer() {
  if (timer) clearInterval(timer)
  timer = setInterval(() => {
    if (attendance.value?.start_time) elapsed.value = workedMinutesNow()
  }, 1000)
}

async function checkStatus() {
  if (!authenticated.value) return
  try {
    const res = await $fetch(`/api/attendance/info`, {
    })
    schedule.value = res?.schedule || null
    if (res?.attendance && res.attendance.id) {
      attendance.value = res.attendance
      started.value = true
      // paused when pause_time is newer than resume_time (or resume missing)
      const p = res.attendance.pause_time || null
      const r = res.attendance.resume_time || null
      paused.value = !!p && (!r || p > r)
      elapsed.value = workedMinutesNow()
      startTimer()
    } else {
      started.value = false
      elapsed.value = 0
    }
  } catch (e) {
    console.error('attendance info error', e)
  }
}

const handleStart = async () => {
  loading.value = true
  try {
    await $fetch(`/api/attendance/start`, {
      method: 'POST',
    })
    await checkStatus()
    emit('changed')
  } catch (e) {
    console.error('start attendance failed', e)
  } finally {
    loading.value = false
  }
}

const handleStop = async () => {
  if (!started.value) return
  // the button is now just an icon: confirm so a stray click doesn't end the day
  const ok = await $swal.confirm('Fichar salida', `Llevas ${elapsedText.value} trabajadas hoy. ¿Quieres fichar la salida?`, 'Fichar salida', 'Cancelar')
  if (!ok) return
  loading.value = true
  try {
    await $fetch(`/api/attendance/stop`, {
      method: 'POST',
    })
    attendance.value = null
    started.value = false
    paused.value = false
    elapsed.value = 0
    if (timer) clearInterval(timer)
    // refresh in case schedule or working status changed
    await checkStatus()
    emit('changed')
  } catch (e) {
    console.error('stop attendance failed', e)
  } finally {
    loading.value = false
  }
}

const togglePause = () => {
  if (!started.value || loading.value) return
  loading.value = true
  const endpoint = paused.value ? 'resume' : 'pause'
  $fetch(`/api/attendance/${endpoint}`, {
    method: 'POST',
  })
    .then(async () => {
      await checkStatus()
      emit('changed')
    })
    .catch((e: any) => {
      console.error(`${endpoint} attendance failed`, e)
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(checkStatus)
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>
