<template>
  <div class="flex min-h-screen bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
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

      <main class="relative z-10 flex-1 p-6 overflow-hidden">
        <div class="h-full grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-4">
          <aside class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col min-h-0">
            <h2 class="text-sm font-semibold text-white mb-3">
              {{ canViewAll ? 'Usuarios' : 'Mis fichajes' }}
            </h2>

            <div v-if="canViewAll" class="mb-3">
              <label class="block text-xs text-gray-400 mb-1">Equipo</label>
              <select
                v-model="teamFilter"
                class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg px-2 py-2 focus:outline-none"
              >
                <option value="">Todos los equipos</option>
                <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
              </select>
            </div>

            <div class="flex-1 overflow-y-auto space-y-2">
              <button
                v-for="u in users"
                :key="u.id"
                @click="selectUser(u.id)"
                :class="[
                  'w-full text-left p-2 rounded-lg border transition',
                  selectedUserId === u.id
                    ? 'bg-blue-600/20 border-blue-500 text-white'
                    : 'bg-gray-800 border-gray-700 text-gray-200 hover:bg-gray-700'
                ]"
              >
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs font-semibold">
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

          <section class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col min-h-0">
            <div class="flex items-center justify-between gap-3 mb-4">
              <div class="flex items-center gap-2">
                <button
                  @click="changeMonth(-1)"
                  class="w-8 h-8 rounded-lg bg-gray-800 text-gray-200 hover:bg-gray-700"
                  aria-label="Mes anterior"
                >
                  <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <div class="text-white font-semibold min-w-[140px] text-center">{{ monthLabel }}</div>
                <button
                  @click="changeMonth(1)"
                  class="w-8 h-8 rounded-lg bg-gray-800 text-gray-200 hover:bg-gray-700"
                  aria-label="Mes siguiente"
                >
                  <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>

              <div class="text-xs text-gray-400">
                Usuario:
                <span class="text-gray-200 font-medium">{{ selectedUserName }}</span>
              </div>
            </div>

            <div class="flex-1 overflow-auto border border-gray-800 rounded-xl">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                  <tr>
                    <th class="text-left px-4 py-3">Fecha</th>
                    <th class="text-left px-4 py-3">Entrada</th>
                    <th class="text-left px-4 py-3">Pausa</th>
                    <th class="text-left px-4 py-3">Reanuda</th>
                    <th class="text-left px-4 py-3">Salida</th>
                    <th class="text-left px-4 py-3">Horas</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3 min-w-[260px]">Timeline</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in monthRows" :key="row.date" class="border-t border-gray-800 text-gray-200">
                    <td class="px-4 py-3">
                      <div class="font-medium">{{ row.date }}</div>
                      <div class="text-xs text-gray-400">{{ row.weekday }}</div>
                    </td>
                    <td class="px-4 py-3">{{ formatTime(row.start_time) }}</td>
                    <td class="px-4 py-3">{{ formatTime(row.pause_time) }}</td>
                    <td class="px-4 py-3">{{ formatTime(row.resume_time) }}</td>
                    <td class="px-4 py-3">{{ formatTime(row.end_time) }}</td>
                    <td class="px-4 py-3">{{ row.hours_worked || '--:--:--' }}</td>
                    <td class="px-4 py-3">
                      <span
                        v-if="row.status"
                        :class="row.status === 'en_trabajo' ? 'bg-green-600/20 text-green-300' : 'bg-gray-700 text-gray-300'"
                        class="px-2 py-1 rounded-full text-xs"
                      >
                        {{ row.status }}
                      </span>
                      <span v-else class="text-gray-500 text-xs">Sin fichaje</span>
                    </td>
                    <td class="px-4 py-3">
                      <div class="relative h-3 bg-gray-800 rounded-full overflow-hidden">
                        <div
                          v-for="(seg, idx) in timelineSegments(row)"
                          :key="idx"
                          class="absolute top-0 h-3 rounded-full"
                          :class="seg.className"
                          :style="{ left: `${seg.left}%`, width: `${seg.width}%` }"
                        />
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
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

function formatTime(v?: string | null) {
  if (!v) return '--:--:--'
  return String(v).slice(0, 8)
}

function timeToMinutes(v?: string | null) {
  if (!v) return null
  const p = String(v).split(':').map((x) => Number(x))
  if (p.length < 2 || Number.isNaN(p[0]) || Number.isNaN(p[1])) return null
  return p[0] * 60 + p[1] + ((p[2] || 0) / 60)
}

function toPct(mins: number, dayStart: number, dayEnd: number) {
  const clamped = Math.max(dayStart, Math.min(dayEnd, mins))
  return ((clamped - dayStart) / (dayEnd - dayStart)) * 100
}

function timelineSegments(row: any) {
  const dayStart = 6 * 60
  const dayEnd = 20 * 60
  const start = timeToMinutes(row.start_time)
  const pause = timeToMinutes(row.pause_time)
  const resume = timeToMinutes(row.resume_time)
  const end = timeToMinutes(row.end_time)
  if (start === null || end === null || end <= start) return []

  const segs: Array<{ left: number; width: number; className: string }> = []

  const firstWorkEnd = pause && pause > start && pause < end ? pause : end
  const l1 = toPct(start, dayStart, dayEnd)
  const w1 = Math.max(0, toPct(firstWorkEnd, dayStart, dayEnd) - l1)
  if (w1 > 0) segs.push({ left: l1, width: w1, className: 'bg-emerald-400' })

  if (pause && pause < end) {
    const pauseEnd = resume && resume > pause && resume < end ? resume : end
    const lp = toPct(pause, dayStart, dayEnd)
    const wp = Math.max(0, toPct(pauseEnd, dayStart, dayEnd) - lp)
    if (wp > 0) segs.push({ left: lp, width: wp, className: 'bg-amber-400' })
  }

  if (resume && resume < end) {
    const l2 = toPct(resume, dayStart, dayEnd)
    const w2 = Math.max(0, toPct(end, dayStart, dayEnd) - l2)
    if (w2 > 0) segs.push({ left: l2, width: w2, className: 'bg-emerald-400' })
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
