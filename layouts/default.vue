<template>
  <div>
    <nav class="bg-white border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center space-x-4">
            <NuxtLink to="/" class="text-lg font-semibold">Home</NuxtLink>
            <NuxtLink to="/dashboard" class="text-gray-600 hover:text-gray-900">Dashboard</NuxtLink>
          </div>
          <div>
            <template v-if="user">
              <span class="mr-4 text-sm text-gray-700">{{ user.name }}</span>
              <button @click="logout" class="px-3 py-1 bg-red-500 text-white rounded">Logout</button>
            </template>
            <template v-else>
              <NuxtLink to="/login" class="text-teal-500 hover:underline">Login</NuxtLink>
              <NuxtLink to="/register" class="ml-4 text-teal-500 hover:underline">Register</NuxtLink>
            </template>
          </div>
        </div>
      </div>
    </nav>
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const { user, logout, fetchUser } = useAuth()
onBeforeMount(async () => {
  await fetchUser()
})
</script>
