<template>
  <!-- "Ver como" (read-only): always visible while RRHH / admin look as an employee -->
  <div
    v-if="viewer"
    class="fixed top-0 inset-x-0 z-[100] flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-amber-400/40 bg-amber-950/95 px-3 py-1.5 text-xs text-amber-100 shadow-lg backdrop-blur"
    role="status"
  >
    <svg class="w-4 h-4 shrink-0 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
    <span class="min-w-0 truncate">
      Viendo como <strong>{{ viewedName }}</strong> · solo lectura · el chat no se muestra
      <template v-if="remaining"> · {{ remaining }}</template>
    </span>
    <button
      class="shrink-0 font-medium px-3 py-0.5 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-950 disabled:opacity-50"
      :disabled="leaving"
      @click="leave"
    >
      {{ leaving ? 'Saliendo...' : 'Salir' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const { user, fetchUser } = useAuth()
const leaving = ref(false)
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

const viewer = computed(() => (user.value as any)?.viewed_by || null)
const viewedName = computed(() => {
  const u: any = user.value || {}
  return `${u.first_name || u.name || ''} ${u.last_name || ''}`.trim() || u.email || 'empleado'
})

// time left of the 30 min read-only session
const remaining = computed(() => {
  const expires = viewer.value?.expires_at ? new Date(viewer.value.expires_at).getTime() : 0
  if (!expires) return ''
  const secs = Math.max(0, Math.round((expires - now.value) / 1000))
  return `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`
})

async function leave() {
  leaving.value = true
  try {
    const res: any = await $fetch('/api/auth/stop-view-as', { method: 'POST' })
    await fetchUser()
    await navigateTo(res?.user_id ? `/empleados/${res.user_id}` : '/empleados')
  } finally {
    leaving.value = false
  }
}

onMounted(() => {
  timer = setInterval(async () => {
    now.value = Date.now()
    // expired: the server already falls back to the viewer's own session
    if (viewer.value && remaining.value === '0:00') {
      await fetchUser()
      await navigateTo('/empleados')
    }
  }, 1000)
})
onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>
