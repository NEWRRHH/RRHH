<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
  <!-- visible only while the super admin is inside a company -->
  <ImpersonationBanner />
  <!-- confetti on the logged-in user's birthday / work anniversary -->
  <CelebrationOverlay />
</template>
