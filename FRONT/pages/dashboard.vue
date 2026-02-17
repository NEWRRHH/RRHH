<template>
  <div class="min-h-screen bg-gray-950 py-10 px-4">

    <!-- Glow accent -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
      <div class="absolute top-0 right-0 w-96 h-96 bg-blue-600 opacity-10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-4xl mx-auto">

      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-600 shadow-lg shadow-blue-600/40 flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-xs text-gray-500">Panel de control</p>
          </div>
        </div>
        <button
          @click="onLogout"
          class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 hover:text-white text-sm font-medium rounded-xl transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Cerrar sesión
        </button>
      </div>

      <!-- Content -->
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

    </div>
  </div>
</template>

<script setup lang="ts">
const { token, fetchUser, logout } = useAuth()
const router = useRouter()
const data = ref<any>({})
const loading = ref(true)

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
</script>
