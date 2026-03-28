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
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <h1 class="text-base font-semibold text-white">Fichajes</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" @changed="handleAttendanceChanged" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-3 sm:p-4 lg:p-6 overflow-hidden">
        <div class="h-full flex flex-col gap-4">
          <div class="max-w-full overflow-x-auto">
            <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900 w-max">
              <button
                class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                :class="activeTab === 'monthly' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="activeTab = 'monthly'"
              >
                Mensual
              </button>
              <button
                class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                :class="activeTab === 'who_is_in' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="activeTab = 'who_is_in'"
              >
                Who's in
              </button>
            </div>
          </div>

          <div
            v-if="requestNotice.text"
            class="rounded-xl border px-3 py-2 text-sm"
            :class="requestNotice.type === 'error'
              ? 'border-red-500/30 bg-red-500/10 text-red-200'
              : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'"
          >
            {{ requestNotice.text }}
          </div>

          <div v-if="activeTab === 'monthly'" class="h-full grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-4">
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
              <div class="flex flex-col gap-3 mb-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    @click="changeMonth(-1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes anterior"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div class="h-10 sm:h-11 min-w-[140px] sm:min-w-[180px] px-3 sm:px-4 rounded-xl border border-gray-700 bg-gray-800 text-white text-sm sm:text-base font-medium flex items-center justify-center">
                    {{ monthLabel }}
                  </div>
                  <button
                    @click="changeMonth(1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes siguiente"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                  <div class="hidden sm:flex h-11 px-4 rounded-xl border border-gray-700 bg-gray-800 text-gray-300 text-sm items-center">
                    Mensual
                  </div>
                </div>

                <button
                  class="h-10 sm:h-11 w-full sm:w-auto px-5 rounded-xl text-white text-sm font-medium bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="requestingAttendance"
                  @click="openRequestModal"
                >
                  {{ requestingAttendance ? 'Enviando...' : 'Solicitar fichaje' }}
                </button>
              </div>

              <div class="text-xs text-gray-400 mb-3">
                Usuario seleccionado:
                <span class="text-gray-200 font-medium">{{ selectedUserName }}</span>
              </div>
              <div class="mb-4 flex flex-wrap gap-2">
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Horas trabajadas:
                  <span class="text-white font-semibold">{{ summary.worked_hhmm }}</span>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Horas a trabajar:
                  <span class="text-white font-semibold">{{ summary.target_hhmm }}</span>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Cumplimiento:
                  <span class="text-white font-semibold">{{ summaryPercent }}%</span>
                </div>
                <label class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 inline-flex items-center gap-2 text-xs text-gray-300 cursor-pointer select-none">
                  <span>No laborables</span>
                  <button
                    type="button"
                    @click="includeNonWorkingDays = !includeNonWorkingDays"
                    :class="[
                      'relative inline-flex h-5 w-10 items-center rounded-full transition',
                      includeNonWorkingDays ? 'bg-blue-600' : 'bg-gray-600'
                    ]"
                    aria-label="Mostrar no laborables"
                  >
                    <span
                      :class="[
                        'inline-block h-4 w-4 transform rounded-full bg-white transition',
                        includeNonWorkingDays ? 'translate-x-5' : 'translate-x-1'
                      ]"
                    />
                  </button>
                </label>
              </div>

              <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto rounded-2xl border border-gray-800">
                <div class="min-w-[760px] sm:min-w-[840px] lg:min-w-[980px]">
                  <div class="grid grid-cols-[120px_90px_minmax(0,1fr)] sm:grid-cols-[150px_110px_minmax(0,1fr)] px-3 py-2 text-gray-400 text-xs sm:text-sm border-b border-gray-800 bg-gray-800/50 sticky top-0 z-10">
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
                    class="grid grid-cols-[120px_90px_minmax(0,1fr)] sm:grid-cols-[150px_110px_minmax(0,1fr)] px-3 py-3 border-b border-gray-800 items-center"
                  >
                    <div class="text-gray-200 text-xs sm:text-sm">
                      <div class="font-medium">{{ shortDate(row.date) }}</div>
                      <div class="text-xs text-gray-500 uppercase">{{ row.weekday }}</div>
                    </div>
                    <div class="text-gray-200 text-xs sm:text-sm font-medium">{{ workedLabel(row) }}</div>
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
                        :style="{ left: `${seg.left}%`, width: `${seg.width}%`, ...(seg.style || {}) }"
                        :title="seg.title || ''"
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

          <div v-else class="h-full grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4">
            <section class="bg-gray-900 border border-gray-800 rounded-3xl p-5 flex flex-col min-h-0 shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-white font-semibold text-lg">Trabajando ahora</h2>
                  <p class="text-xs text-gray-400 mt-1">Personas con jornada activa hoy</p>
                </div>
                <div class="flex items-center gap-2">
                  <div class="px-3 py-1 rounded-full border border-emerald-500/40 bg-emerald-500/15 text-emerald-100 text-xs font-medium">
                    {{ whoIsInWorkingCount }} trabajando
                  </div>
                  <select
                    v-if="canViewAllWhoIsIn"
                    v-model="whoIsInTeamFilter"
                    class="h-9 rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm px-2.5"
                  >
                    <option value="">Todos los equipos</option>
                    <option v-for="t in whoIsInTeams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                  </select>
                </div>
              </div>

              <div v-if="whoIsInLoading" class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-3">
                <div v-for="i in 16" :key="i" class="h-16 rounded-xl bg-gray-800 animate-pulse"></div>
              </div>

              <div v-else class="flex-1 min-h-0 overflow-y-auto">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-4">
                  <div v-for="p in whoIsInRows" :key="p.attendance_id" class="flex flex-col items-center gap-1">
                    <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-emerald-400/70 bg-gray-700 flex items-center justify-center">
                      <img v-if="p.photo" :src="p.photo" class="w-full h-full object-cover" />
                      <span v-else class="text-xs text-gray-200 font-semibold">{{ (p.full_name || '?').charAt(0) }}</span>
                    </div>
                    <p class="text-[11px] text-gray-200 text-center leading-tight line-clamp-2">{{ p.full_name }}</p>
                    <p class="text-[10px] text-gray-500 text-center">{{ p.team_name || 'Sin equipo' }}</p>
                  </div>
                </div>

                <div v-if="!whoIsInRows.length" class="text-center text-sm text-gray-500 py-12">
                  No hay personas trabajando en este momento.
                </div>
              </div>
            </section>

            <aside class="bg-gray-900 border border-gray-800 rounded-3xl p-5 flex flex-col min-h-0 shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-white">Detalle de fichajes</h3>
                <button class="text-xs text-gray-300 border border-gray-700 rounded-lg px-2.5 py-1.5 hover:bg-gray-800" @click="loadWhoIsInData">
                  Actualizar
                </button>
              </div>

              <div class="flex-1 overflow-y-auto space-y-2">
                <div
                  v-for="p in whoIsInRows"
                  :key="`line-${p.attendance_id}`"
                  class="rounded-xl border border-gray-800 bg-gray-800/70 px-3 py-2"
                >
                  <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-sm text-white truncate">{{ p.full_name }}</p>
                      <p class="text-[11px] text-gray-400">Fichaje de entrada: {{ p.start_time || '--:--' }}</p>
                    </div>
                    <div class="text-sm font-semibold tabular-nums" :class="workedColorClass(p.worked_seconds)">
                      +{{ p.worked_hhmm || '00:00' }}
                    </div>
                  </div>
                </div>

                <div v-if="!whoIsInRows.length" class="text-sm text-gray-500 text-center py-8">
                  Sin movimientos activos.
                </div>
              </div>
            </aside>
          </div>
        </div>
      </main>
      <div v-if="requestModal.open" class="fixed inset-0 z-50 bg-black/70 p-4 flex items-center justify-center" @click.self="closeRequestModal">
        <div class="w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-4 space-y-4">
          <h2 class="text-white font-semibold">Solicitar fichaje</h2>
          <p class="text-xs text-gray-400">Selecciona la fecha para pedir regularizacion de entrada/salida.</p>
          <p v-if="!isOwnSelectedUser" class="text-xs text-amber-300">
            Debes seleccionar tu usuario para solicitar fichajes.
          </p>
          <input
            v-model="requestModal.date"
            type="date"
            class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
            :max="todayIso"
          />
          <div class="flex justify-end gap-2">
            <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="closeRequestModal">
              Cancelar
            </button>
            <button
              class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-50"
              :disabled="requestingAttendance || !requestModal.date || !isOwnSelectedUser"
              @click="confirmRequestModal"
            >
              Confirmar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeMount, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken, user } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const activeTab = ref<'monthly' | 'who_is_in'>('monthly')

const canViewAll = ref(false)
const users = ref<any[]>([])
const teams = ref<any[]>([])
const monthRows = ref<any[]>([])
const summary = ref<{ worked_hhmm: string; target_hhmm: string; worked_minutes: number; target_minutes: number }>({
  worked_hhmm: '00:00',
  target_hhmm: '00:00',
  worked_minutes: 0,
  target_minutes: 0,
})
const selectedUserId = ref<number | null>(null)
const teamFilter = ref('')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const includeNonWorkingDays = ref(false)

const canViewAllWhoIsIn = ref(false)
const whoIsInRows = ref<any[]>([])
const whoIsInLoading = ref(false)
const whoIsInTeamFilter = ref('')
const whoIsInTeams = ref<any[]>([])
const whoIsInWorkingCount = ref(0)
let whoIsInInterval: any = null
const requestingAttendance = ref(false)
const requestNotice = ref<{ type: 'success' | 'error'; text: string }>({ type: 'success', text: '' })

const dayStart = 6 * 60
const dayEnd = 19 * 60

const hourMarks = computed(() => Array.from({ length: 14 }, (_, i) => 6 + i))
const hourTicks = computed(() => hourMarks.value.map((h) => ((h * 60 - dayStart) / (dayEnd - dayStart)) * 100))

const selectedUserName = computed(() => {
  const u = users.value.find((x) => x.id === selectedUserId.value)
  return u?.full_name || 'Sin usuario'
})
const summaryPercent = computed(() => {
  if (!summary.value.target_minutes) return 0
  return Math.round((summary.value.worked_minutes / summary.value.target_minutes) * 100)
})

const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1, 1)
  return d.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})
const currentUserId = computed(() => Number((user.value as any)?.id || 0))
const todayIso = computed(() => new Date().toISOString().slice(0, 10))
const isOwnSelectedUser = computed(() => Number(selectedUserId.value || 0) === currentUserId.value)
const requestModal = ref<{ open: boolean; date: string }>({ open: false, date: '' })

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
  const segs: Array<{ left: number; width: number; className: string; style?: Record<string, string>; title?: string }> = []

  if (start !== null && end !== null && end > start) {
    const work1End = pause && pause > start && pause < end ? pause : end
    const l1 = pct(start)
    const w1 = Math.max(0, pct(work1End) - l1)
    if (w1 > 0) segs.push({ left: l1, width: w1, className: 'bg-green-500/90', title: 'Trabajo' })

    if (pause && pause < end) {
      const pauseEnd = resume && resume > pause && resume < end ? resume : end
      const lp = pct(pause)
      const wp = Math.max(0, pct(pauseEnd) - lp)
      if (wp > 0) segs.push({ left: lp, width: wp, className: 'bg-yellow-400/90', title: 'Pausa' })
    }

    if (resume && resume < end) {
      const l2 = pct(resume)
      const w2 = Math.max(0, pct(end) - l2)
      if (w2 > 0) segs.push({ left: l2, width: w2, className: 'bg-green-500/90', title: 'Trabajo' })
    }
  }

  for (const ev of row.events || []) {
    const color = ev?.color || '#60A5FA'
    const title = ev?.title ? `${ev.title} (${ev.event_type_name || 'Evento'})` : (ev?.event_type_name || 'Evento')
    if (ev?.all_day) {
      segs.push({
        left: 0,
        width: 100,
        className: 'border',
        style: { backgroundColor: `${color}55`, borderColor: color, zIndex: '3' },
        title,
      })
      continue
    }
    const evStart = toMinutes(ev?.start_time)
    const evEnd = toMinutes(ev?.end_time)
    if (evStart === null || evEnd === null || evEnd <= evStart) continue
    const left = pct(evStart)
    const width = Math.max(0, pct(evEnd) - left)
    if (width <= 0) continue
    segs.push({
      left,
      width,
      className: '',
      style: { backgroundColor: color, opacity: '0.85', zIndex: '3' },
      title,
    })
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

function workedColorClass(seconds?: number) {
  const s = Number(seconds || 0)
  if (s >= 8 * 3600) return 'text-emerald-300'
  if (s >= 4 * 3600) return 'text-cyan-300'
  return 'text-amber-300'
}

function missingAttendanceKind(row: any): 'entry_exit' | 'entry' | 'exit' | null {
  const hasStart = !!row?.start_time
  const hasEnd = !!row?.end_time
  if (!hasStart && !hasEnd) return 'entry_exit'
  if (!hasStart && hasEnd) return 'entry'
  if (hasStart && !hasEnd) return 'exit'
  return null
}

function canRequestAttendanceForRow(row: any): boolean {
  if (!row || !row.date) return false
  if (Number(selectedUserId.value || 0) !== currentUserId.value) return false
  if (row.attendance_request_pending) return false
  if (String(row.date) > todayIso.value) return false
  return missingAttendanceKind(row) !== null
}

function openRequestModal() {
  requestModal.value = {
    open: true,
    date: todayIso.value,
  }
}

function closeRequestModal() {
  requestModal.value = { open: false, date: '' }
}

async function requestAttendanceByDate(date: string) {
  if (!token.value || !date || requestingAttendance.value) return
  requestingAttendance.value = true
  requestNotice.value = { type: 'success', text: '' }
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/requests`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { date },
    })
    const kind = String(res?.request_kind || '')
    const kindLabel = kind === 'entry_exit' ? 'entrada y salida' : kind === 'entry' ? 'entrada' : kind === 'exit' ? 'salida' : 'fichaje'
    requestNotice.value = { type: 'success', text: `Solicitud enviada a RRHH para ${date} (${kindLabel}).` }
    await loadMonthData()
    closeRequestModal()
  } catch (e: any) {
    requestNotice.value = {
      type: 'error',
      text: e?.data?.message || 'No se pudo enviar la solicitud de fichaje.',
    }
  } finally {
    requestingAttendance.value = false
  }
}

async function confirmRequestModal() {
  const date = requestModal.value.date
  if (!date) {
    requestNotice.value = { type: 'error', text: 'Debes seleccionar una fecha.' }
    return
  }
  if (date > todayIso.value) {
    requestNotice.value = { type: 'error', text: 'No puedes solicitar fichajes para fechas futuras.' }
    return
  }
  const row = monthRows.value.find((r: any) => String(r?.date || '') === date)
  if (row && !canRequestAttendanceForRow(row)) {
    requestNotice.value = { type: 'error', text: 'Ese dia no tiene fichaje faltante o ya posee solicitud pendiente.' }
    return
  }
  await requestAttendanceByDate(date)
}

async function loadMonthData() {
  if (!token.value) return
  const params = new URLSearchParams()
  params.set('month', selectedMonth.value)
  if (teamFilter.value) params.set('team_id', teamFilter.value)
  if (selectedUserId.value) params.set('user_id', String(selectedUserId.value))
  params.set('include_non_working', includeNonWorkingDays.value ? '1' : '0')

  const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/month?${params.toString()}`, {
    headers: { Authorization: `Bearer ${token.value}` },
  })

  canViewAll.value = !!res?.can_view_all
  users.value = res?.users || []
  teams.value = res?.teams || []
  monthRows.value = res?.rows || []
  summary.value = res?.summary || summary.value
  includeNonWorkingDays.value = !!res?.include_non_working
  selectedUserId.value = res?.selected_user_id || null
  selectedMonth.value = res?.month || selectedMonth.value
}

async function loadWhoIsInData() {
  if (!token.value) return
  whoIsInLoading.value = true
  try {
    const params = new URLSearchParams()
    if (whoIsInTeamFilter.value) params.set('team_id', whoIsInTeamFilter.value)
    const qs = params.toString()
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/attendance/who-is-in${qs ? `?${qs}` : ''}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    canViewAllWhoIsIn.value = !!res?.can_view_all
    whoIsInRows.value = res?.rows || []
    whoIsInTeams.value = res?.teams || []
    whoIsInWorkingCount.value = Number(res?.working_count || 0)
  } catch (e) {
    console.error('who-is-in load failed', e)
    whoIsInRows.value = []
    whoIsInWorkingCount.value = 0
  } finally {
    whoIsInLoading.value = false
  }
}

function selectUser(id: number) {
  if (selectedUserId.value === id) return
  selectedUserId.value = id
  loadMonthData()
}

async function handleAttendanceChanged() {
  if (activeTab.value === 'who_is_in') {
    await loadWhoIsInData()
  } else {
    await loadMonthData()
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

watch([selectedMonth, teamFilter, includeNonWorkingDays], () => {
  if (activeTab.value !== 'monthly') return
  loadMonthData()
})

watch(whoIsInTeamFilter, () => {
  if (activeTab.value !== 'who_is_in') return
  loadWhoIsInData()
})

watch(activeTab, async (tab) => {
  if (tab === 'monthly') {
    await loadMonthData()
    return
  }
  await loadWhoIsInData()
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

  whoIsInInterval = setInterval(() => {
    if (activeTab.value !== 'who_is_in') return
    loadWhoIsInData()
  }, 30000)
})

onBeforeUnmount(() => {
  if (whoIsInInterval) clearInterval(whoIsInInterval)
})
</script>
