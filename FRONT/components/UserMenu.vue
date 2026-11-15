<template>
  <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
  <!-- Notifications bell: live unread count (updated over WebSocket in useAuth);
       opens the 5 latest conversations, "Ver todas" goes to /notificaciones -->
  <div class="relative bell-container">
  <button
    type="button"
    class="relative w-10 h-10 rounded-full flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-800 transition"
    :class="{ 'bg-gray-800 text-white': bellOpen }"
    :aria-label="unreadCount > 0 ? `Notificaciones: ${unreadCount} sin leer` : 'Notificaciones'"
    :title="unreadCount > 0 ? `${unreadCount} sin leer` : 'Notificaciones'"
    aria-haspopup="true"
    :aria-expanded="bellOpen"
    @click.stop="toggleBell"
  >
    <svg class="w-5 h-5" :class="{ 'bell-ring': ringing }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4a2 2 0 01-.6-1.4V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>
    <span
      v-if="unreadCount > 0"
      class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-semibold leading-[18px] text-center ring-2 ring-gray-900"
      :class="{ 'badge-pop': ringing }"
    >
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </span>
  </button>

  <div
    v-if="bellOpen"
    class="fixed sm:absolute left-2 right-2 sm:left-auto sm:right-0 top-16 sm:top-auto sm:mt-2 sm:w-80 z-[70] rounded-xl border border-gray-700 bg-gray-900 shadow-2xl shadow-black/60 overflow-hidden"
    role="dialog"
    aria-label="Últimas notificaciones"
  >
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
      <span class="text-sm font-semibold text-white">Notificaciones</span>
      <span v-if="unreadCount > 0" class="text-[11px] px-2 py-0.5 rounded-full bg-red-600/20 text-red-300">{{ unreadCount }} sin leer</span>
    </div>

    <div v-if="bellLoading && !bellItems.length" class="p-3 space-y-2">
      <div v-for="i in 3" :key="i" class="h-12 rounded-lg bg-gray-800 animate-pulse"></div>
    </div>
    <div v-else-if="!bellItems.length" class="px-4 py-8 text-center text-sm text-gray-500">No tienes notificaciones.</div>
    <ul v-else class="max-h-[60vh] overflow-y-auto divide-y divide-gray-800">
      <li v-for="c in bellItems" :key="c.user.id">
        <button
          type="button"
          class="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-gray-800/70 transition"
          @click="openConversation(c.user.id)"
        >
          <div class="relative shrink-0">
            <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs font-semibold text-gray-200">
              <img v-if="c.user.photo" :src="c.user.photo" class="w-full h-full object-cover" alt="" />
              <span v-else>{{ (c.user.name || '?').charAt(0).toUpperCase() }}</span>
            </div>
            <PresenceBadge :user-id="c.user.id" size="xs" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm truncate" :class="c.unread_count ? 'text-white font-semibold' : 'text-gray-200'">{{ c.user.name || 'Usuario' }}</span>
              <span class="ml-auto shrink-0 text-[10px] text-gray-500">{{ timeAgo(c.last_at) }}</span>
            </div>
            <p class="text-xs truncate" :class="c.unread_count ? 'text-gray-200' : 'text-gray-400'">{{ c.last_message || 'Sin mensajes' }}</p>
          </div>
          <span v-if="c.unread_count" class="shrink-0 mt-1 min-w-[18px] h-[18px] px-1 rounded-full bg-blue-600 text-white text-[10px] font-semibold leading-[18px] text-center">{{ c.unread_count }}</span>
        </button>
      </li>
    </ul>

    <NuxtLink
      to="/notificaciones"
      class="block border-t border-gray-800 px-4 py-2.5 text-center text-sm text-blue-300 hover:bg-gray-800 hover:text-blue-200 transition"
      @click="bellOpen = false"
    >
      Ir al chat
    </NuxtLink>
  </div>
  </div>

  <div class="relative flex-shrink-0 avatar-container">
    <button @click.stop="toggle" class="relative">
      <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-lg font-bold shadow-md transition-transform hover:scale-105 overflow-hidden z-50">
        <img
          v-if="user?.photo"
          :src="user.photo"
          class="w-full h-full object-cover"
          alt="Avatar"
        />
        <span v-else>{{ initial }}</span>
      </div>
      <!-- house = remote work today, building = at the office -->
      <PresenceBadge :user-id="(user as any)?.id" corner="top-right" />
      <span
        v-if="attendanceStatus !== null"
        :class="['absolute bottom-0 right-0 w-2.5 h-2.5 border-2 border-gray-900 rounded-full transition-colors duration-300', statusDotClass]"
        :title="statusDotLabel"
      ></span>
    </button>
    <div
      v-if="open"
      class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-lg shadow-2xl overflow-hidden divide-y divide-gray-200 z-[70]"
    >
      <NuxtLink to="/profile" class="block px-4 py-2 text-sm hover:bg-gray-100">Perfil</NuxtLink>
      <button @click="logoutAndClose" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100">Cerrar sesión</button>
    </div>
  </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
declare const $fetch: any
import { useAuth } from '../composables/useAuth'
import { useRouter } from 'vue-router'

declare const process: any

const { user, logout, apiBase, authenticated, fetchUser, unreadNotifications, lastReceivedMessage } = useAuth()

// Bell badge: shake the bell and pop the badge when a message arrives in real
// time (not when the count is first loaded on page navigation).
const unreadCount = computed(() => unreadNotifications.value || 0)
const ringing = ref(false)
let ringTimer: ReturnType<typeof setTimeout> | null = null
// MessageSent is broadcast only on the recipient's private channel.
watch(lastReceivedMessage, (msg) => {
  if (!msg) return
  ringing.value = false
  if (ringTimer) clearTimeout(ringTimer)
  // next tick so the animation restarts on back-to-back notifications
  requestAnimationFrame(() => {
    ringing.value = true
    ringTimer = setTimeout(() => { ringing.value = false }, 1000)
  })
})
onBeforeUnmount(() => { if (ringTimer) clearTimeout(ringTimer) })

// ── Bell dropdown: 5 most recent conversations ──
const bellOpen = ref(false)
const bellLoading = ref(false)
const bellItems = ref<any[]>([])

async function loadBellItems() {
  if (!authenticated.value) return
  bellLoading.value = true
  try {
    const res: any = await $fetch('/api/notifications/conversations')
    bellItems.value = (res?.conversations || [])
      .filter((c: any) => c.last_at)
      .sort((a: any, b: any) => (parseDate(b.last_at)?.getTime() || 0) - (parseDate(a.last_at)?.getTime() || 0))
      .slice(0, 5)
  } catch (e) {
    console.error('failed to load notifications', e)
  } finally {
    bellLoading.value = false
  }
}

function toggleBell() {
  bellOpen.value = !bellOpen.value
  open.value = false
  if (bellOpen.value) loadBellItems()
}

function openConversation(userId: number) {
  bellOpen.value = false
  router.push({ path: '/notificaciones', query: { user: String(userId) } })
}

// a new message while the dropdown is open: refresh the list
watch(lastReceivedMessage, () => { if (bellOpen.value) loadBellItems() })

// Shared clock-in status (useAuth): updated by the clock-in pill and over
// WebSocket from the user's other tabs/devices, so the dot changes live.
const { attendanceStatus } = useAuth()
const statusDotClass = computed(() =>
  attendanceStatus.value === 'working' ? 'bg-green-400' : attendanceStatus.value === 'paused' ? 'bg-amber-400' : 'bg-red-400',
)
const statusDotLabel = computed(() =>
  attendanceStatus.value === 'working' ? 'Trabajando' : attendanceStatus.value === 'paused' ? 'En pausa' : 'Sin fichar',
)

// Fallback for pages without the attendance pill: load the status once.
async function loadAttendanceStatus() {
  if (attendanceStatus.value !== null) return
  try {
    if (!authenticated.value) return
    await fetchUser()
    const res: any = await $fetch(`/api/attendance/status`)
    // the pill may have answered first (it also knows about pauses)
    if (attendanceStatus.value === null) attendanceStatus.value = res?.working ? 'working' : 'idle'
  } catch (e) {
    console.error('failed to load attendance status', e)
  }
}

onMounted(() => {
  document.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.avatar-container')) {
      open.value = false
    }
    if (!target.closest('.bell-container')) {
      bellOpen.value = false
    }
  })
  loadAttendanceStatus()
})
const router = useRouter()
const open = ref(false)

const initial = computed(() => {
  const name = user.value?.name || ''
  return name.charAt(0).toUpperCase() || '?'
})

function toggle() {
  open.value = !open.value
  bellOpen.value = false
}

function logoutAndClose() {
  open.value = false
  logout().then(() => router.push('/login')) // resetSession() clears the status
}

</script>

<style scoped>
.bell-ring {
  transform-origin: top center;
  animation: bell-ring 0.9s ease-in-out;
}
.badge-pop {
  animation: badge-pop 0.5s ease-out;
}
@keyframes bell-ring {
  0%, 100% { transform: rotate(0); }
  15% { transform: rotate(14deg); }
  30% { transform: rotate(-12deg); }
  45% { transform: rotate(9deg); }
  60% { transform: rotate(-6deg); }
  75% { transform: rotate(3deg); }
}
@keyframes badge-pop {
  0% { transform: scale(0.4); }
  60% { transform: scale(1.25); }
  100% { transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
  .bell-ring, .badge-pop { animation: none; }
}
</style>
