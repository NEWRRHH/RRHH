<template>
  <div class="flex min-h-screen bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur overflow-x-auto">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Perfil</h1>
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

            <!-- schedule read-only list -->
            <div class="mb-6 pt-4 border-t border-gray-700">
              <h3 class="text-sm text-gray-200 mb-3">Jornadas del usuario</h3>
              <div v-if="displayScheduleTemplates.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <article
                  v-for="tpl in displayScheduleTemplates"
                  :key="`profile-assigned-${tpl.id}`"
                  class="rounded-xl border border-cyan-500/30 bg-gradient-to-br from-cyan-500/10 to-sky-500/5 p-3"
                >
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-cyan-100">{{ tpl.start_time }} - {{ tpl.end_time }}</p>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-cyan-400/20 text-cyan-200">Asignada</span>
                  </div>
                  <div class="mt-2 flex flex-wrap gap-1.5">
                    <span v-for="day in tpl.days" :key="`profile-assigned-day-${tpl.id}-${day}`" class="text-[11px] px-2 py-0.5 rounded-full border border-gray-700 bg-gray-900/60 text-gray-200">
                      {{ day }}
                    </span>
                  </div>
                </article>
              </div>
              <div v-else class="rounded-xl border border-gray-800 bg-gray-900/60 px-3 py-3 text-xs text-gray-400">
                No hay jornadas asignadas.
              </div>

              <div class="mt-3">
                <p class="text-xs text-gray-500">La asignación de jornadas se gestiona desde RRHH.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-sm text-gray-300 mb-1" for="birth_date">Fecha de cumpleaños</label>
                    <input id="birth_date" v-model="form.birth_date" type="date" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" />
                  </div>
                  <div>
                    <label class="block text-sm text-gray-300 mb-1" for="dni">DNI</label>
                    <input id="dni" v-model="form.dni" type="text" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" />
                  </div>
                  <div>
                    <label class="block text-sm text-gray-300 mb-1" for="ssn">Nro seguridad social</label>
                    <input id="ssn" v-model="form.social_security_number" type="text" class="w-full px-3 py-2 bg-gray-700/60 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" />
                  </div>
                  <div>
                    <label class="block text-sm text-gray-300 mb-1" for="contract_type">Tipo de contrato</label>
                    <input id="contract_type" :value="contractTypeLabel" disabled class="w-full px-3 py-2 bg-gray-800 text-gray-300 rounded-lg border border-gray-700 cursor-not-allowed" />
                    <p class="mt-1 text-[11px] text-gray-500">Solo lectura. Este dato lo gestiona RRHH.</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-300 mb-1" for="contract_start_date">Inicio de contrato</label>
                    <input id="contract_start_date" v-model="form.contract_start_date" type="date" disabled class="w-full px-3 py-2 bg-gray-800 text-gray-300 rounded-lg border border-gray-700 cursor-not-allowed" />
                    <p class="mt-1 text-[11px] text-gray-500">Solo lectura. Este dato lo gestiona RRHH.</p>
                  </div>
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
import { ref, onBeforeMount, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'


declare const $fetch: any

const { token, apiBase, user, fetchUser, logout } = useAuth()
const { $swal } = useNuxtApp()
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
  days: ['L', 'M', 'X', 'J', 'V'],
  birth_date: '',
  dni: '',
  social_security_number: '',
  contract_type: '',
  contract_start_date: '',
})
const loading = ref(false)
const preview = ref<string | null>(null)
const assignedScheduleTemplates = ref<any[]>([])

const contractTypeLabel = computed(() => {
  const raw = String(form.value.contract_type || '').trim()
  if (!raw) return 'Sin definir'
  const value = raw.toLowerCase()
  if (value === 'indefinido') return 'Indefinido'
  if (value === 'temporal') return 'Temporal'
  if (value === 'practicas') return 'Practicas'
  if (value === 'autonomo') return 'Autonomo'
  return raw
})

const displayScheduleTemplates = computed(() => {
  if (assignedScheduleTemplates.value.length) return assignedScheduleTemplates.value
  if (!form.value.start_time || !form.value.end_time) return []
  return [{
    id: 0,
    start_time: form.value.start_time,
    end_time: form.value.end_time,
    days: Array.isArray(form.value.days) ? form.value.days : ['L', 'M', 'X', 'J', 'V'],
  }]
})

onBeforeMount(async () => {
  if (token.value) {
    await fetchUser()
    if (user.value) fillForm()
  } else if (user.value) {
    fillForm()
  }
})

function fillForm() {
  form.value.name = user.value.name || ''
  form.value.email = user.value.email || ''
  form.value.birth_date = user.value.birth_date || ''
  form.value.dni = user.value.dni || ''
  form.value.social_security_number = user.value.social_security_number || ''
  form.value.contract_type = user.value.contract_type || ''
  form.value.contract_start_date = user.value.contract_start_date || ''
  if (user.value.photo) preview.value = user.value.photo
  // fetch schedule info for every user
  if (user.value.id) {
    fetchSchedule()
  }
}

function normalizeTimeToHm(value?: string | null) {
  if (!value) return ''
  const raw = String(value).trim()
  if (/^\d{2}:\d{2}:\d{2}$/.test(raw)) return raw.slice(0, 5)
  if (/^\d{2}:\d{2}$/.test(raw)) return raw
  return raw
}

const fetchSchedule = async () => {
  try {
    const res = await $fetch(`${apiBase}/api/attendance/info`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (res?.schedule) {
      form.value.start_time = normalizeTimeToHm(res.schedule.start_time || '')
      form.value.end_time = normalizeTimeToHm(res.schedule.end_time || '')
      form.value.days = Array.isArray(res.schedule.days) && res.schedule.days.length
        ? res.schedule.days
        : ['L', 'M', 'X', 'J', 'V']
    }
    assignedScheduleTemplates.value = Array.isArray(res?.assigned_schedule_templates)
      ? res.assigned_schedule_templates
      : []
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
    data.append('birth_date', form.value.birth_date || '')
    data.append('dni', form.value.dni || '')
    data.append('social_security_number', form.value.social_security_number || '')
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
        $swal.toast('success', 'Contraseña actualizada')
      } catch (err) {
        $swal.toast('error', 'No se pudo cambiar la contraseña')
      }
    }

    $swal.toast('success', 'Perfil actualizado')
  } catch (e) {
    $swal.toast('error', 'Error al guardar')
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
