<template>
  <div class="min-h-screen bg-gray-950"></div>
</template>

<script setup lang="ts">
// Festivos now live in Configuración > Festivos (RRHH / admin, "holidays.manage").
// Kept as a redirect so old links and bookmarks still work.
definePageMeta({ auth: true })
await navigateTo({ path: '/configuracion', query: { tab: 'holidays' } }, { replace: true })
</script>
