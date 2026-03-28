<template>
  <!-- Overlay (mobile) -->
  <div
    v-if="open && isMobile"
    class="fixed inset-0 z-30 bg-black/60 lg:hidden"
    @click="open = false"
  />

  <!-- Sidebar -->
  <aside
    :class="[
      'fixed lg:relative inset-y-0 left-0 z-40 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 ease-in-out',
      collapsed ? 'w-16' : 'w-64',
      open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]"
  >
    <!-- Header -->
    <div class="h-16 shrink-0 flex items-center gap-2 px-4 border-b border-gray-800 overflow-hidden">
      <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <span v-if="!collapsed" class="text-white font-bold text-base tracking-tight truncate">RRHH</span>
    </div>

    <!-- Collapse toggle (local) -->
    <DashboardSidebarCollapse :collapsed="collapsed" @toggle="collapsed = !collapsed" />

    <!-- Body / Nav -->
    <nav class="flex flex-col flex-1 overflow-y-auto overflow-x-visible py-4 px-2 gap-1">
      <template v-for="item in navItems" :key="item.label">
        <NuxtLink
          :to="item.to"
          :class="[
            'relative flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium transition group overflow-visible',
            collapsed ? 'justify-center' : '',
            item.active
              ? 'bg-blue-600/15 text-blue-400'
              : 'text-gray-400 hover:text-white hover:bg-gray-800'
          ]"
        >
          <span class="shrink-0 inline-flex items-center" v-html="item.icon" />
          <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
          <span
            v-if="item.badge && item.badge.value > 0"
            :class="[
              'inline-flex items-center justify-center rounded-full text-xs font-semibold bg-red-600 text-white',
              collapsed
                ? 'absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1'
                : 'ml-auto px-2 py-0.5'
            ]"
          >
            {{ item.badge.value }}
          </span>
        </NuxtLink>
      </template>
    </nav>

    <div v-if="isMobile" class="shrink-0 px-2 py-3 border-t border-gray-800 overflow-hidden space-y-2">
      <button
        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition"
        :class="collapsed ? 'justify-center' : ''"
        @click="goToProfile"
      >
        <div class="w-7 h-7 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-[11px] font-semibold shrink-0">
          <img v-if="(user as any)?.photo || (user as any)?.profile_photo_path" :src="(user as any)?.photo || (user as any)?.profile_photo_path" class="w-full h-full object-cover" />
          <span v-else>{{ userInitial }}</span>
        </div>
        <span v-if="!collapsed" class="truncate">Mi perfil</span>
      </button>

      <button
        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-red-300 hover:bg-red-500/10 transition"
        :class="collapsed ? 'justify-center' : ''"
        @click="emit('logout')"
      >
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/>
        </svg>
        <span v-if="!collapsed">Cerrar sesion</span>
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DashboardSidebarCollapse from './DashboardSidebarCollapse.vue'
import { useAuth } from '../composables/useAuth'
declare const $fetch: any

const emit = defineEmits<{ logout: [] }>()

// empezar siempre colapsado al iniciar sesión
const collapsed = ref(true)
const open = ref(false)
const isMobile = ref(false)

// expose open so parent can toggle on mobile
defineExpose({ open })

onMounted(async () => {
  isMobile.value = window.innerWidth < 1024
  window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth < 1024
  })

  // obtener contador de notificaciones no leídas
  try {
    await fetchUser()
    await fetchUnread()
    await fetchRequestsBadge()
    setupRequestsRealtime()
    requestsTimer = setInterval(() => {
      void fetchRequestsBadge()
    }, 15000)
  } catch (e) {
    console.error('failed to load unread count', e)
  }
})

const route = useRoute()
const router = useRouter()
const { fetchUser, unreadNotifications, fetchUnread, user, token, apiBase, realtimeInstance } = useAuth()

const unreadCount = computed(() => unreadNotifications.value || 0)
const userInitial = computed(() => String((user.value as any)?.name || '?').charAt(0).toUpperCase())
const requestsCount = ref(0)
const isHrTeam = computed(() => Boolean((user.value as any)?.is_hr_team))
const isAdmin = computed(() => Boolean((user.value as any)?.is_admin) || Number((user.value as any)?.user_type_id || 0) === 1)
const canAccessSettings = computed(() => Boolean((user.value as any)?.can_access_settings) || isAdmin.value || isHrTeam.value)
const canSeeEmployees = computed(() => isHrTeam.value || isAdmin.value)
const canSeeAnnouncements = computed(() => isHrTeam.value || isAdmin.value)
let requestsTimer: ReturnType<typeof setInterval> | null = null
let reviewerRealtimeChannel: any = null
let userRealtimeChannel: any = null

async function fetchRequestsBadge() {
  if (!token.value) return
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/timeoff/requests?status=pending`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    requestsCount.value = Array.isArray(res?.requests) ? res.requests.length : 0
  } catch (_e) {
    requestsCount.value = 0
  }
}

function teardownRequestsRealtime() {
  try {
    reviewerRealtimeChannel?.stopListening?.('.TimeOffRequestUpdated')
    userRealtimeChannel?.stopListening?.('.TimeOffRequestUpdated')
  } catch (_e) {
    // ignore
  } finally {
    reviewerRealtimeChannel = null
    userRealtimeChannel = null
  }
}

function setupRequestsRealtime() {
  teardownRequestsRealtime()
  const echo: any = realtimeInstance?.()
  const uid = Number((user.value as any)?.id || 0)
  if (!echo || !uid) return

  userRealtimeChannel = echo.private(`user.${uid}`)
  userRealtimeChannel.listen('.TimeOffRequestUpdated', () => {
    void fetchRequestsBadge()
  })

  const canReview = Array.isArray((user.value as any)?.permissions) && (user.value as any).permissions.includes('requests.review')
  if (canReview) {
    reviewerRealtimeChannel = echo.private('timeoff.reviewers')
    reviewerRealtimeChannel.listen('.TimeOffRequestUpdated', () => {
      void fetchRequestsBadge()
    })
  }
}

function goToProfile() {
  router.push('/profile')
  if (isMobile.value) open.value = false
}

onBeforeUnmount(() => {
  teardownRequestsRealtime()
  if (requestsTimer) {
    clearInterval(requestsTimer)
    requestsTimer = null
  }
})
const navItems = computed(() => [
  {
    label: 'Dashboard',
    to: '/dashboard',
    active: route.path.startsWith('/dashboard'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
    </svg>`
  },
  {
    label: 'Notificaciones',
    to: '/notificaciones',
    active: route.path.startsWith('/notificaciones'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>`,
    badge: unreadCount
  },
  ...(canSeeAnnouncements.value ? [{
    label: 'Comunicados',
    to: '/comunicados',
    active: route.path.startsWith('/comunicados'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h7m-7 4h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H9l-6 0V5a2 2 0 012-2z"/>
    </svg>`
  }] : []),
  {
    label: 'Vacaciones',
    to: '/vacaciones',
    active: route.path.startsWith('/vacaciones'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z"/>
    </svg>`
  },
  {
    label: 'Solicitudes',
    to: '/solicitudes',
    active: route.path.startsWith('/solicitudes'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/>
    </svg>`,
    badge: requestsCount
  },
  {
    label: 'Documentos',
    to: '/documentos',
    active: route.path.startsWith('/documentos'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M7 7V3h10v4m-9 4h8m-8 4h8m-9 6h10a2 2 0 002-2V7H5v12a2 2 0 002 2z"/>
    </svg>`
  },
  ...(canSeeEmployees.value ? [{
    label: 'Empleados',
    to: '/empleados',
    active: route.path.startsWith('/empleados'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>`
  }] : []),
  {
    label: 'Reportes',
    to: '/reportes',
    active: route.path.startsWith('/reportes'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>`
  },
  ...(canAccessSettings.value ? [{
    label: 'Configuracion',
    to: '/configuracion',
    active: route.path.startsWith('/configuracion'),
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>`
  }] : [])
])
</script>


