<template>
  <div class="flex min-h-screen bg-gray-950">
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
      <div class="absolute top-0 right-0 w-96 h-96 bg-blue-600 opacity-10 rounded-full blur-3xl"></div>
    </div>

    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <h1 class="text-base font-semibold text-white hidden sm:block">Dashboard</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" @changed="handleAttendanceChanged" />
          <UserMenu />
        </div>
      </header>

      <main ref="boardRoot" class="relative z-10 flex-1 p-6 space-y-6 overflow-auto">
        <div class="flex flex-wrap items-center gap-3">
          <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
            <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.users_count ?? 0 }}</div>
            <div class="text-xs text-gray-300">Usuarios</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
            <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.attendances_today ?? 0 }}</div>
            <div class="text-xs text-gray-300">Asistencias hoy</div>
          </div>
          <button
            class="ml-auto w-10 h-10 rounded-xl border inline-flex items-center justify-center transition"
            :class="isEditMode ? 'border-blue-500 bg-blue-600/20 text-blue-200' : 'border-gray-700 bg-gray-900 text-gray-200 hover:bg-gray-800'"
            :title="isEditMode ? 'Terminar de personalizar' : 'Personalizar mi dashboard'"
            aria-label="Personalizar mi dashboard"
            @click="toggleEditMode"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 113 3L12 14l-4 1 1-4 7.5-7.5z"/>
            </svg>
          </button>
        </div>

        <!-- Personal arrangement: hidden cards tray + back to the company layout -->
        <div
          v-if="isEditMode"
          data-dash-drop="hidden"
          class="rounded-2xl border-2 border-dashed p-4 transition"
          :class="dashDragging ? 'border-red-400/60 bg-red-500/5' : 'border-blue-500/40 bg-blue-500/5'"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-sm font-medium text-white">Estás personalizando tu dashboard</p>
              <p class="text-xs text-gray-400 mt-0.5">
                Arrastra las tarjetas para cambiarlas de lugar. Suéltalas aquí para ocultarlas o arrastra una oculta a cualquier posición.
                En el celular, mantén pulsada la tarjeta para arrastrarla. Los cambios solo los ves tú.
              </p>
            </div>
            <div class="flex gap-2">
              <button
                v-if="layoutSource === 'user'"
                class="text-xs px-3 py-1.5 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800"
                @click="resetToCompanyLayout"
              >
                Volver al diseño de la empresa
              </button>
              <button class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white" @click="toggleEditMode">Listo</button>
            </div>
          </div>
          <div data-dash-list class="mt-3 flex flex-wrap gap-2 min-h-[36px]">
            <div
              v-for="key in hiddenCards"
              :key="key"
              :data-dash-key="key"
              class="flex items-center gap-2 rounded-lg border border-gray-700 bg-gray-900 px-2.5 py-1.5 cursor-grab active:cursor-grabbing"
              :title="`Arrastra a una posición para mostrar ${DASHBOARD_WIDGETS[key].name}`"
            >
              <span class="w-6 h-6 rounded-md flex items-center justify-center" :class="DASHBOARD_WIDGETS[key].tone">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="DASHBOARD_WIDGETS[key].icon" />
                </svg>
              </span>
              <span class="text-xs text-gray-200">{{ DASHBOARD_WIDGETS[key].name }}</span>
            </div>
            <span v-if="!hiddenCards.length" class="pointer-events-none self-center text-xs text-gray-500">No tienes tarjetas ocultas.</span>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
          <div v-for="(column, cIdx) in boardColumns" :key="`col-${cIdx}`" class="space-y-4">
            <div
              v-for="slotIndex in visibleSlots(column)"
              :key="`slot-${slotIndex}`"
              class="rounded-2xl border transition"
              :data-dash-drop="isEditMode ? slotIndex : undefined"
              :data-dash-list="isEditMode ? '' : undefined"
              :class="[
                isEditMode ? 'min-h-40' : 'h-auto',
                isEditMode
                  ? (dashDragging ? 'border-blue-500/60 bg-blue-500/5 border-dashed' : 'border-gray-700 bg-gray-900/20 border-dashed')
                  : 'border-gray-800 bg-transparent'
              ]"
            >
              <div
                v-if="boardLayout[slotIndex]"
                class="rounded-2xl"
                :class="isEditMode ? 'cursor-grab active:cursor-grabbing' : ''"
                :data-dash-key="isEditMode ? boardLayout[slotIndex] : undefined"
              >
                <template v-if="boardLayout[slotIndex] === 'welcome'">
                  <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 min-h-52 shadow-xl shadow-blue-900/40">
                    <div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
                    <div class="relative">
                      <p class="text-blue-200 text-xs sm:text-sm font-medium mb-1">Panel de control</p>
                      <h2 class="text-xl sm:text-2xl font-bold text-white leading-tight">
                        {{ welcomeSettings.title || 'Bienvenido' }}{{ user?.name ? ', ' + user.name : '' }}
                      </h2>
                      <p v-if="welcomeSettings.show_date" class="mt-1 text-blue-100/90 text-xs">
                        {{ todayLabel }}
                      </p>
                      <p class="mt-2 text-blue-200 text-xs sm:text-sm">
                        {{ welcomeSettings.subtitle || 'Aquí tienes un resumen de la actividad de la empresa.' }}
                      </p>
                    </div>
                  </div>
                </template>
                <!-- Empty card collapsed to a slim bar (click to expand) -->
                <button
                  v-else-if="isCardCollapsed(boardLayout[slotIndex])"
                  type="button"
                  class="w-full flex items-center gap-3 rounded-2xl border border-gray-800 bg-gray-900 px-4 py-3 text-left hover:bg-gray-800/70 transition"
                  :aria-expanded="false"
                  :title="`Mostrar ${DASHBOARD_WIDGETS[boardLayout[slotIndex]!].name}`"
                  @click="toggleEmptyCard(boardLayout[slotIndex]!)"
                >
                  <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" :class="DASHBOARD_WIDGETS[boardLayout[slotIndex]!].tone">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="DASHBOARD_WIDGETS[boardLayout[slotIndex]!].icon" />
                    </svg>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block text-xs text-gray-300 uppercase tracking-wide">{{ DASHBOARD_WIDGETS[boardLayout[slotIndex]!].name }}</span>
                    <span class="block text-xs text-gray-500 truncate">{{ EMPTY_CARD_TEXT[boardLayout[slotIndex]!] }}</span>
                  </span>
                  <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-else class="relative">
                  <component
                    :is="cardComponent(boardLayout[slotIndex])"
                    v-bind="cardProps(boardLayout[slotIndex])"
                    v-on="cardListeners(boardLayout[slotIndex])"
                    class="w-full max-w-none"
                  />
                  <!-- Empty card the user expanded: let them collapse it again -->
                  <button
                    v-if="!isEditMode && isCardEmpty(boardLayout[slotIndex])"
                    type="button"
                    class="absolute top-3 right-3 z-10 w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 transition"
                    title="Contraer"
                    aria-label="Contraer tarjeta"
                    :aria-expanded="true"
                    @click="toggleEmptyCard(boardLayout[slotIndex]!)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                  </button>
                </div>
              </div>

              <div v-else-if="isEditMode" class="pointer-events-none h-full min-h-40 flex items-center justify-center text-xs text-gray-500">
                Posición libre — suelta una tarjeta aquí
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, ref, onBeforeMount, onMounted, watch, onBeforeUnmount } from 'vue'
import { DASHBOARD_COLUMNS, DASHBOARD_WIDGETS, DASHBOARD_WIDGET_KEYS, moveDashboardWidget, normalizeDashboardSlots } from '~/utils/dashboardWidgets'
import { useDashboardDrag } from '~/composables/useDashboardDrag'

definePageMeta({ auth: true })
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

declare const process: any
declare const $fetch: any

import BirthdayCard from '../components/BirthdayCard.vue'
import TeamCard from '../components/TeamCard.vue'
import VacationCard from '../components/VacationCard.vue'
import NotificationCard from '../components/NotificationCard.vue'
import WorkedHoursCard from '../components/WorkedHoursCard.vue'
import HolidaysCard from '../components/HolidaysCard.vue'
import AnnouncementsCard from '../components/AnnouncementsCard.vue'
import DocumentsSummaryCard from '../components/DocumentsSummaryCard.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'


const { authenticated, user, fetchUser, logout, apiBase, fetchUnread, unreadNotifications, lastReceivedMessage, realtimeInstance } = useAuth()

type CardKey = 'welcome' | 'birthdays' | 'team' | 'vacation' | 'notifications' | 'worked_hours' | 'holidays' | 'announcements' | 'documents'

async function markRead(id: number) {
  if (data.value.notifications) {
    data.value.notifications = data.value.notifications.filter((n: any) => n.conversation_id !== id)
  }
  await fetchUnread()
  data.value.unread_notifications = unreadNotifications.value || 0
}

const router = useRouter()
const data = ref<any>({})
const loading = ref(true)
const sidebar = ref<{ open: boolean } | null>(null)

const birthdays = ref<any[]>([])
const birthdaysLoading = ref(true)
const teamMembers = ref<any[]>([])
const teamName = ref('Sin equipo')
const teamLoading = ref(true)
const holidays = ref<any[]>([])
const holidaysLoading = ref(true)
const announcements = ref<any[]>([])
const announcementsLoading = ref(true)
const documentsSummary = ref<{ medical: number; receipt: number; payroll: number; total: number }>({
  medical: 0,
  receipt: 0,
  payroll: 0,
  total: 0,
})

const vacationLoading = ref(true)
const vacationInfo = ref<{
  daysUntilVacation: number | null
  vacationDaysTotal: number | null
  pendingToRequest: number | null
  remainingVacationDays: number | null
}>({
  daysUntilVacation: null,
  vacationDaysTotal: null,
  pendingToRequest: null,
  remainingVacationDays: null,
})

const boardLayout = ref<Array<CardKey | null>>(['welcome', 'birthdays', 'team', 'vacation', 'notifications', 'worked_hours', 'holidays', 'announcements', 'documents'])
const boardColumns = DASHBOARD_COLUMNS
// 'user' = personal arrangement, 'company' = layout defined by RRHH
const layoutSource = ref<'user' | 'company'>('company')
const hiddenCards = computed(() => DASHBOARD_WIDGET_KEYS.filter((k) => !boardLayout.value.includes(k)))
const isEditMode = ref(false)
const savingLayout = ref(false)
const welcomeSettings = ref<{ title: string; subtitle: string; show_date: boolean }>({
  title: 'Bienvenido',
  subtitle: 'Aquí tienes un resumen de la actividad de la empresa.',
  show_date: true,
})
const { $swal } = useNuxtApp()

// Company setting (Configuración > Límites de tarjetas):
// - birthdays: how many birthdays the card shows;
// - the other lists: how many items are visible before the list scrolls
//   (the whole list is still there, scrolling).
type ListCardKey = 'notifications' | 'announcements' | 'birthdays' | 'holidays' | 'team'
const DEFAULT_CARD_LIMIT = 5
const MAX_LIST_ITEMS = 50
const cardLimits = ref<Record<ListCardKey, number>>({
  notifications: DEFAULT_CARD_LIMIT,
  announcements: DEFAULT_CARD_LIMIT,
  birthdays: DEFAULT_CARD_LIMIT,
  holidays: DEFAULT_CARD_LIMIT,
  team: DEFAULT_CARD_LIMIT,
})
const limitList = <T,>(list: T[] | null | undefined, key: ListCardKey): T[] => (list || []).slice(0, cardLimits.value[key])

// ── Empty cards collapse to a slim bar ──
// Only cards that render a list/summary can be "empty"; vacation, worked hours
// and welcome always have something to show. A card counts as empty only once
// its data has loaded, so nothing collapses and re-opens while loading.
const documentsLoaded = ref(false)
const EMPTY_CARD_TEXT: Partial<Record<CardKey, string>> = {
  notifications: 'No hay notificaciones',
  announcements: 'Sin comunicados',
  birthdays: 'Sin próximos cumpleaños',
  holidays: 'Sin próximos festivos',
  team: 'Sin compañeros de equipo',
  documents: 'Sin documentos',
}

function isCardEmpty(key: CardKey | null): boolean {
  if (key === 'notifications') return !loading.value && !(data.value.notifications || []).length
  if (key === 'announcements') return !announcementsLoading.value && !announcements.value.length
  if (key === 'birthdays') return !birthdaysLoading.value && !birthdays.value.length
  if (key === 'holidays') return !holidaysLoading.value && !holidays.value.length
  if (key === 'team') return !teamLoading.value && !teamMembers.value.length
  if (key === 'documents') return documentsLoaded.value && !documentsSummary.value.total
  return false
}

// Empty cards the viewer chose to keep open (remembered in this browser).
const EXPANDED_EMPTY_KEY = 'dashboard:expandedEmptyCards'
const expandedEmptyCards = ref<CardKey[]>([])

function isCardCollapsed(key: CardKey | null): boolean {
  return !!key && !isEditMode.value && isCardEmpty(key) && !expandedEmptyCards.value.includes(key)
}

function toggleEmptyCard(key: CardKey) {
  expandedEmptyCards.value = expandedEmptyCards.value.includes(key)
    ? expandedEmptyCards.value.filter((k) => k !== key)
    : [...expandedEmptyCards.value, key]
  try {
    localStorage.setItem(EXPANDED_EMPTY_KEY, JSON.stringify(expandedEmptyCards.value))
  } catch {
    // storage blocked: the toggle still works for this visit
  }
}

onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(EXPANDED_EMPTY_KEY) || '[]')
    if (Array.isArray(saved)) expandedEmptyCards.value = saved.filter((k) => k in EMPTY_CARD_TEXT)
  } catch {
    // no saved state: every empty card starts collapsed
  }
})
// ── End empty cards ──

const cardComponent = (key: CardKey | null) => {
  if (key === 'birthdays') return BirthdayCard
  if (key === 'team') return TeamCard
  if (key === 'vacation') return VacationCard
  if (key === 'worked_hours') return WorkedHoursCard
  if (key === 'holidays') return HolidaysCard
  if (key === 'announcements') return AnnouncementsCard
  if (key === 'documents') return DocumentsSummaryCard
  return NotificationCard
}

const cardProps = (key: CardKey | null) => {
  if (key === 'birthdays') return { birthdays: limitList(birthdays.value, 'birthdays'), loading: birthdaysLoading.value }
  if (key === 'team') return { members: teamMembers.value, teamName: teamName.value, loading: teamLoading.value, visibleRows: cardLimits.value.team }
  if (key === 'vacation') {
    return {
      daysUntilVacation: vacationInfo.value.daysUntilVacation,
      vacationDaysTotal: vacationInfo.value.vacationDaysTotal,
      pendingToRequest: vacationInfo.value.pendingToRequest,
      remainingVacationDays: vacationInfo.value.remainingVacationDays,
      loading: vacationLoading.value,
    }
  }
  if (key === 'worked_hours') {
    return {
      workedHHMM: data.value?.worked_hours?.worked_hhmm || null,
      monthWorkedHHMM: data.value?.worked_hours?.worked_month_hhmm || null,
      monthTargetHHMM: data.value?.worked_hours?.target_month_hhmm || null,
    }
  }
  if (key === 'holidays') return { holidays: holidays.value, loading: holidaysLoading.value, visibleRows: cardLimits.value.holidays }
  if (key === 'announcements') return { items: announcements.value, loading: announcementsLoading.value, visibleRows: cardLimits.value.announcements }
  if (key === 'documents') return { summary: documentsSummary.value }
  return {
    notifications: data.value.notifications || [],
    visibleRows: cardLimits.value.notifications,
    unreadCount: unreadNotifications.value || 0,
  }
}

const cardListeners = (key: CardKey | null) => {
  if (key === 'notifications') {
    return { 'notification-read': markRead }
  }
  return {}
}

const todayLabel = (() => {
  const now = new Date()
  return now.toLocaleDateString('es-AR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
})()

function toggleEditMode() {
  isEditMode.value = !isEditMode.value
}

// Drag & drop (SortableJS: mouse + touch), only active in edit mode.
const boardRoot = ref<HTMLElement | null>(null)
const { dragging: dashDragging } = useDashboardDrag(boardRoot, (key, target) => {
  if (target === 'hidden' ? !boardLayout.value.includes(key) : boardLayout.value[target] === key) return
  boardLayout.value = moveDashboardWidget(boardLayout.value, key, target)
  void saveDashboardLayout()
}, isEditMode)

function visibleSlots(column: number[]) {
  if (isEditMode.value) return column
  return column.filter((slotIndex) => boardLayout.value[slotIndex] !== null)
}

async function resetToCompanyLayout() {
  try {
    const res: any = await $fetch('/api/dashboard/layout', { method: 'DELETE' })
    applyLayout(res?.layout)
    $swal.toast('success', 'Volviste al diseño de la empresa')
  } catch (e) {
    $swal.toast('error', 'No se pudo restablecer el dashboard')
  }
}

function applyLayout(res: any) {
  if (!res) return
  boardLayout.value = normalizeDashboardSlots(res.slots)
  layoutSource.value = res.source === 'user' ? 'user' : 'company'
  const ws = res?.settings?.welcome || {}
  welcomeSettings.value = {
    title: typeof ws?.title === 'string' && ws.title.trim() ? ws.title.trim() : 'Bienvenido',
    subtitle: typeof ws?.subtitle === 'string' && ws.subtitle.trim() ? ws.subtitle.trim() : 'Aquí tienes un resumen de la actividad de la empresa.',
    show_date: ws?.show_date !== false,
  }
  const limits = res?.settings?.limits || {}
  for (const key of Object.keys(cardLimits.value) as ListCardKey[]) {
    const n = Number(limits[key])
    if (Number.isFinite(n) && n > 0) cardLimits.value[key] = n
  }
}

async function loadDashboardLayout() {
  if (!authenticated.value) return
  try {
    applyLayout(await $fetch(`/api/dashboard/layout`))
  } catch (e) {
    console.error('dashboard layout load failed', e)
  }
}

async function saveDashboardLayout() {
  if (!authenticated.value) return
  if (savingLayout.value) return
  savingLayout.value = true
  try {
    await $fetch(`/api/dashboard/layout`, {
      method: 'PUT',
      body: { slots: boardLayout.value },
    })
    layoutSource.value = 'user'
    $swal.toast('success', 'Tu dashboard se guardó')
  } catch (e) {
    console.error('dashboard layout save failed', e)
    $swal.toast('error', 'No se pudo guardar el dashboard')
  } finally {
    savingLayout.value = false
  }
}

// ── Live "Asistencias hoy": anyone in the company clocks in/out ──
let companyAttendanceChannel: any = null
let statsRefreshTimer: ReturnType<typeof setTimeout> | null = null

async function refreshStats() {
  try {
    const fresh: any = await $fetch('/api/dashboard')
    data.value.stats = fresh?.stats || data.value.stats
  } catch (e) {
    console.error('dashboard stats refresh failed', e)
  }
}

function subscribeCompanyAttendance() {
  const echo: any = realtimeInstance?.()
  const companyId = Number((user.value as any)?.company_id || 0)
  if (!echo || !companyId || companyAttendanceChannel) return
  companyAttendanceChannel = echo.private(`company.${companyId}.attendance`)
  companyAttendanceChannel.listen('.CompanyAttendanceChanged', () => {
    // several people clocking in at 9:00 -> one request
    if (statsRefreshTimer) clearTimeout(statsRefreshTimer)
    statsRefreshTimer = setTimeout(() => { void refreshStats() }, 400)
  })
}

onBeforeUnmount(() => {
  if (statsRefreshTimer) clearTimeout(statsRefreshTimer)
  try {
    companyAttendanceChannel?.stopListening?.('.CompanyAttendanceChanged')
  } catch {
    // ignore
  }
  companyAttendanceChannel = null
})

onBeforeMount(async () => {
  await fetchUser()
  subscribeCompanyAttendance()

  await loadDashboardLayout()

  try {
    data.value = await $fetch(`/api/dashboard`, {
    })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }

  birthdaysLoading.value = true
  try {
    const res = await $fetch(`/api/birthdays?limit=${cardLimits.value.birthdays}`)
    birthdays.value = res || []
  } catch (err) {
    console.error('birthdays fetch failed', err)
    birthdays.value = []
  } finally {
    birthdaysLoading.value = false
  }

  await refreshTeam()
  await refreshHolidays()
  await refreshAnnouncements()
  await refreshDocumentsSummary()

  vacationLoading.value = true
  try {
    const vac = await $fetch(`/api/vacations/me`, {
    })
    vacationInfo.value = {
      daysUntilVacation: vac?.days_until_vacation != null ? Math.round(Number(vac.days_until_vacation)) : null,
      vacationDaysTotal: vac?.vacation_days_requested_year != null ? Math.round(Number(vac.vacation_days_requested_year)) : null,
      pendingToRequest: vac?.remaining_vacation_days != null ? Math.round(Number(vac.remaining_vacation_days)) : null,
      remainingVacationDays: vac?.remaining_vacation_days != null ? Math.round(Number(vac.remaining_vacation_days)) : null,
    }
  } catch (e) {
    console.error('vacation fetch failed', e)
  } finally {
    vacationLoading.value = false
  }
})

async function refreshTeam() {
  teamLoading.value = true
  try {
    const res = await $fetch(`/api/team/me`, {
    })
    teamName.value = (typeof res?.team_name === 'string' && res.team_name.trim()) ? res.team_name.trim() : 'Sin equipo'
    teamMembers.value = Array.isArray(res?.members) ? res.members : []
  } catch (e) {
    console.error('team fetch failed', e)
    teamName.value = 'Sin equipo'
    teamMembers.value = []
  } finally {
    teamLoading.value = false
  }
}

async function handleAttendanceChanged() {
  await refreshTeam()
  await refreshStats()
}

async function refreshHolidays() {
  holidaysLoading.value = true
  try {
    const res = await $fetch(`/api/holidays/upcoming?limit=${MAX_LIST_ITEMS}`)
    holidays.value = res || []
  } catch (e) {
    console.error('holidays fetch failed', e)
    holidays.value = []
  } finally {
    holidaysLoading.value = false
  }
}

async function refreshAnnouncements() {
  announcementsLoading.value = true
  try {
    const res = await $fetch(`/api/announcements?limit=${MAX_LIST_ITEMS}`)
    announcements.value = res || []
  } catch (e) {
    console.error('announcements fetch failed', e)
    announcements.value = []
  } finally {
    announcementsLoading.value = false
  }
}

async function refreshDocumentsSummary() {
  try {
    const res = await $fetch(`/api/documents/summary`, {
    })
    documentsSummary.value = {
      medical: Number(res?.medical || 0),
      receipt: Number(res?.receipt || 0),
      payroll: Number(res?.payroll || 0),
      total: Number(res?.total || 0),
    }
  } catch (e) {
    console.error('documents summary fetch failed', e)
  } finally {
    documentsLoaded.value = true
  }
}

async function refreshDashboardNotifications() {
  if (!authenticated.value) return
  try {
    const fresh = await $fetch(`/api/dashboard`, {
    })
    data.value.notifications = fresh?.notifications || []
    data.value.unread_notifications = unreadNotifications.value || fresh?.unread_notifications || 0
  } catch (e) {
    console.error('dashboard realtime refresh failed', e)
  }
}

const stopRealtimeWatch = watch(lastReceivedMessage, (e) => {
  if (!e) return
  refreshDashboardNotifications()
})

const stopUnreadWatch = watch(unreadNotifications, (count) => {
  data.value.unread_notifications = count || 0
})

onBeforeUnmount(() => {
  stopRealtimeWatch()
  stopUnreadWatch()
})

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}
</script>
