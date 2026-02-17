<template>
  <div class="container">
    <h1>Dashboard</h1>
    <div v-if="loading">Cargando...</div>
    <div v-else>
      <p>{{ data.message }}</p>
      <pre>{{ data.stats }}</pre>
    </div>
    <button @click="onLogout">Logout</button>
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

<style scoped>
.container{max-width:960px;margin:48px auto;padding:20px}
</style>
