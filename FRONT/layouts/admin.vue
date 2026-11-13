<template>
  <div class="flex h-screen overflow-hidden bg-gray-950 text-gray-200">
    <!-- mobile backdrop -->
    <div v-if="open" class="fixed inset-0 z-40 bg-black/60 lg:hidden" @click="open = false"></div>

    <aside
      class="fixed lg:static z-50 inset-y-0 left-0 w-64 shrink-0 flex flex-col border-r border-gray-800 bg-gray-900 transition-transform duration-200"
      :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
      <div class="h-16 flex items-center gap-3 px-5 border-b border-gray-800">
        <div class="w-9 h-9 rounded-xl bg-violet-600 flex items-center justify-center shadow-lg shadow-violet-600/30">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z" />
          </svg>
        </div>
        <div>
          <div class="text-sm font-semibold text-white leading-tight">RRHH Plataforma</div>
          <div class="text-[11px] text-violet-300">Superadmin</div>
        </div>
      </div>

      <div role="navigation" aria-label="Panel de plataforma" class="flex-1 overflow-y-auto p-3 space-y-1">
        <NuxtLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition no-underline hover:no-underline"
          :class="isActive(item) ? 'bg-violet-600/20 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
          @click="open = false"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span class="flex-1">{{ item.label }}</span>
          <span v-if="item.to === '/admin/errores' && openErrors > 0" class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-500/20 text-red-300">{{ openErrors }}</span>
        </NuxtLink>
      </div>

      <div class="p-3 border-t border-gray-800">
        <div class="px-3 py-2 text-xs text-gray-400 truncate">{{ (user as any)?.email }}</div>
        <button class="w-full text-left px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800" @click="onLogout">Cerrar sesión</button>
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-16 shrink-0 flex items-center gap-3 px-4 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button class="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-800" aria-label="Abrir menú" @click="open = true">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <h1 class="text-base font-semibold text-white">{{ title }}</h1>
      </header>
      <main class="flex-1 min-h-0 overflow-auto p-4 sm:p-6">
        <div class="max-w-7xl mx-auto">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

const route = useRoute()
const router = useRouter()
const { user, logout } = useAuth()
const open = ref(false)
const openErrors = ref(0)

const nav = [
  { to: '/admin', label: 'Dashboard', exact: true, icon: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z' },
  { to: '/admin/empresas', label: 'Empresas', icon: 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m-1 4h1m-1 4h1m4-8h1m-1 4h1m-1 4h1' },
  { to: '/admin/usuarios', label: 'Usuarios', icon: 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m8-4.13a4 4 0 11-8 0 4 4 0 018 0z' },
  { to: '/admin/errores', label: 'Errores', icon: 'M12 9v3.75m0 3.75h.008M10.3 3.86L1.82 18a2 2 0 001.72 3h16.92a2 2 0 001.72-3L13.7 3.86a2 2 0 00-3.4 0z' },
  { to: '/admin/auditoria', label: 'Auditoría', icon: 'M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z' },
  { to: '/admin/configuracion', label: 'Configuración', icon: 'M10.3 4.3a1.7 1.7 0 013.4 0 1.7 1.7 0 002.6 1.1 1.7 1.7 0 012.4 2.4 1.7 1.7 0 001.1 2.6 1.7 1.7 0 010 3.4 1.7 1.7 0 00-1.1 2.6 1.7 1.7 0 01-2.4 2.4 1.7 1.7 0 00-2.6 1.1 1.7 1.7 0 01-3.4 0 1.7 1.7 0 00-2.6-1.1 1.7 1.7 0 01-2.4-2.4 1.7 1.7 0 00-1.1-2.6 1.7 1.7 0 010-3.4 1.7 1.7 0 001.1-2.6 1.7 1.7 0 012.4-2.4 1.7 1.7 0 002.6-1.1zM15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

const isActive = (item: { to: string; exact?: boolean }) =>
  item.exact ? route.path === item.to : route.path === item.to || route.path.startsWith(item.to + '/')

const title = computed(() => String(route.meta?.title || 'Panel de plataforma'))

async function loadOpenErrors() {
  try {
    const res: any = await $fetch('/api/admin/errors', { query: { per_page: 5 } })
    openErrors.value = Number(res?.total || 0)
  } catch {
    openErrors.value = 0
  }
}

async function onLogout() {
  await logout()
  router.push('/login')
}

onMounted(loadOpenErrors)
watch(() => route.path, loadOpenErrors)
</script>
