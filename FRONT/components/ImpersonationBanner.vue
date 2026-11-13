<template>
  <div
    v-if="impersonator"
    class="fixed bottom-16 left-1/2 -translate-x-1/2 z-[100] max-w-[calc(100%-2rem)] flex items-center gap-3 rounded-full border border-violet-400/40 bg-violet-950/95 px-4 py-2 shadow-2xl shadow-black/60 backdrop-blur"
    role="status"
  >
    <span class="w-2 h-2 rounded-full bg-violet-400 animate-pulse shrink-0" aria-hidden="true"></span>
    <span class="text-xs text-violet-100 truncate">
      Modo soporte: estás viendo <strong>{{ companyName }}</strong> como {{ (user as any)?.email }}
    </span>
    <button
      class="shrink-0 text-xs font-medium px-3 py-1 rounded-full bg-violet-500 hover:bg-violet-400 text-white disabled:opacity-50"
      :disabled="leaving"
      @click="leave"
    >
      {{ leaving ? 'Saliendo...' : 'Salir' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const { user, fetchUser } = useAuth()
const leaving = ref(false)

const impersonator = computed(() => (user.value as any)?.impersonated_by || null)
const companyName = computed(() => (user.value as any)?.company?.name || 'la empresa')

async function leave() {
  leaving.value = true
  try {
    const res: any = await $fetch('/api/auth/stop-impersonation', { method: 'POST' })
    await fetchUser()
    await navigateTo(res?.company_id ? `/admin/empresas/${res.company_id}` : '/admin')
  } finally {
    leaving.value = false
  }
}
</script>
