<template>
  <div class="min-h-screen flex items-center justify-center text-sm text-gray-500">
    Redirigiendo...
  </div>
</template>

<script setup lang="ts">
const { token, setToken } = useAuth()

onMounted(async () => {
  if (!token.value && process.client) {
    const saved = localStorage.getItem('rrhh_token')
    if (saved) setToken(saved)
  }

  if (!token.value) {
    await navigateTo('/login', { replace: true })
    return
  }

  await navigateTo('/dashboard', { replace: true })
})
</script>
