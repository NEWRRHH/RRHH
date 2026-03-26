<template>
  <div class="flex min-h-screen bg-gray-950">
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
      <div class="absolute top-0 right-0 w-96 h-96 bg-blue-600 opacity-10 rounded-full blur-3xl"></div>
    </div>

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

        <h1 class="text-base font-semibold text-white">Dashboard</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" @changed="handleAttendanceChanged" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 p-6 space-y-6 overflow-auto">
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
            :title="isEditMode ? 'Salir de modificacion' : 'Activar modificacion'"
            aria-label="Editar dashboard"
            @click="toggleEditMode"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 113 3L12 14l-4 1 1-4 7.5-7.5z"/>
            </svg>
          </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
          <div v-for="(column, cIdx) in boardColumns" :key="`col-${cIdx}`" class="space-y-4">
            <div
              v-for="slotIndex in visibleSlots(column)"
              :key="`slot-${slotIndex}`"
              class="rounded-2xl border transition"
              :class="[
                slotHeights[slotIndex],
                isEditMode
                  ? (dragOverSlot === slotIndex ? 'border-blue-500 bg-blue-500/5 border-dashed' : 'border-gray-700 bg-gray-900/20 border-dashed')
                  : 'border-gray-800 bg-transparent'
              ]"
              @dragover.prevent="isEditMode ? onSlotDragOver(slotIndex) : null"
              @dragleave="isEditMode ? onSlotDragLeave(slotIndex) : null"
              @drop.prevent="isEditMode ? onSlotDrop(slotIndex) : null"
            >
              <div
                v-if="boardLayout[slotIndex]"
                class="h-full rounded-2xl"
                :class="isEditMode ? 'cursor-grab active:cursor-grabbing' : ''"
                :draggable="isEditMode"
                @dragstart="onCardDragStart(slotIndex)"
                @dragend="onCardDragEnd"
              >
                <template v-if="boardLayout[slotIndex] === 'welcome'">
                  <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 h-full shadow-xl shadow-blue-900/40">
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
                        {{ isEditMode ? 'Modo modificacion activo: puedes reorganizar tus cards.' : (welcomeSettings.subtitle || 'Aqui tenes un resumen de la actividad del sistema.') }}
                      </p>
                    </div>
                  </div>
                </template>
                <component
                  v-else
                  :is="cardComponent(boardLayout[slotIndex])"
                  v-bind="cardProps(boardLayout[slotIndex])"
                  v-on="cardListeners(boardLayout[slotIndex])"
                  class="w-full h-full max-w-none"
                />
              </div>

              <div v-else-if="isEditMode" class="h-full flex items-center justify-center text-xs text-gray-500">
                Soltar card aqui
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <AppToast :show="toast.show" :message="toast.message" :type="toast.type" @close="toast.show = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount, watch, onBeforeUnmount } from 'vue'

// require authentication to view this page
definePageMeta({ auth: true })
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

declare const process: any
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
import AppToast from '../components/AppToast.vue'

declare const $fetch: any

const { token, user, fetchUser, logout, apiBase, setToken, fetchUnread, unreadNotifications, lastReceivedMessage } = useAuth()

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
const vacationInfo = ref<{ daysUntilVacation: number | null; vacationDays: number | null; remainingVacationDays: number | null }>({
  daysUntilVacation: null,
  vacationDays: null,
  remainingVacationDays: null,
})

const boardLayout = ref<Array<CardKey | null>>(['welcome', 'birthdays', 'team', 'vacation', 'notifications', 'worked_hours', 'holidays', 'announcements', 'documents'])
const boardColumns = [[0, 1, 2], [3, 4, 5], [6, 7, 8]]
const slotHeights = ['h-52', 'h-80', 'h-64', 'h-72', 'h-[22rem]', 'h-72', 'h-72', 'h-72', 'h-72']
const draggingSlot = ref<number | null>(null)
const dragOverSlot = ref<number | null>(null)
const isEditMode = ref(false)
const savingLayout = ref(false)
const welcomeSettings = ref<{ title: string; subtitle: string; show_date: boolean }>({
  title: 'Bienvenido',
  subtitle: 'Aqui tenes un resumen de la actividad del sistema.',
  show_date: true,
})
const toast = ref<{ show: boolean; type: 'success' | 'error'; message: string }>({
  show: false,
  type: 'success',
  message: '',
})
let toastTimer: any = null

function showToast(type: 'success' | 'error', message: string) {
  toast.value = { show: true, type, message }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 2800)
}

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
  if (key === 'birthdays') return { birthdays: birthdays.value, loading: birthdaysLoading.value }
  if (key === 'team') return { members: teamMembers.value, teamName: teamName.value, loading: teamLoading.value }
  if (key === 'vacation') {
    return {
      daysUntilVacation: vacationInfo.value.daysUntilVacation,
      vacationDays: vacationInfo.value.vacationDays,
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
  if (key === 'holidays') return { holidays: holidays.value, loading: holidaysLoading.value }
  if (key === 'announcements') return { items: announcements.value, loading: announcementsLoading.value }
  if (key === 'documents') return { summary: documentsSummary.value }
  return {
    notifications: data.value.notifications || [],
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
  if (!isEditMode.value) {
    onCardDragEnd()
  }
}

function visibleSlots(column: number[]) {
  if (isEditMode.value) return column
  return column.filter((slotIndex) => boardLayout.value[slotIndex] !== null)
}

function onCardDragStart(slotIndex: number) {
  if (!isEditMode.value) return
  if (!boardLayout.value[slotIndex]) return
  draggingSlot.value = slotIndex
}

function onCardDragEnd() {
  draggingSlot.value = null
  dragOverSlot.value = null
}

function onSlotDragOver(slotIndex: number) {
  dragOverSlot.value = slotIndex
}

function onSlotDragLeave(slotIndex: number) {
  if (dragOverSlot.value === slotIndex) dragOverSlot.value = null
}

function onSlotDrop(slotIndex: number) {
  if (!isEditMode.value) return
  if (draggingSlot.value === null) return
  const from = draggingSlot.value
  if (from === slotIndex) {
    onCardDragEnd()
    return
  }
  const tmp = boardLayout.value[slotIndex]
  boardLayout.value[slotIndex] = boardLayout.value[from]
  boardLayout.value[from] = tmp
  onCardDragEnd()
  void saveDashboardLayout()
}

async function loadDashboardLayout() {
  if (!token.value) return
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (Array.isArray(res?.slots)) {
      const normalized = res.slots.slice(0, 9)
      while (normalized.length < 9) normalized.push(null)
      boardLayout.value = normalized as Array<CardKey | null>
    }
    const ws = res?.settings?.welcome || {}
    welcomeSettings.value = {
      title: typeof ws?.title === 'string' && ws.title.trim() ? ws.title.trim() : 'Bienvenido',
      subtitle: typeof ws?.subtitle === 'string' && ws.subtitle.trim() ? ws.subtitle.trim() : 'Aqui tenes un resumen de la actividad del sistema.',
      show_date: ws?.show_date !== false,
    }
  } catch (e) {
    console.error('dashboard layout load failed', e)
  }
}

async function saveDashboardLayout() {
  if (!token.value) return
  if (savingLayout.value) return
  savingLayout.value = true
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { slots: boardLayout.value },
    })
    showToast('success', 'Dashboard guardado correctamente')
  } catch (e) {
    console.error('dashboard layout save failed', e)
    showToast('error', 'No se pudo guardar el dashboard')
  } finally {
    savingLayout.value = false
  }
}

onBeforeMount(async () => {
  console.log('dashboard onBeforeMount, token=', token.value)
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

  await loadDashboardLayout()

  try {
    data.value = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }

  birthdaysLoading.value = true
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/birthdays`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
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
    const vac = await $fetch(`${apiBase || 'http://localhost:8000'}/api/vacations/me`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    vacationInfo.value = {
      daysUntilVacation: vac?.days_until_vacation != null ? Math.round(Number(vac.days_until_vacation)) : null,
      vacationDays: vac?.vacation_days != null ? Math.round(Number(vac.vacation_days)) : null,
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
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/team/me`, {
      headers: { Authorization: `Bearer ${token.value}` },
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
  try {
    const fresh = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    data.value.stats = fresh?.stats || data.value.stats
  } catch (e) {
    console.error('dashboard stats refresh failed', e)
  }
}

async function refreshHolidays() {
  holidaysLoading.value = true
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/holidays/upcoming`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
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
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/announcements?limit=5`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
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
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/documents/summary`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    documentsSummary.value = {
      medical: Number(res?.medical || 0),
      receipt: Number(res?.receipt || 0),
      payroll: Number(res?.payroll || 0),
      total: Number(res?.total || 0),
    }
  } catch (e) {
    console.error('documents summary fetch failed', e)
  }
}

async function refreshDashboardNotifications() {
  if (!token.value) return
  try {
    const fresh = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
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
  if (toastTimer) clearTimeout(toastTimer)
})

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}
</script>
