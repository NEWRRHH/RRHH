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
        v-if="user?.session_token"
        class="absolute bottom-0 right-0 w-2 h-2 bg-green-400 border-2 border-gray-800 rounded-full"
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
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

declare const process: any

const { user, logout } = useAuth()
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

onMounted(() => {
  document.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.avatar-container')) {
      open.value = false
    }
  })
})
</script>
