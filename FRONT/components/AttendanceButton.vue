<template>
  <div class="flex items-center gap-2">
    <!-- not yet clocked in: full-green clickable button -->
    <button
      v-if="!started"
      @click="handleStart"
      :disabled="loading"
      class="flex items-center gap-2 bg-green-600 text-white text-xs px-3 py-1 rounded hover:bg-green-700 transition disabled:opacity-50"
    >
      <span>{{ elapsedText }}</span>
      <span class="text-green-200">/ {{ targetText }}</span>
      <span class="ml-2 font-semibold">fichar</span>
    </button>

    <!-- clocked in state -->
    <div v-else class="flex items-center gap-2 text-white text-xs">
      <div class="flex items-center gap-1 bg-gray-800 px-2 py-1 rounded">
        <span>{{ elapsedText }}</span>
        <span class="text-gray-400">/ {{ targetText }}</span>
      </div>
      <button
        @click="togglePause"
        class="px-2 py-1 rounded text-black bg-yellow-400 hover:bg-yellow-500 transition text-[10px]"
      >
        {{ paused ? 'Resume' : 'Pause' }}
      </button>
      <button
        @click="handleStop"
        class="px-2 py-1 rounded text-white bg-red-500 hover:bg-red-600 transition text-[10px]"
      >
        salida
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { useAuth } from '../composables/useAuth'

declare const $fetch: any

const { token, apiBase, user } = useAuth()
const emit = defineEmits<{
  changed: []
}>()
const loading = ref(false)
const started = ref(false)
const paused = ref(false)
const attendance = ref<any>(null)
const schedule = ref<any>(null)
const elapsed = ref(0) // minutes
let timer: number | null = null

const userInitial = computed(() => {
  const name = user.value?.name || ''
  return name.charAt(0).toUpperCase() || '?'
})

const formatMinutes = (mins: number) => {
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${h}h ${m.toString().padStart(2, '0')}min`
}

const elapsedText = computed(() => {
  const mins = isNaN(elapsed.value) ? 0 : elapsed.value
  return formatMinutes(mins)
})
const targetText = computed(() => {
  if (!schedule.value || !schedule.value.start_time || !schedule.value.end_time) {
    return '00h 00min'
  }
  const start = new Date(`1970-01-01T${schedule.value.start_time}`)
  const end = new Date(`1970-01-01T${schedule.value.end_time}`)
  let diff = (end.getTime() - start.getTime()) / 60000
  if (diff < 0) diff += 24 * 60
  return formatMinutes(Math.round(diff))
})

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
    if (!paused.value && attendance.value?.start_time) {
      const start = parseTimeString(attendance.value.start_time)
      if (start) {
        const diff = Math.floor((Date.now() - start.getTime()) / 60000)
        elapsed.value = isNaN(diff) ? 0 : diff
      }
    }
  }, 1000)
}

async function checkStatus() {
  if (!token.value) return
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/info`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    schedule.value = res?.schedule || null
    if (res?.attendance && res.attendance.id) {
      attendance.value = res.attendance
      started.value = true
      paused.value = false
      const startDate = parseTimeString(res.attendance.start_time)
      if (startDate) {
        elapsed.value = Math.floor((Date.now() - startDate.getTime()) / 60000)
      } else {
        elapsed.value = 0
      }
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
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/start`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
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
  loading.value = true
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/stop`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
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
  paused.value = !paused.value
}

onMounted(checkStatus)
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>
