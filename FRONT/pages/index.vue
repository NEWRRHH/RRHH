<template>
  <div class="min-h-screen flex items-center justify-center text-sm text-gray-500">
    Redirigiendo...
  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: false })

// Public entry point: resolve the session from the httpOnly cookie and send
// the visitor to the right place.
const { fetchUser } = useAuth()

onMounted(async () => {
  const u = await fetchUser()
  await navigateTo(u ? '/dashboard' : '/login', { replace: true })
})
</script>
