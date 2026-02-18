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
      <header class="h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
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
          <span class="text-sm text-gray-400">{{ user?.name }}</span>
          <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold">
            {{ user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
          </div>
        </div>
      </header>

      <!-- Page body -->
      <main class="flex-1 p-6 space-y-6 overflow-auto">

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

        <!-- KPI + quick search -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
          <div class="flex gap-3 items-center">
            <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
              <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.users_count ?? 0 }}</div>
              <div class="text-xs text-gray-300">Usuarios</div>
            </div>
            <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
              <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.posts_count ?? 0 }}</div>
              <div class="text-xs text-gray-300">Publicaciones</div>
            </div>
            <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm hidden md:flex">
              <div class="text-2xl text-blue-300 font-semibold">{{ data.stats?.attendances_today ?? 0 }}</div>
              <div class="text-xs text-gray-300">Asistencias hoy</div>
            </div>
          </div>

          <div class="md:col-span-2">
            <div class="flex items-center gap-3">
              <div class="relative w-full max-w-lg">
                <input v-model="searchTerm" @input="onSearch" placeholder="Buscar personas, tareas o publicaciones..." class="w-full bg-gray-800 border border-gray-700 text-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" />
                <div v-if="searchTerm && searchResults.length" class="absolute left-0 right-0 mt-2 bg-gray-900 border border-gray-800 rounded-lg shadow-lg overflow-hidden z-20">
                  <div v-for="r in searchResults.slice(0,6)" :key="r._uid" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 cursor-pointer">
                    <div class="w-8 h-8 rounded-full bg-gray-800 overflow-hidden flex items-center justify-center text-gray-400">
                      <img v-if="r.photo" :src="r.photo" class="w-full h-full object-cover" />
                      <span v-else class="text-sm">{{ (r.first_name || r.full_name || '?').charAt(0) }}</span>
                    </div>
                    <div class="flex-1 text-sm text-gray-200 truncate">{{ r.full_name || r.first_name }}</div>
                    <div class="text-xs text-gray-400">{{ r.source }}</div>
                  </div>
                </div>
              </div>
              <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm">Buscar</button>
            </div>
          </div>
        </div>

        <!-- Content grid: Birthdays (left) + Stats (right) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

          <!-- Birthdays card -->
          <div class="lg:col-span-1 flex justify-center lg:justify-end">
            <BirthdayCard :birthdays="birthdays" :loading="birthdaysLoading" />
          </div>

          <!-- Workers card (moved out) -->
          <div class="lg:col-span-1 flex justify-center lg:justify-start">
            <WorkingUsersCard :users="workingUsers" :loading="workingLoading" />
          </div>

          <!-- Right column: general stats (kept, but no welcome header) -->
          <div class="lg:col-span-1">

          </div>

        </div>

      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import BirthdayCard from '../components/BirthdayCard.vue'
import WorkingUsersCard from '../components/WorkingUsersCard.vue'

declare const $fetch: any

const { token, user, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const data = ref<any>({})
const loading = ref(true)
const sidebar = ref<{ open: boolean } | null>(null)

const birthdays = ref<any[]>([])
const birthdaysLoading = ref(true)
const workingUsers = ref<any[]>([])
const workingLoading = ref(true)

// quick search
const searchTerm = ref('')
const searchResults = computed(() => {
  const q = (searchTerm.value || '').toLowerCase().trim()
  if (!q) return []
  const items = [
    ...birthdays.value.map((b: any) => ({ _uid: `b-${b.id}`, first_name: b.first_name, full_name: b.full_name, photo: b.photo, source: 'Cumpleaños' })),
    ...workingUsers.value.map((w: any) => ({ _uid: `w-${w.id}`, first_name: w.first_name, full_name: w.full_name, photo: w.photo, source: 'En trabajo' })),
  ]
  return items.filter((it: any) => (it.full_name || it.first_name || '').toLowerCase().includes(q))
})

const onSearch = () => {
  // keep local-only quick search; button can trigger full search later
  return
}

onBeforeMount(async () => {
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

  // fetch workers currently "en_trabajo"
  workingLoading.value = true
  try {
    const res2 = await $fetch(`${apiBase || 'http://localhost:8000'}/api/working`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    workingUsers.value = res2 || []
  } catch (e) {
    console.error('working fetch failed', e)
    workingUsers.value = []
  } finally {
    workingLoading.value = false
  }
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
