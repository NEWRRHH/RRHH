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
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-8 shadow-xl shadow-blue-900/40">
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

        <!-- Content grid: Birthdays (left) + Stats (right) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

          <!-- Birthdays card -->
          <div class="lg:col-span-1 flex justify-center lg:justify-end">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 w-[230px] max-w-full flex flex-col">
              <h3 class="text-sm text-gray-400 mb-3">Cumpleaños</h3>

              <div v-if="birthdaysLoading" class="space-y-4">
                <div class="h-36 rounded-2xl bg-gray-800 animate-pulse"></div>
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
                  <div class="flex-1 h-4 bg-gray-800 animate-pulse rounded"></div>
                </div>
              </div>

              <div v-else>
                <div class="grid grid-cols-1 gap-4 md:grid-cols">
                  <div>
                    <div v-if="birthdays.length">
                      <div class="flex flex-col items-center text-center">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-white text-lg font-bold overflow-hidden">
                          <img v-if="birthdays[0].photo" :src="birthdays[0].photo" class="w-full h-full object-cover" />
                          <span v-else>{{ (birthdays[0].first_name || birthdays[0].full_name || '?').charAt(0) }}</span>
                        </div>
                        <div class="mt-2">
                          <div class="text-white font-semibold">{{ birthdays[0].full_name || birthdays[0].first_name }}</div>
                          <div class="text-sm text-gray-400 mt-1">{{ formatDate(birthdays[0].next_birthday) }} — {{ birthdays[0].age }} años</div>
                        </div>
                      </div>

                      <div class="mt-4 grid grid-cols-2 gap-3 justify-items-center">
                        <div v-for="(b, i) in birthdays.slice(1,3)" :key="b.id" class="flex flex-col items-center text-center gap-2">
                          <div class="w-12 h-12 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 overflow-hidden">
                            <img v-if="b.photo" :src="b.photo" class="w-full h-full object-cover" />
                            <span v-else class="text-sm">{{ (b.first_name || b.full_name || '?').charAt(0) }}</span>
                          </div>
                          <div class="text-sm text-gray-200">{{ b.full_name || b.first_name }}</div>
                          <div class="text-xs text-gray-400">{{ formatDate(b.next_birthday) }}</div>
                        </div>
                      </div>
                    </div>

                    <div v-else class="p-6 rounded-xl bg-gray-800/50 text-center text-gray-400">
                      <div class="animate-pulse h-6 w-3/4 mx-auto bg-gray-700 rounded mb-3"></div>
                      <div class="text-sm">No upcoming birthdays</div>
                    </div>
                  </div>


                </div>
              </div>
            </div>
          </div>

          <!-- Workers card (moved out) -->
          <div class="lg:col-span-1 flex justify-center lg:justify-start">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-3 h-full w-[180px] max-w-full flex flex-col">
              <h3 class="text-sm text-gray-400 mb-3">En trabajo</h3>
              <div class="flex-1">
                <div v-if="workingLoading" class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
                  <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
                  <div class="h-10 w-10 rounded-full bg-gray-800 animate-pulse"></div>
                </div>
                <div v-else>
                  <div v-if="workingUsers.length" class="flex items-center gap-3 overflow-x-auto py-1">
                    <div
                      v-for="w in workingUsers"
                      :key="w.id"
                      class="flex items-center gap-2 min-w-[130px] flex-shrink-0"
                      :title="w.full_name || w.first_name"
                    >
                      <div class="relative">
                        <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-800 flex items-center justify-center text-gray-400">
                          <img v-if="w.photo" :src="w.photo" class="w-full h-full object-cover" />
                          <span v-else class="text-sm">{{ (w.first_name || w.full_name || '?').charAt(0) }}</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full ring-2 ring-gray-900"></span>
                      </div>
                      <span class="text-xs text-gray-200 truncate">{{ w.full_name || w.first_name }}</span>
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-400">No hay trabajadores en trabajo</div>
                </div>
              </div>
            </div>
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
import { ref, onBeforeMount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

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
