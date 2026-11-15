<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />
    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Vacaciones y Ausencias</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-4 sm:p-6 overflow-auto space-y-4">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <!-- view toggle + year stay together on one line, also on phones -->
          <div class="flex items-center gap-2 sm:gap-3">
            <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900">
              <button
                class="px-3 py-1.5 rounded-lg text-sm transition"
                :class="viewMode === 'year' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="viewMode = 'year'"
              >
                Anual
              </button>
              <button
                class="px-3 py-1.5 rounded-lg text-sm transition"
                :class="viewMode === 'month' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="viewMode = 'month'"
              >
                Mensual
              </button>
            </div>

            <div class="inline-flex items-center gap-0.5 sm:gap-2 rounded-xl border border-gray-700 px-1 sm:px-2 py-1 bg-gray-900">
              <button class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-gray-300 hover:bg-gray-800" aria-label="Año anterior" @click="changeYear(-1)">&#60;</button>
              <span class="text-white font-semibold w-12 sm:w-16 text-center tabular-nums">{{ selectedYear }}</span>
              <button class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-gray-300 hover:bg-gray-800" aria-label="Año siguiente" @click="changeYear(1)">&#62;</button>
            </div>
          </div>

          <div
            v-if="canHidePastMonths"
            class="px-3 py-1.5 rounded-xl bg-gray-900 border border-gray-700 inline-flex items-center gap-2 text-sm text-gray-300 select-none"
          >
            <span>Ver meses pasados</span>
            <button
              type="button"
              role="switch"
              :aria-checked="showPastMonths"
              @click="showPastMonths = !showPastMonths"
              :class="[
                'relative inline-flex h-5 w-10 items-center rounded-full transition',
                showPastMonths ? 'bg-blue-600' : 'bg-gray-600'
              ]"
              aria-label="Ver meses pasados"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition',
                  showPastMonths ? 'translate-x-5' : 'translate-x-1'
                ]"
              />
            </button>
          </div>

          <div v-if="viewMode === 'month'" class="inline-flex items-center gap-2 rounded-xl border border-gray-700 px-2 py-1 bg-gray-900">
            <button class="w-8 h-8 rounded-lg text-gray-300 hover:bg-gray-800" @click="changeMonth(-1)">&#60;</button>
            <span class="text-white font-semibold w-40 text-center">{{ monthLabel(selectedYear, selectedMonth) }}</span>
            <button class="w-8 h-8 rounded-lg text-gray-300 hover:bg-gray-800" @click="changeMonth(1)">&#62;</button>
          </div>

          <div class="ml-auto text-xs text-gray-300">
            Dias laborales del horario: {{ scheduleDaysText }}
          </div>
        </div>

        <div
          v-if="tapStartIso"
          class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-sm text-blue-100"
        >
          <span>
            Inicio: <strong>{{ tapStartIso }}</strong> — mueve el ratón y haz clic (o toca) en el día fin para cerrar el rango (o en el mismo día para un solo día).
            <span class="block text-xs mt-0.5" :class="excessDates.size ? 'text-red-300' : 'text-blue-200'">
              Días de vacaciones seleccionados: {{ selectionVacationDays }} · Disponibles: {{ vacationAvailableDays }}
              <template v-if="excessDates.size"> · Excedes en {{ excessDates.size }}</template>
            </span>
          </span>
          <button class="text-xs border border-blue-400/40 rounded-lg px-2 py-1.5 hover:bg-blue-500/20" @click="cancelTapSelection">
            Cancelar
          </button>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="i in visibleYearMonths.length" :key="'skel-' + i" class="rounded-2xl border border-gray-800 bg-gray-900 p-3 space-y-2">
            <div class="h-4 w-24 rounded bg-gray-800 animate-pulse"></div>
            <div class="grid grid-cols-7 gap-1">
              <div v-for="j in 7" :key="'skhd-' + j" class="h-3 rounded bg-gray-800 animate-pulse"></div>
            </div>
            <div class="grid grid-cols-7 gap-1">
              <div v-for="j in 35" :key="'skc-' + j" class="h-8 rounded-md bg-gray-800 animate-pulse"></div>
            </div>
          </div>
        </div>

        <div v-else-if="viewMode === 'year'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div
            v-for="month in visibleYearMonths"
            :key="month.key"
            class="rounded-2xl border border-gray-800 bg-gray-900 p-3"
          >
            <div class="text-sm font-semibold text-white mb-2">{{ month.label }}</div>
            <div class="grid grid-cols-7 text-[10px] text-gray-500 mb-1">
              <div v-for="d in weekHeaders" :key="d" class="text-center py-1">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7 gap-1">
              <button
                v-for="cell in month.cells"
                :key="cell.key"
                class="h-8 rounded-md text-[11px] relative"
                :class="dayCellClass(cell.iso, cell.currentMonth)"
                :disabled="isPastIso(cell.iso) || !isWorkingIso(cell.iso)"
                @pointerdown="onDayPointerDown(cell.iso, $event)"
                @pointerenter="onDayPointerEnter(cell.iso, $event)"
                @pointerup="onDayPointerUp"
              >
                {{ cell.day }}
                <!-- type icon in the event colour + colour bar -->
                <svg
                  v-if="eventsByDate[cell.iso]?.length"
                  class="absolute top-0.5 right-0.5 w-2.5 h-2.5 pointer-events-none"
                  :style="{ color: eventsByDate[cell.iso][0].display_color || '#3B82F6' }"
                  fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" :d="eventTypeVisual(eventsByDate[cell.iso][0].event_type_code, eventsByDate[cell.iso][0].event_type_name).icon" />
                </svg>
                <span
                  v-if="eventsByDate[cell.iso]?.length"
                  class="absolute left-1 right-1 bottom-1 h-1 rounded-full"
                  :style="{ backgroundColor: eventsByDate[cell.iso][0].display_color || '#3B82F6' }"
                />
              </button>
            </div>
          </div>
        </div>

        <div v-else class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
          <div class="grid grid-cols-7 text-xs text-gray-500 mb-2">
            <div v-for="d in weekHeaders" :key="d" class="text-center py-1">{{ d }}</div>
          </div>
          <div class="grid grid-cols-7 gap-1">
            <button
              v-for="cell in monthCells"
              :key="cell.key"
              class="h-20 rounded-lg p-2 text-left relative"
              :class="dayCellClass(cell.iso, cell.currentMonth)"
              :disabled="isPastIso(cell.iso) || !isWorkingIso(cell.iso)"
              @pointerdown="onDayPointerDown(cell.iso, $event)"
              @pointerenter="onDayPointerEnter(cell.iso, $event)"
              @pointerup="onDayPointerUp"
            >
              <div class="text-sm">{{ cell.day }}</div>
              <div class="mt-1 space-y-1">
                <div
                  v-for="ev in (eventsByDate[cell.iso] || []).slice(0, 2)"
                  :key="`${cell.iso}-${ev.id}`"
                  class="flex items-center gap-1 min-w-0 text-[10px] px-1.5 py-0.5 rounded"
                  :style="{ backgroundColor: `${ev.display_color || '#1D4ED8'}33`, color: ev.display_color || '#93C5FD' }"
                  :title="`${ev.title} · ${statusShort(ev.approval_status)}`"
                >
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="eventTypeVisual(ev.event_type_code, ev.event_type_name).icon" />
                  </svg>
                  <span class="truncate">{{ ev.title }} · {{ statusShort(ev.approval_status) }}</span>
                </div>
                <div v-if="(eventsByDate[cell.iso] || []).length > 2" class="text-[10px] text-gray-400">
                  +{{ (eventsByDate[cell.iso] || []).length - 2 }} mas
                </div>
              </div>
            </button>
          </div>
        </div>

        <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
          <h2>
            <button
              type="button"
              class="w-full flex items-center justify-between gap-2 text-left"
              @click="toggleRequests"
            >
              <span class="text-sm font-semibold text-white">Estado de mis solicitudes</span>
              <svg class="w-4 h-4 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': requestsOpen }" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd" />
              </svg>
            </button>
          </h2>
          <div v-if="requestsOpen" class="mt-3">
            <div v-if="!recentRequests.length" class="text-sm text-gray-500">Todavia no hay solicitudes en este periodo.</div>
            <div v-else class="space-y-2">
            <div
              v-for="req in recentRequests"
              :key="`req-${req.id}`"
              class="rounded-xl border border-gray-800 bg-gray-950 px-3 py-2"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 flex items-center gap-2 text-sm text-gray-200">
                  <span class="w-6 h-6 shrink-0 rounded-md flex items-center justify-center" :class="eventTypeVisual(req.event_type_code, req.event_type_name).tone">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="eventTypeVisual(req.event_type_code, req.event_type_name).icon" />
                    </svg>
                  </span>
                  <span class="truncate">{{ req.title }}</span>
                </p>
                <span class="text-[11px] px-2 py-1 rounded-full border uppercase tracking-wide" :class="statusBadgeClass(req.approval_status)">
                  {{ statusLabel(req.approval_status) }}
                </span>
              </div>
              <p class="text-xs text-gray-400">{{ req.start_date }} a {{ req.end_date }} · {{ req.event_type_name }}</p>
              <p v-if="!req.all_day" class="text-xs text-blue-300">Horario: {{ req.start_time }} - {{ req.end_time }}</p>
              <p v-if="req.approval_comment" class="text-xs text-amber-200 mt-1">Motivo RRHH: {{ req.approval_comment }}</p>
            </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="closeModal">
      <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-900 p-5 space-y-4">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h2 class="text-lg font-semibold text-white">{{ isEditMode ? 'Editar evento' : 'Nuevo evento' }}</h2>
            <p class="text-sm text-gray-400">Seleccionado: {{ selectedRangeText }}</p>
          </div>
          <button class="text-gray-400 hover:text-white" @click="closeModal">X</button>
        </div>

        <div class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-300">
          Dias totales: {{ selectedTotalDays }} | Dias laborables: {{ selectedWorkingDays }}
        </div>
        <div class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-300">
          Dias seleccionados: {{ selectedDaysSummary }}
        </div>
        <div class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-xs text-gray-300">
          Al guardar, la solicitud queda <strong>pendiente</strong> y RRHH debe aprobarla.
        </div>

        <div class="grid gap-3">
          <!-- the type is also the title of the request -->
          <div>
            <span class="block text-xs text-gray-400 mb-1.5">Tipo de evento</span>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Tipo de evento">
              <button
                v-for="t in eventTypes"
                :key="t.id"
                type="button"
                role="radio"
                :aria-checked="form.event_type_id === t.id"
                class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-sm transition"
                :class="form.event_type_id === t.id ? 'border-blue-500 bg-blue-600/15 text-white' : 'border-gray-700 bg-gray-800 text-gray-300 hover:border-gray-500'"
                @click="form.event_type_id = t.id"
              >
                <span class="w-7 h-7 shrink-0 rounded-md flex items-center justify-center" :class="eventTypeVisual(t.code, t.name).tone">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="eventTypeVisual(t.code, t.name).icon" />
                  </svg>
                </span>
                <span class="truncate">{{ t.name }}</span>
                <span class="ml-auto w-2.5 h-2.5 shrink-0 rounded-full" :style="{ backgroundColor: eventTypeVisual(t.code, t.name).color }" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div>
            <label for="event-description" class="block text-xs text-gray-400 mb-1">
              Descripción
              <span v-if="descriptionRequired" class="text-red-300">*</span>
              <span v-else class="text-gray-500">(opcional)</span>
            </label>
            <textarea
              id="event-description"
              v-model="form.description"
              maxlength="1000"
              :placeholder="descriptionRequired ? 'Cuéntale a RRHH el motivo' : ''"
              class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white min-h-20"
            />
          </div>
          <div>
            <label v-if="canChooseHours" class="inline-flex items-center gap-2 text-sm text-gray-200">
              <input v-model="form.all_day" type="checkbox" class="accent-blue-600" />
              Evento de dia completo
            </label>
            <p v-else-if="selectedTotalDays > 1" class="text-xs text-gray-400">
              Para varios dias solo se permite evento de dia completo.
            </p>
            <p v-else class="text-xs text-gray-400">
              No hay horario laboral configurado para habilitar rango horario.
            </p>
          </div>
          <div v-if="canChooseHours && !form.all_day" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Hora inicio</label>
              <input
                v-model="form.start_time"
                type="time"
                :min="scheduleStartTime || undefined"
                :max="scheduleEndTime || undefined"
                class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Hora fin</label>
              <input
                v-model="form.end_time"
                type="time"
                :min="scheduleStartTime || undefined"
                :max="scheduleEndTime || undefined"
                class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
              />
            </div>
          </div>
        </div>

        <div v-if="errorMessage" class="text-sm text-red-300">{{ errorMessage }}</div>

        <div class="flex justify-end gap-2">
          <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="closeModal">Cancelar</button>
          <template v-if="isEditMode ? canEditEvents : canCreateEvents">
            <button
              class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50"
              :disabled="saving || !canSave"
              @click="saveEvent"
            >
              {{ saving ? 'Guardando...' : (isEditMode ? 'Guardar cambios' : 'Guardar evento') }}
            </button>
          </template>
          <span v-else class="text-xs text-gray-400 self-center">No tienes permisos para {{ isEditMode ? 'editar' : 'crear' }} eventos.</span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppSidebar from '../components/AppSidebar.vue'

const { $swal } = useNuxtApp()


definePageMeta({ auth: true })
declare const $fetch: any

type CalendarEvent = {
  id: number
  title: string
  description: string | null
  start_date: string
  end_date: string
  start_time: string
  end_time: string
  all_day: boolean
  color: string | null
  display_color: string | null
  event_type_code?: string | null
  event_type_name?: string | null
  approval_status: 'pending' | 'approved' | 'rejected'
  approval_comment: string | null
  reviewed_at: string | null
  event_type_id: number
  event_type_name: string
  created_at: string | null
}

const router = useRouter()
const { authenticated, apiBase, fetchUser, logout, user } = useAuth()
const sidebar = ref<{ open: boolean } | null>(null)

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const todayIso = toIsoDate(new Date())
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth())
const viewMode = ref<'year' | 'month'>('year')

const eventTypes = ref<Array<{ id: number; name: string; code?: string | null }>>([])
const selectedEventType = computed(() => eventTypes.value.find((t) => t.id === form.value.event_type_id))
// General and Permiso need a description; the title is always the type name
const descriptionRequired = computed(() => DESCRIPTION_REQUIRED_CODES.includes(String(selectedEventType.value?.code || '')))
const events = ref<CalendarEvent[]>([])
const scheduleDays = ref<string[]>(['L', 'M', 'X', 'J', 'V'])
const scheduleStartTime = ref<string | null>(null)
const scheduleEndTime = ref<string | null>(null)

const showModal = ref(false)
const editingEventId = ref<number | null>(null)
const holidays = ref<Record<string, string>>({})
const vacationTypeId = ref<number | null>(null)
const vacationLimit = ref(0)
const dragStartIso = ref('')
const dragEndIso = ref('')
const tapStartIso = ref('')
const pendingTapIso = ref('')
const touchDownPt = ref<{ x: number; y: number } | null>(null)
const requestsOpen = ref(true)

function toggleRequests() {
  requestsOpen.value = !requestsOpen.value
  try {
    localStorage.setItem('rrhh_vac_requests_open', requestsOpen.value ? '1' : '0')
  } catch {}
}

const form = ref({
  event_type_id: 0,
  title: '',
  description: '',
  all_day: true,
  start_time: '09:00',
  end_time: '18:00',
  color: '#3B82F6',
})

const weekHeaders = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const dayToIsoMap: Record<number, string> = { 1: 'L', 2: 'M', 3: 'X', 4: 'J', 5: 'V', 6: 'S', 7: 'D' }

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}

function toDateFromIso(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, (m || 1) - 1, d || 1)
}

function toIsoDate(date: Date): string {
  const y = date.getFullYear()
  const m = `${date.getMonth() + 1}`.padStart(2, '0')
  const d = `${date.getDate()}`.padStart(2, '0')
  return `${y}-${m}-${d}`
}

function sortRange(a: string, b: string): { start: string; end: string } {
  return a <= b ? { start: a, end: b } : { start: b, end: a }
}

function monthLabel(year: number, month: number): string {
  return `${monthNames[month]} ${year}`
}

function buildMonthCells(year: number, month: number) {
  const first = new Date(year, month, 1)
  const firstWeekDay = (first.getDay() + 6) % 7
  const start = new Date(year, month, 1 - firstWeekDay)
  const cells: Array<{ key: string; iso: string; day: number; currentMonth: boolean }> = []
  for (let i = 0; i < 42; i++) {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const iso = toIsoDate(d)
    cells.push({
      key: `${year}-${month}-${i}-${iso}`,
      iso,
      day: d.getDate(),
      currentMonth: d.getMonth() === month,
    })
  }
  return cells
}

const monthCells = computed(() => buildMonthCells(selectedYear.value, selectedMonth.value))

const yearMonths = computed(() =>
  Array.from({ length: 12 }, (_, m) => ({
    key: `${selectedYear.value}-${m}`,
    label: monthLabel(selectedYear.value, m),
    cells: buildMonthCells(selectedYear.value, m),
  })),
)

// Annual view of the current year starts at the current month so users
// (especially on mobile) don't have to scroll past already-gone months.
// Past/future years always show all 12 months.
const SHOW_PAST_MONTHS_KEY = 'vacaciones:showPastMonths'
const showPastMonths = ref(false)

// Remember the switch across reloads (read on mount: localStorage is client-only).
onMounted(() => {
  try {
    showPastMonths.value = localStorage.getItem(SHOW_PAST_MONTHS_KEY) === '1'
  } catch {
    // storage blocked (private mode, etc.): keep the default
  }
})

watch(showPastMonths, (value) => {
  try {
    localStorage.setItem(SHOW_PAST_MONTHS_KEY, value ? '1' : '0')
  } catch {
    // storage blocked: the switch still works for this visit
  }
})

const canHidePastMonths = computed(() => {
  const now = new Date()
  return viewMode.value === 'year'
    && selectedYear.value === now.getFullYear()
    && now.getMonth() > 0
})
const visibleYearMonths = computed(() => {
  if (!canHidePastMonths.value || showPastMonths.value) return yearMonths.value
  const currentMonth = new Date().getMonth()
  return yearMonths.value.slice(currentMonth)
})

const eventsByDate = computed<Record<string, CalendarEvent[]>>(() => {
  const map: Record<string, CalendarEvent[]> = {}
  for (const ev of events.value) {
    let cursor = toDateFromIso(ev.start_date)
    const end = toDateFromIso(ev.end_date)
    while (cursor <= end) {
      const iso = toIsoDate(cursor)
      if (!map[iso]) map[iso] = []
      map[iso].push(ev)
      cursor = new Date(cursor)
      cursor.setDate(cursor.getDate() + 1)
    }
  }
  return map
})

const selectedRange = computed(() => sortRange(dragStartIso.value || dragEndIso.value, dragEndIso.value || dragStartIso.value))

const selectedRangeText = computed(() => {
  if (!selectedRange.value.start) return ''
  return selectedRange.value.start === selectedRange.value.end
    ? selectedRange.value.start
    : `${selectedRange.value.start} a ${selectedRange.value.end}`
})
const selectedDaysSummary = computed(() => {
  if (!selectedRange.value.start || !selectedRange.value.end) return '-'
  if (selectedTotalDays.value <= 4) {
    const dates: string[] = []
    let cursor = toDateFromIso(selectedRange.value.start)
    const end = toDateFromIso(selectedRange.value.end)
    while (cursor <= end) {
      dates.push(toIsoDate(cursor))
      cursor = new Date(cursor)
      cursor.setDate(cursor.getDate() + 1)
    }
    return dates.join(', ')
  }
  return `${selectedRange.value.start} ... ${selectedRange.value.end}`
})

function countDaysInRange(startIso: string, endIso: string): number {
  if (!startIso || !endIso) return 0
  let cursor = toDateFromIso(startIso)
  const end = toDateFromIso(endIso)
  let count = 0
  while (cursor <= end) {
    count++
    cursor = new Date(cursor)
    cursor.setDate(cursor.getDate() + 1)
  }
  return count
}

function isWorkingIso(iso: string): boolean {
  const letter = dayToIsoMap[toDateFromIso(iso).getDay() === 0 ? 7 : toDateFromIso(iso).getDay()]
  return scheduleDays.value.includes(letter)
}

const selectedTotalDays = computed(() => countDaysInRange(selectedRange.value.start, selectedRange.value.end))
const selectedWorkingDays = computed(() => {
  if (!selectedRange.value.start || !selectedRange.value.end) return 0
  let cursor = toDateFromIso(selectedRange.value.start)
  const end = toDateFromIso(selectedRange.value.end)
  let count = 0
  while (cursor <= end) {
    const iso = toIsoDate(cursor)
    if (isWorkingIso(iso)) count++
    cursor = new Date(cursor)
    cursor.setDate(cursor.getDate() + 1)
  }
  return count
})

const canSave = computed(() => {
  if (!selectedRange.value.start || !selectedRange.value.end) return false
  if (!(form.value.event_type_id > 0)) return false
  if (descriptionRequired.value && !String(form.value.description || '').trim()) return false
  if (!canChooseHours.value) return true
  if (!form.value.all_day) {
    if (!form.value.start_time || !form.value.end_time) return false
    if (selectedRange.value.start === selectedRange.value.end && form.value.end_time <= form.value.start_time) return false
    if (scheduleStartTime.value && form.value.start_time < scheduleStartTime.value) return false
    if (scheduleEndTime.value && form.value.end_time > scheduleEndTime.value) return false
  }
  return true
})
const canChooseHours = computed(() => selectedTotalDays.value === 1 && !!scheduleStartTime.value && !!scheduleEndTime.value)

const canCreateEvents = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.can_create_vacations) || (Array.isArray(currentUser?.permissions) && currentUser.permissions.includes('vacations.create'))
})
const canEditEvents = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.can_edit_vacations) || (Array.isArray(currentUser?.permissions) && currentUser.permissions.includes('vacations.edit'))
})

const scheduleDaysText = computed(() => (scheduleDays.value.length ? scheduleDays.value.join(', ') : 'Sin definir'))
const isEditMode = computed(() => editingEventId.value !== null)
const recentRequests = computed(() => {
  return [...events.value]
    .sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')))
    .slice(0, 8)
})

function statusShort(status: string): string {
  if (status === 'approved') return 'ok'
  if (status === 'rejected') return 'no'
  return 'pend'
}

function statusLabel(status: string): string {
  if (status === 'approved') return 'Aprobada'
  if (status === 'rejected') return 'Rechazada'
  return 'Pendiente'
}

function statusBadgeClass(status: string): string {
  if (status === 'approved') return 'border-emerald-500/40 text-emerald-300 bg-emerald-500/10'
  if (status === 'rejected') return 'border-red-500/40 text-red-300 bg-red-500/10'
  return 'border-gray-500/40 text-gray-200 bg-gray-500/20'
}

// --- Vacation allowance (mirrors TimeOffController::create/update) --------
// A day consumes allowance when it is a schedule day and not a company holiday.
function isVacationCountableIso(iso: string): boolean {
  return isWorkingIso(iso) && !holidays.value[iso]
}

function eachIso(startIso: string, endIso: string, fn: (iso: string) => void) {
  let cursor = toDateFromIso(startIso)
  const end = toDateFromIso(endIso)
  while (cursor <= end) {
    fn(toIsoDate(cursor))
    cursor = new Date(cursor)
    cursor.setDate(cursor.getDate() + 1)
  }
}

// Event being extended/edited by the current selection: the API excludes its
// own days from the "already used" count, so we do the same.
const selectionSourceEventId = computed<number | null>(() => {
  if (editingEventId.value !== null) return editingEventId.value
  const startIso = tapStartIso.value || dragStartIso.value
  return startIso ? (firstEventForDay(startIso)?.id ?? null) : null
})

// Vacation days already requested (pending/approved) in the selection's year.
const usedVacationDates = computed(() => {
  const set = new Set<string>()
  const year = (selectedRange.value.start || todayIso).slice(0, 4)
  for (const ev of events.value) {
    if (ev.event_type_id !== vacationTypeId.value) continue
    if (ev.approval_status === 'rejected') continue
    if (ev.id === selectionSourceEventId.value) continue
    eachIso(ev.start_date, ev.end_date, (iso) => {
      if (iso.startsWith(year) && isVacationCountableIso(iso)) set.add(iso)
    })
  }
  return set
})

const vacationAvailableDays = computed(() => Math.max(0, vacationLimit.value - usedVacationDates.value.size))

// New vacation days in the selection, and those beyond the allowance (in date order).
const selectionNewVacationDates = computed(() => {
  const list: string[] = []
  if (!selectedRange.value.start || !selectedRange.value.end) return list
  eachIso(selectedRange.value.start, selectedRange.value.end, (iso) => {
    if (isVacationCountableIso(iso) && !usedVacationDates.value.has(iso)) list.push(iso)
  })
  return list
})
const selectionVacationDays = computed(() => selectionNewVacationDates.value.length)
const excessDates = computed(() => new Set(selectionNewVacationDates.value.slice(vacationAvailableDays.value)))

function isInSelectedRange(iso: string): boolean {
  if (!selectedRange.value.start || !selectedRange.value.end) return false
  if (!isWorkingIso(iso)) return false
  return iso >= selectedRange.value.start && iso <= selectedRange.value.end
}

function dayCellClass(iso: string, currentMonth: boolean): string {
  if (isPastIso(iso)) {
    return 'bg-gray-950 text-gray-700 border border-gray-900 cursor-not-allowed opacity-70'
  }
  const inRange = isInSelectedRange(iso)
  const base = currentMonth ? 'bg-gray-900 text-gray-100 hover:bg-gray-800' : 'bg-gray-950 text-gray-600 hover:bg-gray-900'
  if (inRange && excessDates.value.has(iso)) return 'bg-red-600/40 text-white border border-red-500'
  if (inRange) return 'bg-blue-600/35 text-white border border-blue-500'
  if (!isWorkingIso(iso)) return `${base} border border-gray-800/60`
  return `${base} border border-gray-800`
}

function isPastIso(iso: string): boolean {
  return iso < todayIso
}

function resetForm() {
  form.value = {
    event_type_id: eventTypes.value[0]?.id || 0,
    title: '',
    description: '',
    all_day: true,
    start_time: '09:00',
    end_time: '18:00',
    color: '#3B82F6',
  }
  errorMessage.value = ''
}

function fillFormFromEvent(ev: CalendarEvent) {
  form.value = {
    event_type_id: ev.event_type_id,
    title: ev.title || '',
    description: ev.description || '',
    all_day: !!ev.all_day,
    start_time: ev.start_time || (scheduleStartTime.value || '09:00'),
    end_time: ev.end_time || (scheduleEndTime.value || '18:00'),
    color: ev.color || '#3B82F6',
  }
}

function firstEventForDay(iso: string): CalendarEvent | null {
  const eventsForDay = eventsByDate.value[iso] || []
  if (!eventsForDay.length) return null
  const startingToday = eventsForDay.find((e) => e.start_date === iso)
  return startingToday || eventsForDay[0] || null
}

function openCreateModal() {
  editingEventId.value = null
  showModal.value = true
  resetForm()
}

function openEditModal(ev: CalendarEvent, rangeOverride?: { start: string; end: string }) {
  editingEventId.value = ev.id
  if (rangeOverride) {
    dragStartIso.value = rangeOverride.start
    dragEndIso.value = rangeOverride.end
  } else {
    dragStartIso.value = ev.start_date
    dragEndIso.value = ev.end_date
  }
  showModal.value = true
  fillFormFromEvent(ev)
}

function closeModal() {
  showModal.value = false
  editingEventId.value = null
  resetForm()
}

function onDayPointerDown(iso: string, e: PointerEvent) {
  if (e.button !== 0) return
  if (isPastIso(iso)) return
  if (!isWorkingIso(iso)) return
  if (e.pointerType === 'touch') {
    // decided on pointerup, so a scroll gesture is not taken as a tap
    pendingTapIso.value = iso
    touchDownPt.value = { x: e.clientX, y: e.clientY }
    return
  }
  // Mouse: click the first day, move the mouse (range previews), click the last day.
  handleDayTap(iso)
}

function onDayPointerEnter(iso: string, e: PointerEvent) {
  if (!tapStartIso.value || e.pointerType === 'touch') return
  if (isPastIso(iso)) return
  if (!isWorkingIso(iso)) return
  dragEndIso.value = iso
}

function onDayPointerUp(e?: PointerEvent) {
  const iso = pendingTapIso.value
  const startPt = touchDownPt.value
  pendingTapIso.value = ''
  touchDownPt.value = null
  if (!iso || !startPt || !e) return
  if (Math.hypot(e.clientX - startPt.x, e.clientY - startPt.y) > 10) return
  handleDayTap(iso)
}

function handleDayTap(iso: string) {
  if (!tapStartIso.value) {
    tapStartIso.value = iso
    dragStartIso.value = iso
    dragEndIso.value = iso
    return
  }
  const start = tapStartIso.value
  tapStartIso.value = ''
  const range = sortRange(start, iso)
  dragStartIso.value = range.start
  dragEndIso.value = range.end
  finishSelection(range.start, range.end)
}

function finishSelection(startIso: string, endIso: string) {
  const range = sortRange(startIso, endIso)
  const sourceDayEvent = firstEventForDay(startIso)

  // Over the allowance: keep the range on screen with the excess in red and
  // do not open the confirmation modal. (Computed before clearing tapStartIso,
  // which identifies the event being extended.)
  const excess = excessDates.value.size
  tapStartIso.value = ''
  if (excess > 0) {
    $swal.toast('error', `Superas tus días de vacaciones en ${excess} ${excess === 1 ? 'día' : 'días'} (disponibles: ${vacationAvailableDays.value})`)
    return
  }

  if (startIso === endIso) {
    if (sourceDayEvent) {
      openEditModal(sourceDayEvent)
      return
    }
    openCreateModal()
    return
  }

  // If user starts the selection on an existing event day, treat it as extending/
  // shrinking that same event instead of creating a new one.
  if (sourceDayEvent) {
    openEditModal(sourceDayEvent, range)
    return
  }

  openCreateModal()
}

function cancelTapSelection() {
  tapStartIso.value = ''
  pendingTapIso.value = ''
  touchDownPt.value = null
  dragStartIso.value = ''
  dragEndIso.value = ''
}

function onGlobalPointerUp(e: PointerEvent) {
  if (pendingTapIso.value) onDayPointerUp(e)
}

function onGlobalPointercancel() {
  pendingTapIso.value = ''
  touchDownPt.value = null
}

function changeYear(delta: number) {
  selectedYear.value += delta
  void loadCalendar()
}

function changeMonth(delta: number) {
  const prevYear = selectedYear.value
  const d = new Date(selectedYear.value, selectedMonth.value + delta, 1)
  selectedYear.value = d.getFullYear()
  selectedMonth.value = d.getMonth()
  if (selectedYear.value !== prevYear) {
    void loadCalendar()
  }
}

async function loadCalendar() {
  if (!authenticated.value) return
  loading.value = true
  try {
    const res: any = await $fetch(`/api/timeoff/calendar?year=${selectedYear.value}`, {
    })
    scheduleDays.value = Array.isArray(res?.schedule_days) && res.schedule_days.length ? res.schedule_days : ['L', 'M', 'X', 'J', 'V']
    scheduleStartTime.value = res?.schedule?.start_time || null
    scheduleEndTime.value = res?.schedule?.end_time || null
    holidays.value = res?.holidays || {}
    vacationTypeId.value = res?.vacation?.event_type_id ?? null
    vacationLimit.value = Number(res?.vacation?.limit || 0)
    eventTypes.value = res?.event_types || []
    events.value = res?.events || []
    if (!form.value.event_type_id && eventTypes.value.length) {
      form.value.event_type_id = eventTypes.value[0].id
    }
  } catch (e) {
    console.error('calendar load failed', e)
    $swal.toast('error', 'Error al cargar el calendario')
  } finally {
    loading.value = false
  }
}

async function saveEvent() {
  if (!canSave.value || !authenticated.value) return
  saving.value = true
  errorMessage.value = ''
  const wasEdit = isEditMode.value
  try {
    const method = wasEdit ? 'PUT' : 'POST'
    const url = wasEdit
      ? `/api/timeoff/events/${editingEventId.value}`
      : `/api/timeoff/events`

    await $fetch(url, {
      method,
      body: {
        title: selectedEventType.value?.name || '',
        description: form.value.description?.trim() || null,
        event_type_id: form.value.event_type_id,
        start_date: selectedRange.value.start,
        end_date: selectedRange.value.end,
        all_day: form.value.all_day,
        start_time: form.value.all_day ? null : form.value.start_time,
        end_time: form.value.all_day ? null : form.value.end_time,
        // colour: fixed by the event type (server side)
      },
    })
    closeModal()
    await loadCalendar()
    $swal.toast('success', wasEdit ? 'Solicitud actualizada y enviada a RRHH' : 'Solicitud enviada a RRHH')
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'No se pudo guardar el evento'
    $swal.toast('error', errorMessage.value)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const saved = localStorage.getItem('rrhh_vac_requests_open')
    if (saved !== null) requestsOpen.value = saved === '1'
  } catch {}

  await fetchUser()

  const currentUser: any = user.value || {}
  const canView = Boolean(currentUser?.can_view_vacations) || (Array.isArray(currentUser?.permissions) && currentUser.permissions.includes('vacations.view'))
  if (!canView) {
    router.push('/dashboard')
    return
  }

  await loadCalendar()
  window.addEventListener('pointerup', onGlobalPointerUp)
  window.addEventListener('pointercancel', onGlobalPointercancel)
  window.addEventListener('keydown', onSelectionKeydown)
})

function onSelectionKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && tapStartIso.value) cancelTapSelection()
}

watch(
  () => form.value.event_type_id,
  (typeId) => {
    const selectedType = eventTypes.value.find((t) => t.id === typeId)
    const isPermission = (selectedType?.name || '').toLowerCase().includes('permiso')
    if (isPermission && canChooseHours.value) {
      form.value.all_day = false
    }
  },
)

watch(canChooseHours, (singleDay) => {
  if (!singleDay) {
    form.value.all_day = true
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('pointerup', onGlobalPointerUp)
  window.removeEventListener('pointercancel', onGlobalPointercancel)
  window.removeEventListener('keydown', onSelectionKeydown)
})
</script>
