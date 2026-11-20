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
const { user, apiBase } = useAuth()
const { start } = usePresence()

// Browser tab icon = the company logo (browser default when it has none)
const companyLogo = computed(() => {
  const logo = String((user.value as any)?.company?.logo || '')
  if (!logo) return ''
  return logo.startsWith('/') ? `${apiBase}${logo}` : logo
})
useHead(() => ({
  link: companyLogo.value ? [{ key: 'favicon', rel: 'icon', href: companyLogo.value }] : [],
}))
watch(
  () => (user.value as any)?.company_id,
  (companyId) => { if (companyId) start() },
  { immediate: true },
)
</script>
