<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
  <!-- visible only while the super admin is inside a company -->
  <ImpersonationBanner />
  <!-- RRHH / admin looking as an employee (read-only) -->
  <ViewAsBanner />
  <!-- confetti on the logged-in user's birthday / work anniversary -->
  <CelebrationOverlay />
</template>

<script setup lang="ts">
// Today's presence (remote / office / vacation) for the icon on every avatar:
// start once a company user is logged in (super admin has no company).
const { user } = useAuth()
const { start } = usePresence()
watch(
  () => (user.value as any)?.company_id,
  (companyId) => { if (companyId) start() },
  { immediate: true },
)
</script>
