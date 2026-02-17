<template>
  <div>
    <nav style="padding:12px;border-bottom:1px solid #eee;display:flex;gap:12px;align-items:center;">
      <NuxtLink to="/">Home</NuxtLink>
      <NuxtLink to="/dashboard">Dashboard</NuxtLink>
      <div style="margin-left:auto">
        <template v-if="user">
          <span style="margin-right:12px">{{ user.name }}</span>
          <button @click="logout">Logout</button>
        </template>
        <template v-else>
          <NuxtLink to="/login">Login</NuxtLink>
          <NuxtLink to="/register" style="margin-left:8px">Register</NuxtLink>
        </template>
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
