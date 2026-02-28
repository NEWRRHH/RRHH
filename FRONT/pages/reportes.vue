<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <h1 class="text-base font-semibold text-white">Fichajes</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" @changed="loadMonthData" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-hidden">
        <div class="h-full grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-4">
          <aside class="bg-gray-900 border border-gray-800 rounded-3xl p-4 flex flex-col min-h-0 shadow-sm">
            <h2 class="text-sm font-semibold text-white mb-3">
              {{ canViewAll ? 'Usuarios' : 'Mis fichajes' }}
            </h2>

            <div v-if="canViewAll" class="mb-3">
              <label class="block text-xs text-gray-400 mb-1">Equipo</label>
              <select
                v-model="teamFilter"
                class="w-full bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-xl px-2.5 py-2 focus:outline-none"
              >
                <option value="">Todos los equipos</option>
                <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
              </select>
            </div>

            <div class="flex-1 overflow-y-auto space-y-2 pr-1">
              <button
                v-for="u in users"
                :key="u.id"
                @click="selectUser(u.id)"
                :class="[
                  'w-full text-left p-2.5 rounded-xl border transition',
                  selectedUserId === u.id
                    ? 'bg-blue-600/20 border-blue-500 text-white'
                    : 'bg-gray-800 border-gray-700 text-gray-200 hover:bg-gray-700'
                ]"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs font-semibold">
                    <img v-if="u.photo" :src="u.photo" class="w-full h-full object-cover" />
                    <span v-else>{{ (u.full_name || '?').charAt(0).toUpperCase() }}</span>
                  </div>
                  <div class="min-w-0">
                    <div class="text-sm truncate">{{ u.full_name }}</div>
                    <div class="text-[11px] text-gray-400 truncate">{{ u.team_name || 'Sin equipo' }}</div>
                  </div>
                </div>
              </button>
            </div>
          </aside>

          <section class="bg-gray-900 border border-gray-800 rounded-3xl p-5 flex flex-col min-h-0 overflow-hidden shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-5">
              <div class="flex items-center gap-2">
                <button
                  @click="changeMonth(-1)"
                  class="h-11 w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                  aria-label="Mes anterior"
                >
                  <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <div class="h-11 min-w-[180px] px-4 rounded-xl border border-gray-700 bg-gray-800 text-white font-medium flex items-center justify-center">
                  {{ monthLabel }}
                </div>
                <button
                  @click="changeMonth(1)"
                  class="h-11 w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                  aria-label="Mes siguiente"
                >
                  <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
                <div class="h-11 px-4 rounded-xl border border-gray-700 bg-gray-800 text-gray-300 text-sm flex items-center">
                  Mensual
                </div>
              </div>

              <button
                class="h-11 px-5 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-500"
                @click="loadMonthData"
              >
                Solicitar fichaje
              </button>
            </div>

            <div class="text-xs text-gray-400 mb-3">
              Usuario seleccionado:
              <span class="text-gray-200 font-medium">{{ selectedUserName }}</span>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto rounded-2xl border border-gray-800">
              <div class="min-w-[980px]">
                <div class="grid grid-cols-[150px_110px_minmax(0,1fr)] px-3 py-2 text-gray-400 text-sm border-b border-gray-800 bg-gray-800/50 sticky top-0 z-10">
                  <div>Fecha</div>
                  <div>Horas</div>
                  <div class="relative">
                    <div class="flex justify-between">
                      <span v-for="h in hourMarks" :key="h">{{ h }}:00</span>
                    </div>
                  </div>
                </div>

                <div
                  v-for="row in monthRows"
                  :key="row.date"
                  class="grid grid-cols-[150px_110px_minmax(0,1fr)] px-3 py-3 border-b border-gray-800 items-center"
                >
                  <div class="text-gray-200 text-sm">
                    <div class="font-medium">{{ shortDate(row.date) }}</div>
                    <div class="text-xs text-gray-500 uppercase">{{ row.weekday }}</div>
                  </div>
                  <div class="text-gray-200 text-sm font-medium">
                    {{ workedLabel(row) }}
                  </div>
                  <div class="relative h-5 rounded-full bg-gray-800 overflow-hidden">
                    <div
                      v-for="(tick, idx) in hourTicks"
                      :key="idx"
                      class="absolute top-0 bottom-0 w-px bg-gray-700"
                      :style="{ left: `${tick}%` }"
                    />
                    <div
                      v-for="(seg, idx) in timelineSegments(row)"
                      :key="idx"
                      class="absolute top-0 h-5"
                      :class="seg.className"
                      :style="{ left: `${seg.left}%`, width: `${seg.width}%` }"
                    />
                  </div>
                </div>

                <div v-if="!monthRows.length" class="px-3 py-10 text-center text-gray-500 text-sm">
                  No hay datos para este mes.
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeMount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const canViewAll = ref(false)
const users = ref<any[]>([])
const teams = ref<any[]>([])
const monthRows = ref<any[]>([])
const selectedUserId = ref<number | null>(null)
const teamFilter = ref('')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))

const dayStart = 6 * 60
const dayEnd = 19 * 60

const hourMarks = computed(() => Array.from({ length: 14 }, (_, i) => 6 + i))
const hourTicks = computed(() => hourMarks.value.map((h) => ((h * 60 - dayStart) / (dayEnd - dayStart)) * 100))

const selectedUserName = computed(() => {
  const u = users.value.find((x) => x.id === selectedUserId.value)
  return u?.full_name || 'Sin usuario'
})

const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1, 1)
  return d.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function shortDate(dateIso: string) {
  const d = new Date(dateIso)
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function toMinutes(v?: string | null) {
  if (!v) return null
  const p = String(v).split(':').map((x) => Number(x))
  if (p.length < 2 || Number.isNaN(p[0]) || Number.isNaN(p[1])) return null
  return p[0] * 60 + p[1] + ((p[2] || 0) / 60)
}

function pct(mins: number) {
  const clamped = Math.max(dayStart, Math.min(dayEnd, mins))
  return ((clamped - dayStart) / (dayEnd - dayStart)) * 100
}

function workedMinutes(row: any) {
  if (row.hours_worked) {
    const p = String(row.hours_worked).split(':').map((x) => Number(x))
    if (p.length >= 2 && !Number.isNaN(p[0]) && !Number.isNaN(p[1])) {
      return p[0] * 60 + p[1]
    }
  }
  const start = toMinutes(row.start_time)
  const end = toMinutes(row.end_time)
  if (start === null || end === null || end <= start) return 0
  let total = end - start
  const pause = toMinutes(row.pause_time)
  const resume = toMinutes(row.resume_time)
  if (pause !== null && pause > start && pause < end) {
    const pauseEnd = resume !== null && resume > pause && resume < end ? resume : end
    total -= (pauseEnd - pause)
  }
  return Math.max(0, Math.round(total))
}

function workedLabel(row: any) {
  const mins = workedMinutes(row)
  if (!mins) return '--:-- h'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${h}:${String(m).padStart(2, '0')} h`
}

function timelineSegments(row: any) {
  const start = toMinutes(row.start_time)
  const pause = toMinutes(row.pause_time)
  const resume = toMinutes(row.resume_time)
  const end = toMinutes(row.end_time)
  if (start === null || end === null || end <= start) return []

  const segs: Array<{ left: number; width: number; className: string }> = []
  const work1End = pause && pause > start && pause < end ? pause : end
  const l1 = pct(start)
  const w1 = Math.max(0, pct(work1End) - l1)
  if (w1 > 0) segs.push({ left: l1, width: w1, className: 'bg-green-500/90' })

  if (pause && pause < end) {
    const pauseEnd = resume && resume > pause && resume < end ? resume : end
    const lp = pct(pause)
    const wp = Math.max(0, pct(pauseEnd) - lp)
    if (wp > 0) segs.push({ left: lp, width: wp, className: 'bg-yellow-400/90' })
  }

  if (resume && resume < end) {
    const l2 = pct(resume)
    const w2 = Math.max(0, pct(end) - l2)
    if (w2 > 0) segs.push({ left: l2, width: w2, className: 'bg-green-500/90' })
  }

  return segs
}

function changeMonth(delta: number) {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1 + delta, 1)
  const yy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  selectedMonth.value = `${yy}-${mm}`
}

async function loadMonthData() {
  if (!token.value) return
  const params = new URLSearchParams()
  params.set('month', selectedMonth.value)
  if (teamFilter.value) params.set('team_id', teamFilter.value)
  if (selectedUserId.value) params.set('user_id', String(selectedUserId.value))

  const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/month?${params.toString()}`, {
    headers: { Authorization: `Bearer ${token.value}` },
  })

  canViewAll.value = !!res?.can_view_all
  users.value = res?.users || []
  teams.value = res?.teams || []
  monthRows.value = res?.rows || []
  selectedUserId.value = res?.selected_user_id || null
  selectedMonth.value = res?.month || selectedMonth.value
}

function selectUser(id: number) {
  if (selectedUserId.value === id) return
  selectedUserId.value = id
  loadMonthData()
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

watch([selectedMonth, teamFilter], () => {
  loadMonthData()
})

onBeforeMount(async () => {
  if (!token.value) {
    if (process.client) {
      const saved = localStorage.getItem('rrhh_token')
      if (saved) {
        setToken(saved)
        await fetchUser()
      } else {
        return router.push('/login')
      }
    } else {
      return
    }
  } else {
    await fetchUser()
  }

  await loadMonthData()
})
</script>
