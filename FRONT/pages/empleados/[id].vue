<template>
  <div class="flex min-h-screen bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <h1 class="text-base font-semibold text-white">Editar empleado</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="flex-1 p-6 overflow-auto">
        <div class="max-w-3xl mx-auto">
          <template v-if="loadingEmployee">
            <div class="text-white text-center py-20">Cargando empleado...</div>
          </template>
          <template v-else-if="error">
            <div class="text-red-400 text-center py-20">{{ error }}</div>
          </template>
          <template v-else>
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
              <h2 class="text-2xl font-bold text-white mb-6">Información del empleado</h2>

              <form @submit.prevent="save" enctype="multipart/form-data" class="space-y-6">
            <div class="flex items-start gap-6">
              <div class="flex-shrink-0">
                <div class="w-24 h-24 rounded-full bg-gray-700 overflow-hidden shadow-inner">
                  <img v-if="preview" :src="preview" class="w-full h-full object-cover" />
                  <span v-else class="flex items-center justify-center h-full text-gray-400 text-2xl">
                    {{ (form.name || '?').charAt(0).toUpperCase() }}
                  </span>
                </div>
                <label class="block text-xs text-gray-300 mt-2 text-center" for="photo">Cambiar foto</label>
                <input id="photo" type="file" @change="onPhotoChange" class="hidden" />
              </div>

              <div class="flex-1 space-y-4">
                <div>
                  <label class="block text-sm text-gray-300 mb-1" for="name">Nombre</label>
                  <input id="name" v-model="form.name" type="text" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label class="block text-sm text-gray-300 mb-1" for="email">Email</label>
                  <input id="email" v-model="form.email" type="email" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label class="block text-sm text-gray-300 mb-1" for="team">Equipo</label>
                  <select id="team" v-model="form.team_id" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option :value="null">Sin equipo</option>
                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-gray-700">
              <h3 class="text-sm text-gray-300 mb-2">Cambiar contraseña</h3>
              <input v-model="form.password" type="password" placeholder="Nueva contraseña" class="w-full mb-3 px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <input v-model="form.password_confirmation" type="password" placeholder="Confirmar contraseña" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p class="text-xs text-gray-500 mt-2">Mínimo 8 caracteres, una mayúscula, una minúscula y un carácter especial.</p>
            </div>

            <div class="pt-4 border-t border-gray-700">
              <h3 class="text-sm text-gray-300 mb-2">Horario de trabajo</h3>
              <div class="flex gap-2">
                <input v-model="form.start_time" type="time" class="w-1/2 px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <input v-model="form.end_time" type="time" class="w-1/2 px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button type="button" @click="router.push('/empleados')" class="px-4 py-2 rounded bg-gray-700 text-white">Cancelar</button>
              <button :disabled="loading" type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-2 rounded-full text-white font-semibold hover:from-blue-600 hover:to-blue-700 transition disabled:opacity-50">Guardar cambios</button>
            </div>
          </form>
        </div>
          </template>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import AppSidebar from '../../components/AppSidebar.vue'
import AttendanceButton from '../../components/AttendanceButton.vue'
import UserMenu from '../../components/UserMenu.vue'

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const route = useRoute()
const sidebar = ref<{ open: boolean } | null>(null)
const loading = ref(false)

const teams = ref<any[]>([])
const form = ref<any>({
  name: '',
  email: '',
  team_id: null,
  password: '',
  password_confirmation: '',
  start_time: '',
  end_time: '',
  photo: null,
})
const preview = ref<string | null>(null)

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function onPhotoChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const file = input.files[0]
    form.value.photo = file
    const reader = new FileReader()
    reader.onload = (ev) => {
      preview.value = (ev.target?.result as string) || null
    }
    reader.readAsDataURL(file)
  }
}

const error = ref<string | null>(null)
const loadingEmployee = ref(false)

async function loadEmployee() {
  error.value = null
  loadingEmployee.value = true
  const employeeId = route.params.id
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    const employee = res?.employee || {}
    form.value.name = employee.name || ''
    form.value.email = employee.email || ''
    form.value.team_id = employee.team_id ?? null
    if (employee.photo || employee.profile_photo_path) {
      preview.value = employee.photo || employee.profile_photo_path
    }

    const schedule = res?.schedule || null
    form.value.start_time = schedule?.start_time || ''
    form.value.end_time = schedule?.end_time || ''
    teams.value = res?.teams || []
  } catch (e: any) {
    console.error('failed to load employee', e)
    error.value = e?.data?.message || 'No se pudo cargar el empleado'
    // if the error is forbidden or not found, go back to the list after logging
    if (e?.status === 403 || e?.status === 404) {
      setTimeout(() => {
        router.push('/empleados')
      }, 1000)
    }
  } finally {
    loadingEmployee.value = false
  }
}

async function save() {
  loading.value = true
  try {
    const employeeId = route.params.id
    const data = new FormData()
    data.append('_method', 'PUT')
    data.append('name', form.value.name)
    data.append('email', form.value.email)
    if (form.value.team_id !== null && form.value.team_id !== '') data.append('team_id', String(form.value.team_id))
    if (form.value.password) {
      data.append('password', form.value.password)
      data.append('password_confirmation', form.value.password_confirmation)
    }
    if (form.value.start_time) data.append('start_time', form.value.start_time)
    if (form.value.end_time) data.append('end_time', form.value.end_time)
    if (form.value.photo) data.append('photo', form.value.photo)

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId}`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: data,
    })

    router.push('/empleados')
  } finally {
    loading.value = false
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

onBeforeMount(async () => {
  if (!token.value) {
    if (process.client) {
      const saved = localStorage.getItem('rrhh_token')
      if (saved) {
        setToken(saved)
        await fetchUser()
      } else {
        return router.push('/login')
      }
    } else {
      return
    }
  } else {
    await fetchUser()
  }

  await loadEmployee()
})
</script>
