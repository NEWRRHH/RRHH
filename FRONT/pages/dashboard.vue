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
            <p class="text-blue-200 text-sm font-medium mb-1">Panel de control</p>
            <h2 class="text-3xl font-bold text-white">
              Bienvenido{{ user?.name ? ', ' + user.name : '' }} 👋
            </h2>
            <p class="mt-2 text-blue-200 text-sm">Aquí tenés un resumen de la actividad del sistema.</p>
          </div>
        </div>

        <!-- Content card -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl shadow-black/60 p-6">
          <div v-if="loading" class="flex items-center gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Cargando...
          </div>
          <div v-else class="space-y-4">
            <p class="text-gray-300">{{ data.message }}</p>
            <pre class="bg-gray-800 border border-gray-700 p-4 rounded-xl text-sm text-gray-300 overflow-auto">{{ data.stats }}</pre>
          </div>
        </div>

      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
const { token, user, fetchUser, logout } = useAuth()
const router = useRouter()
const data = ref<any>({})
const loading = ref(true)
const sidebar = ref<{ open: boolean } | null>(null)

onBeforeMount(async () => {
  if (!token.value) return router.push('/login')
  await fetchUser()
  try {
    data.value = await $fetch(`${useRuntimeConfig().public.apiBase || 'http://localhost:8000'}/api/dashboard`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}
</script>
