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

        <h1 class="text-base font-semibold text-white">Empleados</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 p-6 overflow-auto">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between gap-3">
            <h2 class="text-white font-semibold">Listado de empleados</h2>
            <button
              v-if="canCreateEmployee"
              @click="openCreateModal"
              class="px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-500 transition"
            >
              Crear usuario
            </button>
          </div>
          <div class="px-5 py-3 border-b border-gray-800 flex flex-wrap gap-2">
            <button
              v-for="team in teamTabs"
              :key="team"
              @click="selectedTeam = team"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                selectedTeam === team
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
              ]"
            >
              {{ team }}
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-800/60 text-gray-300">
                <tr>
                  <th class="text-left px-4 py-3">Empleado</th>
                  <th class="text-left px-4 py-3">Email</th>
                  <th class="text-left px-4 py-3">Equipo</th>
                  <th class="text-right px-4 py-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="employee in filteredEmployees" :key="employee.id" class="border-t border-gray-800 text-gray-200 hover:bg-gray-800/40">
                  <td class="px-4 py-3">
                    <button class="flex items-center gap-3 w-full text-left" :disabled="!canViewEmployeeDetails" @click="goToEdit(employee)">
                      <div class="w-9 h-9 rounded-full overflow-hidden bg-blue-600 flex items-center justify-center font-semibold">
                        <img v-if="employee.photo || employee.profile_photo_path" :src="resolvePhotoUrl(employee.photo || employee.profile_photo_path)" class="w-full h-full object-cover" />
                        <span v-else>{{ (employee.name || '?').charAt(0).toUpperCase() }}</span>
                      </div>
                      <span :class="canViewEmployeeDetails ? 'text-white' : 'text-gray-300'">{{ employee.name }}</span>
                    </button>
                  </td>
                  <td class="px-4 py-3 text-gray-300">{{ employee.email }}</td>
                  <td class="px-4 py-3 text-gray-300">{{ employee.team_name || 'Sin equipo' }}</td>
                  <td class="px-4 py-3 text-right">
                    <div v-if="canViewEmployeeDetails || canDeleteEmployee" class="relative inline-block action-menu">
                      <button @click.stop="toggleMenu(employee.id)" class="p-2 rounded hover:bg-gray-800">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6h.01M12 12h.01M12 18h.01"/>
                        </svg>
                      </button>

                      <div
                        v-if="openMenuId === employee.id"
                        class="absolute right-0 mt-1 w-40 bg-white text-gray-800 rounded-lg shadow-xl overflow-hidden divide-y divide-gray-200 z-[70]"
                      >
                        <button v-if="canViewEmployeeDetails" @click="goToEdit(employee)" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5h2m-1-1v2m-7 8l9-9 3 3-9 9H5v-3z"/>
                          </svg>
                          Ver detalle
                        </button>
                        <button v-if="canDeleteEmployee" @click="removeEmployee(employee)" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2 text-red-600">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/>
                          </svg>
                          Eliminar
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <tr v-if="!filteredEmployees.length">
                  <td colspan="4" class="px-4 py-6 text-center text-gray-400">No hay empleados disponibles</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>

    <div v-if="createModalOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <button class="absolute inset-0 bg-black/70" @click="closeCreateModal"></button>
      <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-700 bg-gray-900 p-5">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h3 class="text-lg font-semibold text-white">Crear nuevo usuario</h3>
            <p class="text-xs text-gray-400">Completa los campos de la tabla de usuarios para dar de alta al empleado.</p>
          </div>
          <button class="text-gray-400 hover:text-white" @click="closeCreateModal">✕</button>
        </div>

        <form class="space-y-4" @submit.prevent="submitCreateEmployee">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Nombre completo *</label>
              <input v-model="createForm.name" required class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Username</label>
              <input v-model="createForm.username" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Email *</label>
              <input v-model="createForm.email" type="email" required class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Telefono</label>
              <input v-model="createForm.phone" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Nombres</label>
              <input v-model="createForm.first_name" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Apellidos</label>
              <input v-model="createForm.last_name" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Equipo</label>
              <select v-model="createForm.team_id" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                <option :value="null">Sin equipo</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Tipo de usuario</label>
              <select v-model="createForm.user_type_id" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                <option :value="null">Sin tipo</option>
                <option v-for="type in userTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Rol</label>
              <input v-model="createForm.role" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">DNI</label>
              <input v-model="createForm.dni" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Nro seguridad social</label>
              <input v-model="createForm.social_security_number" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Fecha de nacimiento</label>
              <input v-model="createForm.birth_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Fecha de ingreso</label>
              <input v-model="createForm.hire_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Tipo de contrato</label>
              <select v-model="createForm.contract_type" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                <option value="">Sin definir</option>
                <option value="indefinido">Indefinido</option>
                <option value="temporal">Temporal</option>
                <option value="practicas">Practicas</option>
                <option value="autonomo">Autonomo</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Inicio de contrato</label>
              <input v-model="createForm.contract_start_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Dias de vacaciones</label>
              <input v-model.number="createForm.vacation_days_total" type="number" min="0" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Foto</label>
              <input type="file" accept="image/*" @change="onCreatePhotoChange" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Contrasena *</label>
              <input v-model="createForm.password" type="password" required class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Confirmar contrasena *</label>
              <input v-model="createForm.password_confirmation" type="password" required class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
            </div>
          </div>

          <div v-if="isHrTeam" class="pt-3 border-t border-gray-800">
            <h4 class="text-sm text-gray-300 mb-2">Horario laboral</h4>
            <div class="mb-3">
              <label class="block text-xs text-gray-400 mb-2">Seleccionar horarios existentes</label>
              <div v-if="scheduleTemplates.length" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <label
                  v-for="schedule in scheduleTemplates"
                  :key="`tpl-${schedule.id}`"
                  class="inline-flex items-center gap-2 text-xs text-gray-200 bg-gray-800 border border-gray-700 px-2 py-2 rounded"
                >
                  <input
                    type="checkbox"
                    class="accent-blue-500"
                    :checked="(createForm.schedule_template_ids || []).includes(schedule.id)"
                    @change="toggleTemplateSelection(schedule.id, ($event.target as HTMLInputElement).checked)"
                  />
                  <span>{{ schedule.label }}</span>
                </label>
              </div>
              <p v-else class="text-xs text-gray-500">No hay horarios cargados en Configuracion.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-400 mb-1">Hora de entrada</label>
                <input v-model="createForm.start_time" type="time" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Hora de salida</label>
                <input v-model="createForm.end_time" type="time" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <label v-for="d in availableWorkDayOptions" :key="`create-${d}`" class="inline-flex items-center gap-2 text-xs text-gray-300 bg-gray-800 px-2 py-1 rounded">
                <input type="checkbox" :value="d" v-model="createForm.days" class="accent-blue-500" />
                <span>{{ d }}</span>
              </label>
            </div>
          </div>

          <p v-if="createError" class="text-sm text-red-400">{{ createError }}</p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="closeCreateModal">Cancelar</button>
            <button :disabled="creatingEmployee" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-50">
              {{ creatingEmployee ? 'Creando...' : 'Crear usuario' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeMount, onMounted } from 'vue'

definePageMeta({ auth: true })
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import AppSidebar from '../../components/AppSidebar.vue'
import AttendanceButton from '../../components/AttendanceButton.vue'
import UserMenu from '../../components/UserMenu.vue'

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken, user } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const employees = ref<any[]>([])
const teams = ref<any[]>([])
const userTypes = ref<any[]>([])
const scheduleTemplates = ref<any[]>([])
const openMenuId = ref<number | null>(null)
const selectedTeam = ref('Todos')
const createModalOpen = ref(false)
const creatingEmployee = ref(false)
const createError = ref('')
const defaultDayOptions = ['L', 'M', 'X', 'J', 'V', 'S', 'D']

const createForm = ref<any>({
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  first_name: '',
  last_name: '',
  birth_date: '',
  hire_date: '',
  phone: '',
  user_type_id: null,
  team_id: null,
  role: '',
  dni: '',
  social_security_number: '',
  contract_type: '',
  contract_start_date: '',
  vacation_days_total: 0,
  schedule_template_ids: [],
  start_time: '',
  end_time: '',
  days: ['L', 'M', 'X', 'J', 'V'],
  photo: null,
})

const availableWorkDayOptions = computed(() => {
  const set = new Set<string>()
  for (const schedule of scheduleTemplates.value) {
    for (const day of schedule?.days || []) {
      if (defaultDayOptions.includes(day)) set.add(day)
    }
  }
  const options = defaultDayOptions.filter((d) => set.has(d))
  return options.length ? options : defaultDayOptions
})

const teamTabs = computed(() => {
  const teams = new Set<string>()
  for (const employee of employees.value) {
    teams.add(employee.team_name || 'Sin equipo')
  }
  const ordered = Array.from(teams).sort((a, b) => a.localeCompare(b, 'es'))
  return ['Todos', ...ordered]
})

const filteredEmployees = computed(() => {
  if (selectedTeam.value === 'Todos') return employees.value
  return employees.value.filter((employee) => (employee.team_name || 'Sin equipo') === selectedTeam.value)
})
const canViewEmployeeDetails = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.is_admin || Number(currentUser?.user_type_id || 0) === 1) return true
  if (currentUser?.can_view_employee_details) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.view_details')
})
const canDeleteEmployee = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.is_admin || Number(currentUser?.user_type_id || 0) === 1) return true
  if (currentUser?.can_delete_employee) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.delete')
})
const canCreateEmployee = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.is_admin || Number(currentUser?.user_type_id || 0) === 1) return true
  if (currentUser?.can_create_employee) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.create')
})
const isHrTeam = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_hr_team)
})

function resolvePhotoUrl(path?: string | null) {
  if (!path) return ''
  const raw = String(path)
  if (/^https?:\/\//i.test(raw)) return raw
  const base = String(apiBase || 'http://localhost:8000').replace(/\/+$/, '')
  return raw.startsWith('/') ? `${base}${raw}` : `${base}/${raw}`
}

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function toggleMenu(id: number) {
  openMenuId.value = openMenuId.value === id ? null : id
}

function goToEdit(employee: any) {
  if (!canViewEmployeeDetails.value) return
  openMenuId.value = null
  console.log('navigating to edit', employee.id)
  router.push(`/empleados/${employee.id}`)
}

function resetCreateForm() {
  createForm.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    first_name: '',
    last_name: '',
    birth_date: '',
    hire_date: '',
    phone: '',
    user_type_id: null,
    team_id: null,
    role: '',
    dni: '',
    social_security_number: '',
    contract_type: '',
    contract_start_date: '',
    vacation_days_total: 0,
    schedule_template_ids: [],
    start_time: '',
    end_time: '',
    days: ['L', 'M', 'X', 'J', 'V'],
    photo: null,
  }
  createError.value = ''
}

async function openCreateModal() {
  if (!canCreateEmployee.value) return
  await loadEmployees()
  resetCreateForm()
  createModalOpen.value = true
}

function closeCreateModal() {
  createModalOpen.value = false
  createError.value = ''
}

function onCreatePhotoChange(event: Event) {
  const input = event.target as HTMLInputElement
  createForm.value.photo = input?.files?.[0] || null
}

function toggleTemplateSelection(templateId: number, checked: boolean) {
  const current = Array.isArray(createForm.value.schedule_template_ids) ? [...createForm.value.schedule_template_ids] : []
  const set = new Set<number>(current.map((id: any) => Number(id)))
  if (checked) set.add(templateId)
  else set.delete(templateId)

  const ids = Array.from(set.values())
  createForm.value.schedule_template_ids = ids

  if (!ids.length) {
    return
  }

  const templates = scheduleTemplates.value.filter((s: any) => ids.includes(Number(s.id)))
  const unionDays = new Set<string>()
  for (const template of templates) {
    for (const day of template?.days || []) {
      if (defaultDayOptions.includes(day)) unionDays.add(day)
    }
  }

  const first = templates[0]
  if (first?.start_time) createForm.value.start_time = first.start_time
  if (first?.end_time) createForm.value.end_time = first.end_time

  const days = defaultDayOptions.filter((d) => unionDays.has(d))
  if (days.length) createForm.value.days = days
}

function isValidYmdDate(value: string) {
  if (!value) return true
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const [y, m, d] = value.split('-').map((part) => Number(part))
  const date = new Date(Date.UTC(y, m - 1, d))
  return date.getUTCFullYear() === y && (date.getUTCMonth() + 1) === m && date.getUTCDate() === d
}

async function removeEmployee(employee: any) {
  if (!canDeleteEmployee.value) return
  openMenuId.value = null
  const ok = confirm(`¿Eliminar a ${employee.name}?`)
  if (!ok) return
  await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employee.id}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token.value}` },
  })
  await loadEmployees()
}

async function loadEmployees() {
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    employees.value = res?.employees || []
    teams.value = res?.teams || []
    userTypes.value = res?.user_types || []
    scheduleTemplates.value = res?.schedule_templates || []
  } catch (e: any) {
    if (e?.status === 403 || e?.data?.message === 'Forbidden') {
      router.push('/dashboard')
      return
    }
    employees.value = []
    teams.value = []
    userTypes.value = []
    scheduleTemplates.value = []
  }
}

async function submitCreateEmployee() {
  if (!canCreateEmployee.value || !token.value) return

  creatingEmployee.value = true
  createError.value = ''

  try {
    if (!isValidYmdDate(createForm.value.birth_date || '')) {
      createError.value = 'La fecha de nacimiento debe tener formato YYYY-MM-DD valido.'
      return
    }
    if (!isValidYmdDate(createForm.value.hire_date || '')) {
      createError.value = 'La fecha de ingreso debe tener formato YYYY-MM-DD valido.'
      return
    }
    if (!isValidYmdDate(createForm.value.contract_start_date || '')) {
      createError.value = 'La fecha de inicio de contrato debe tener formato YYYY-MM-DD valido.'
      return
    }

    const data = new FormData()
    data.append('name', createForm.value.name || '')
    data.append('username', createForm.value.username || '')
    data.append('email', createForm.value.email || '')
    data.append('password', createForm.value.password || '')
    data.append('password_confirmation', createForm.value.password_confirmation || '')
    data.append('first_name', createForm.value.first_name || '')
    data.append('last_name', createForm.value.last_name || '')
    data.append('birth_date', createForm.value.birth_date || '')
    data.append('hire_date', createForm.value.hire_date || '')
    data.append('phone', createForm.value.phone || '')
    data.append('role', createForm.value.role || '')
    data.append('dni', createForm.value.dni || '')
    data.append('social_security_number', createForm.value.social_security_number || '')
    data.append('contract_type', createForm.value.contract_type || '')
    data.append('contract_start_date', createForm.value.contract_start_date || '')
    data.append('vacation_days_total', String(Number(createForm.value.vacation_days_total || 0)))

    if (isHrTeam.value) {
      data.append('start_time', createForm.value.start_time || '')
      data.append('end_time', createForm.value.end_time || '')
      for (const d of createForm.value.days || []) data.append('days[]', d)
      for (const id of createForm.value.schedule_template_ids || []) data.append('schedule_template_ids[]', String(id))
    }

    if (createForm.value.team_id !== null && createForm.value.team_id !== '') {
      data.append('team_id', String(createForm.value.team_id))
    }
    if (createForm.value.user_type_id !== null && createForm.value.user_type_id !== '') {
      data.append('user_type_id', String(createForm.value.user_type_id))
    }
    if (createForm.value.photo) {
      data.append('photo', createForm.value.photo)
    }

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: data,
    })

    closeCreateModal()
    await loadEmployees()
  } catch (e: any) {
    createError.value = e?.data?.message || 'No se pudo crear el usuario'
  } finally {
    creatingEmployee.value = false
  }
}

watch(teamTabs, (tabs) => {
  if (!tabs.includes(selectedTeam.value)) {
    selectedTeam.value = 'Todos'
  }
})

const onLogout = async () => {
  await logout()
  router.push('/login')
}

onMounted(() => {
  document.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.action-menu')) {
      openMenuId.value = null
    }
  })
})

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
  const canViewEmployees = Boolean((user.value as any)?.is_hr_team) || Boolean((user.value as any)?.is_admin) || Number((user.value as any)?.user_type_id || 0) === 1
  if (!canViewEmployees) {
    return router.push('/dashboard')
  }

  await loadEmployees()
})
</script>
