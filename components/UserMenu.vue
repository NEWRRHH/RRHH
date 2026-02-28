<template>
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
        v-if="attendanceWorking !== null"
        :class="['absolute bottom-0 right-0 w-2 h-2 border-2 border-gray-800 rounded-full', attendanceWorking ? 'bg-green-400' : 'bg-red-400']"
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
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
declare const $fetch: any
import { useAuth } from '../composables/useAuth'
import { useRouter } from 'vue-router'

declare const process: any

const { user, logout, apiBase, token, fetchUser } = useAuth()

const attendanceWorking = ref<boolean | null>(null)

async function loadAttendanceStatus() {
  try {
    if (!token.value) {
      attendanceWorking.value = false
      return
    }

    await fetchUser()
    const res: any = await $fetch(`${apiBase}/api/attendance/status`, {
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json'
      }
    })
    attendanceWorking.value = res.working
  } catch (e) {
    console.error('failed to load attendance status', e)
    attendanceWorking.value = false
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
  logout().then(() => router.push('/login'))
}

</script>
