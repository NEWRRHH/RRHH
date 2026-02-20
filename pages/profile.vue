<template>
  <div class="flex min-h-screen bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Perfil</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton />
          <UserMenu />
        </div>
      </header>

      <main class="flex-1 p-6 overflow-auto">
        <div class="max-w-lg mx-auto">
          <h2 class="text-2xl font-bold text-white mb-6">Mi perfil</h2>
          <form @submit.prevent="save" enctype="multipart/form-data" class="space-y-6">
            <!-- personal info with photo preview on left -->
            <div class="flex items-start gap-6">
              <div class="flex-shrink-0">
                <div class="w-24 h-24 rounded-full bg-gray-700 overflow-hidden shadow-inner">
                  <img v-if="preview" :src="preview" class="w-full h-full object-cover" />
                  <span v-else class="flex items-center justify-center h-full text-gray-400 text-2xl">
                    {{ user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                  </span>
                </div>
                <label class="block text-xs text-gray-300 mt-2 text-center" for="photo">Cambiar foto</label>
                <input id="photo" type="file" @change="onPhotoChange" class="hidden" />
              </div>
              <div class="flex-1 space-y-4">
                <div>
                  <label class="block text-sm text-gray-300 mb-1" for="name">Nombre</label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="w-full px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="Tu nombre"
                  />
                </div>
                <div>
                  <label class="block text-sm text-gray-300 mb-1" for="email">Email</label>
                  <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="correo@ejemplo.com"
                  />
                </div>
              </div>
            </div>

            <!-- password section -->
            <div class="mb-6 pt-4 border-t border-gray-700">
              <h3 class="text-sm text-gray-300 mb-2">Cambiar contraseña</h3>
              <div class="mb-3">
                <input
                  v-model="form.current_password"
                  type="password"
                  placeholder="Contraseña actual"
                  class="w-full px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
              <div class="mb-3">
                <input
                  v-model="form.password"
                  type="password"
                  placeholder="Nueva contraseña"
                  class="w-full px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
              <div class="mb-3">
                <input
                  v-model="form.password_confirmation"
                  type="password"
                  placeholder="Confirmar contraseña"
                  class="w-full px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
              <p class="text-xs text-gray-500">
                Mínimo 8 caracteres, una mayúscula, una minúscula y un carácter especial.
              </p>
            </div>

            <!-- schedule for admins -->
            <div v-if="isAdmin" class="mb-6 pt-4 border-t border-gray-700">
              <h3 class="text-sm text-gray-300 mb-2">Horario de trabajo</h3>
              <div class="flex gap-2">
                <input
                  v-model="form.start_time"
                  type="time"
                  class="w-1/2 px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
                <input
                  v-model="form.end_time"
                  type="time"
                  class="w-1/2 px-3 py-2 bg-gray-700/60 placeholder-gray-400 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                />
              </div>
            </div>

            <div class="flex justify-end">
              <button
                :disabled="loading"
                class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-2 rounded-full text-white font-semibold hover:from-blue-600 hover:to-blue-700 transition disabled:opacity-50"
              >
                Guardar cambios
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

declare const $fetch: any

const { token, apiBase, user, fetchUser, logout } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const form = ref<any>({
  name: '',
  email: '',
  current_password: '',
  password: '',
  password_confirmation: '',
  start_time: '',
  end_time: '',
})
const loading = ref(false)
const preview = ref<string | null>(null)

const isAdmin = computed(() => user.value?.user_type_id === 1)

onBeforeMount(async () => {
  if (user.value) {
    fillForm()
  } else if (token.value) {
    await fetchUser()
    if (user.value) fillForm()
  }
})

function fillForm() {
  form.value.name = user.value.name || ''
  form.value.email = user.value.email || ''
  if (user.value.photo) preview.value = user.value.photo
  if (isAdmin.value && user.value.id) {
    // fetch schedule info
    fetchSchedule()
  }
}

const fetchSchedule = async () => {
  try {
    const res = await $fetch(`${apiBase}/api/attendance/info`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (res?.schedule) {
      form.value.start_time = res.schedule.start_time || ''
      form.value.end_time = res.schedule.end_time || ''
    }
  } catch (e) {
    console.error('schedule fetch failed', e)
  }
}

const onPhotoChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const reader = new FileReader()
    reader.onload = (ev) => {
      preview.value = ev.target?.result as string
    }
    reader.readAsDataURL(input.files[0])
    form.value.photo = input.files[0]
  }
}

const save = async () => {
  if (!token.value) return
  loading.value = true
  try {
    const data = new FormData()
    data.append('name', form.value.name)
    data.append('email', form.value.email)
    if (form.value.photo) data.append('photo', form.value.photo)
    // update profile
    const res: any = await $fetch(`${apiBase}/api/user`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: data,
    })
    user.value = res

    // password change?
    if (form.value.password) {
      try {
        await $fetch(`${apiBase}/api/user/password`, {
          method: 'PUT',
          headers: { Authorization: `Bearer ${token.value}` },
          body: {
            current_password: form.value.current_password,
            password: form.value.password,
            password_confirmation: form.value.password_confirmation,
          },
        })
        alert('Contraseña actualizada')
      } catch (err) {
        console.error('password update failed', err)
        alert('No se pudo cambiar la contraseña')
      }
    }

    // schedule update if admin
    if (isAdmin.value) {
      try {
        await $fetch(`${apiBase}/api/user/schedule`, {
          method: 'PUT',
          headers: { Authorization: `Bearer ${token.value}` },
          body: {
            start_time: form.value.start_time,
            end_time: form.value.end_time,
          },
        })
      } catch (err) {
        console.error('schedule update failed', err)
      }
    }

    alert('Perfil actualizado')
  } catch (e) {
    console.error('update profile failed', e)
    alert('Error al guardar')
  } finally {
    loading.value = false
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}
</script>