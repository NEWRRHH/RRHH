<template>
  <div class="flex min-h-screen bg-gray-950">

    <!-- Glow accent -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
      <div class="absolute top-0 right-0 w-96 h-96 bg-blue-600 opacity-10 rounded-full blur-3xl"></div>
    </div>

    <!-- Sidebar -->
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 relative z-10">

      <!-- Top bar -->
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <!-- Mobile sidebar toggle -->
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
          <!-- attendance control button placed before user info -->
          <AttendanceButton class="!text-[11px]" @changed="handleAttendanceChanged" />
          <!-- avatar/menu component -->
          <UserMenu />
        </div>
      </header>

      <!-- Page body -->
      <main class="relative z-10 flex-1 p-6 space-y-6 overflow-auto">

        <!-- Welcome banner -->
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 sm:p-8 shadow-xl shadow-blue-900/40">
          <div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
          <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
          <div class="relative">
            <p class="text-blue-200 text-xs sm:text-sm font-medium mb-1">Panel de control</p>
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white leading-tight">
              Bienvenido{{ user?.name ? ', ' + user.name : '' }} 👋
            </h2>
            <p class="mt-2 text-blue-200 text-xs sm:text-sm">Aquí tenés un resumen de la actividad del sistema.</p>
          </div>
        </div>

        <!-- KPI stats -->
        <div class="flex flex-wrap gap-3">
          <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
            <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.users_count ?? 0 }}</div>
            <div class="text-xs text-gray-300">Usuarios</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
            <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.attendances_today ?? 0 }}</div>
            <div class="text-xs text-gray-300">Asistencias hoy</div>
          </div>
        </div>


        <!-- Cards row: Birthdays + Workers + Vacations + Notifications -->
        <div class="flex flex-wrap gap-3">
          <BirthdayCard :birthdays="birthdays" :loading="birthdaysLoading" />
          <WorkingUsersCard :users="workingUsers" :loading="workingLoading" />
          <VacationCard
            :days-until-vacation="vacationInfo.daysUntilVacation"
            :vacation-days="vacationInfo.vacationDays"
            :loading="vacationLoading"
          />
          <NotificationCard
            :notifications="data.notifications || []"
            :unread-count="unreadNotifications || 0"
            @notification-read="markRead"
          />
        </div>

      </main>
    </div>
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
import WorkingUsersCard from '../components/WorkingUsersCard.vue'
import VacationCard from '../components/VacationCard.vue'
import NotificationCard from '../components/NotificationCard.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

declare const $fetch: any

const { token, user, fetchUser, logout, apiBase, setToken, fetchUnread, unreadNotifications, lastReceivedMessage } = useAuth()

async function markRead(id: number) {
  if (data.value.notifications) {
    // remove all messages belonging to this conversation
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
const workingUsers = ref<any[]>([])
const workingLoading = ref(true)

// vacation
const vacationLoading = ref(true)
const vacationInfo = ref<{ daysUntilVacation: number | null; vacationDays: number | null }>({
  daysUntilVacation: null,
  vacationDays: null,
})

onBeforeMount(async () => {
  console.log('dashboard onBeforeMount, token=', token.value)
  // try to restore token from localStorage on client before redirecting
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
      // server: do nothing (middleware skips SSR check)
      return
    }
  } else {
    await fetchUser()
  }

  try {
    data.value = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }

  // fetch upcoming birthdays (authenticated)
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

  await refreshWorkingUsers()

  // fetch vacation info for logged-in user
  vacationLoading.value = true
  try {
    const vac = await $fetch(`${apiBase || 'http://localhost:8000'}/api/vacations/me`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    vacationInfo.value = {
      daysUntilVacation: vac?.days_until_vacation != null ? Math.round(Number(vac.days_until_vacation)) : null,
      vacationDays: vac?.vacation_days != null ? Math.round(Number(vac.vacation_days)) : null,
    }
  } catch (e) {
    console.error('vacation fetch failed', e)
  } finally {
    vacationLoading.value = false
  }
})

async function refreshWorkingUsers() {
  workingLoading.value = true
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/working`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    workingUsers.value = res || []
  } catch (e) {
    console.error('working fetch failed', e)
    workingUsers.value = []
  } finally {
    workingLoading.value = false
  }
}

async function handleAttendanceChanged() {
  await refreshWorkingUsers()
  // Keep KPI in sync (attendances_today/open attendances) without full reload.
  try {
    const fresh = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    data.value.stats = fresh?.stats || data.value.stats
  } catch (e) {
    console.error('dashboard stats refresh failed', e)
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
})

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}

const formatDate = (iso?: string) => {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' })
  } catch (e) {
    return iso
  }
}
</script>
