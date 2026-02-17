<template>
  <div class="max-w-4xl mx-auto mt-12 p-6 bg-white rounded-lg shadow">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Dashboard</h1>
      <button @click="onLogout" class="px-3 py-1 bg-red-500 text-white rounded">Logout</button>
    </div>

    <div v-if="loading" class="text-gray-500">Cargando...</div>
    <div v-else>
      <p class="mb-4 text-gray-700">{{ data.message }}</p>
      <pre class="bg-gray-50 p-4 rounded text-sm text-gray-700">{{ data.stats }}</pre>
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
