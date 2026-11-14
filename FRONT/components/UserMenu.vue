<template>
  <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
  <!-- Notifications bell: live unread count (updated over WebSocket in useAuth) -->
  <NuxtLink
    to="/notificaciones"
    class="relative w-10 h-10 rounded-full flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-800 transition"
    :aria-label="unreadCount > 0 ? `Notificaciones: ${unreadCount} sin leer` : 'Notificaciones'"
    :title="unreadCount > 0 ? `${unreadCount} sin leer` : 'Notificaciones'"
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
  </NuxtLink>

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

// Shared with AttendanceButton: it updates this the moment you clock in,
// pause, resume or clock out, so the dot changes colour live.
const attendanceStatus = useState<'idle' | 'working' | 'paused' | null>('attendance_status', () => null)
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
}

function logoutAndClose() {
  open.value = false
  attendanceStatus.value = null // don't carry it over to the next user
  logout().then(() => router.push('/login'))
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
