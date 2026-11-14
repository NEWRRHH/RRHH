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
        <span v-else-if="status === 'closed'" class="text-gray-400 mr-1 hidden sm:inline">Jornada cerrada</span>
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

    <!-- already clocked out today: nothing left to do until tomorrow -->
    <span
      v-else-if="status === 'closed'"
      class="inline-flex items-center justify-center w-8 h-8 rounded-full text-emerald-300"
      :title="closedTitle"
      :aria-label="closedTitle"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    </span>

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
import { ref, onMounted, computed, onBeforeUnmount, watch } from 'vue'
import { useAuth } from '../composables/useAuth'

declare const $fetch: any

const { authenticated, apiBase, user, attendanceStatus: sharedStatus, lastAttendanceChange } = useAuth()
const emit = defineEmits<{
  changed: []
}>()
const loading = ref(false)
const started = ref(false)
const paused = ref(false)
const attendance = ref<any>(null)
const schedule = ref<any>(null)
const elapsed = ref(0) // seconds worked today (pauses excluded)
// sharedStatus (useAuth): read by the avatar and sidebar dots; also updated
// over WebSocket when this user clocks in/out from another tab or device.
let timer: ReturnType<typeof setInterval> | null = null

const { $swal } = useNuxtApp()

// Today's day already clocked out: { start_time, end_time, hours_worked } or null.
const closedToday = ref<{ start_time: string; end_time: string; hours_worked: string | null } | null>(null)

const status = computed<'idle' | 'working' | 'paused' | 'closed'>(() => {
  if (started.value) return paused.value ? 'paused' : 'working'
  return closedToday.value ? 'closed' : 'idle'
})
const statusLabel = computed(() => ({ idle: 'Sin fichar', working: 'Trabajando', paused: 'En pausa', closed: 'Jornada cerrada' })[status.value])
const dotClass = computed(() => ({ idle: 'bg-gray-500', working: 'bg-emerald-400', paused: 'bg-amber-400', closed: 'bg-gray-500' })[status.value])
const barClass = computed(() => (status.value === 'paused' ? 'bg-amber-400' : 'bg-emerald-500'))
const closedTitle = computed(() => {
  const c = closedToday.value
  if (!c) return ''
  return `Jornada cerrada: entrada ${String(c.start_time).slice(0, 5)}, salida ${String(c.end_time).slice(0, 5)}`
})

// "08:02:15" -> seconds
function timeToSeconds(t?: string | null): number {
  const [h, m, s] = String(t || '').split(':').map((x) => parseInt(x, 10))
  return [h, m].some(isNaN) ? 0 : h * 3600 + m * 60 + (isNaN(s) ? 0 : s)
}

// Show the API's reason (e.g. "Solo puedes fichar entrada hasta 15 minutos antes...")
function showError(e: any, fallback: string) {
  const msg = e?.data?.message || e?.response?._data?.message
  $swal.toast('error', typeof msg === 'string' && msg ? msg : fallback)
}

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

// "2:05:09" (hours:minutes:seconds)
const formatSeconds = (secs: number) => {
  const safe = Math.max(0, Math.floor(secs || 0))
  const h = Math.floor(safe / 3600)
  const m = Math.floor((safe % 3600) / 60)
  const s = safe % 60
  return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

const elapsedText = computed(() => formatSeconds(elapsed.value))
const targetText = computed(() => formatMinutes(targetMinutes.value))
const progressPercent = computed(() =>
  targetMinutes.value > 0 ? Math.min(100, Math.round((elapsed.value / 60 / targetMinutes.value) * 100)) : 0,
)

// Worked seconds today, without the pause (same rule as the API's dashboard):
// paused span = pause_time .. resume_time, or .. now while still paused.
function workedSecondsNow(): number {
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
  return Math.max(0, Math.floor((now - start.getTime() - paused) / 1000))
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
    if (attendance.value?.start_time) elapsed.value = workedSecondsNow()
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
      elapsed.value = workedSecondsNow()
      startTimer()
      closedToday.value = null
    } else {
      started.value = false
      paused.value = false
      if (timer) clearInterval(timer)
      closedToday.value = res?.closed_today || null
      elapsed.value = timeToSeconds(closedToday.value?.hours_worked)
    }
    // the dots only know working / paused / not working
    sharedStatus.value = status.value === 'closed' ? 'idle' : status.value
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
  } catch (e: any) {
    console.error('start attendance failed', e)
    showError(e, 'No se pudo fichar la entrada')
    // e.g. the day was closed from another device: show the real state
    await checkStatus()
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
    sharedStatus.value = 'idle'
    if (timer) clearInterval(timer)
    // refresh in case schedule or working status changed
    await checkStatus()
    emit('changed')
  } catch (e: any) {
    console.error('stop attendance failed', e)
    showError(e, 'No se pudo fichar la salida')
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
      showError(e, endpoint === 'pause' ? 'No se pudo pausar' : 'No se pudo reanudar')
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(checkStatus)

// Clocked in/out from another tab or device: reload start/pause times.
watch(lastAttendanceChange, () => {
  if (!loading.value) void checkStatus()
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>
