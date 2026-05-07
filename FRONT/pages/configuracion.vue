<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Administración de Permisos</h1>
        <div class="relative flex items-center ml-8">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar rol..."
            class="px-4 pl-10 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <svg class="absolute left-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto">
        <div class="max-w-5xl mx-auto space-y-4">
          <div class="inline-flex max-w-full overflow-x-auto rounded-xl border border-gray-700 p-1 bg-gray-900">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
              :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>

          <section v-if="activeTab === 'dashboard'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">{{ isAdminUser ? 'Widgets del Dashboard' : 'Mensaje de bienvenida' }}</h2>
              <div class="flex gap-2">
                <button v-if="isAdminUser" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="resetDefaults">
                  Restablecer
                </button>
                <button
                  class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50"
                  :disabled="saving"
                  @click="saveLayout"
                >
                  {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                </button>
              </div>
            </div>

            <p class="text-sm text-gray-400">{{ isAdminUser ? 'Activa/desactiva cards y elige su posicion. Los cambios se reflejan en el dashboard para este usuario.' : 'Actualiza el mensaje de bienvenida del dashboard.' }}</p>

            <div class="rounded-xl border border-gray-800 bg-gray-950 p-4 space-y-3">
              <h3 class="text-sm font-semibold text-white">Mensaje de bienvenida</h3>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Titulo</label>
                <input v-model="welcomeSettings.title" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" maxlength="80" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Descripcion</label>
                <textarea v-model="welcomeSettings.subtitle" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white min-h-20" maxlength="180" />
              </div>
              <label class="inline-flex items-center gap-2 text-sm text-gray-200">
                <input v-model="welcomeSettings.show_date" type="checkbox" class="accent-blue-600" />
                Mostrar fecha en bienvenida
              </label>
            </div>

            <div v-if="loading" class="text-gray-400">Cargando configuracion...</div>

            <div v-else-if="isAdminUser" class="space-y-3">
              <div
                v-for="w in widgets"
                :key="w.key"
                class="rounded-xl border border-gray-800 bg-gray-950 p-3 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_180px] gap-3 items-start lg:items-center"
              >
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 min-w-0">
                  <label class="inline-flex items-center gap-2 text-gray-200">
                    <input v-model="visibility[w.key]" type="checkbox" class="accent-blue-600" />
                    <span class="font-medium">{{ w.name }}</span>
                  </label>
                  <span class="text-xs text-gray-500 break-all">{{ w.key }}</span>
                </div>

                <div>
                  <label class="block text-xs text-gray-400 mb-1">Posicion</label>
                  <select
                    :value="widgetSlot(w.key)"
                    class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
                    :disabled="!visibility[w.key]"
                    @change="onSlotChange(w.key, $event)"
                  >
                    <option :value="-1">Sin asignar</option>
                    <option v-for="i in 9" :key="i" :value="i - 1">Slot {{ i }}</option>
                  </select>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'permissions'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <h2 class="text-white font-semibold">Permisos por Equipo</h2>

            <div v-if="permissionsLoading" class="text-sm text-gray-400">Cargando permisos...</div>

            <div v-else-if="!canManagePermissions" class="text-sm text-gray-400">No tienes permisos para gestionar esta seccion.</div>

            <div v-else class="space-y-3">
              <p class="text-sm text-gray-400">Asigna permisos a cada equipo. Los cambios se aplican a todos los usuarios del equipo.</p>

              <div v-if="!permissionTeams.length" class="text-sm text-gray-500">No hay equipos registrados.</div>

              <template v-else>
                <div v-for="team in permissionTeams" :key="team.id" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
                  <!-- Accordion header -->
                  <button
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-800/40 transition"
                    @click="toggleTeamExpanded(team.id)"
                  >
                    <div class="flex items-center gap-3">
                      <span class="font-medium text-white">{{ team.name }}</span>
                      <span class="text-xs px-2 py-0.5 rounded-full bg-blue-600/20 text-blue-400 font-medium">
                        {{ (localTeamPermissions[team.id] || []).length }} / {{ permissionsCatalog.length }}
                      </span>
                    </div>
                    <svg
                      class="w-4 h-4 text-gray-400 transition-transform duration-200"
                      :class="expandedTeams.includes(team.id) ? 'rotate-180' : ''"
                      fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  <!-- Accordion body -->
                  <div v-if="expandedTeams.includes(team.id)" class="border-t border-gray-800 px-5 py-4 space-y-5">
                    <div v-for="group in permissionGroups" :key="group.prefix" class="space-y-2">
                      <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 pb-1 border-b border-gray-800">{{ group.label }}</h4>
                      <div class="space-y-1.5">
                        <div
                          v-for="p in group.permissions"
                          :key="p.code"
                          class="flex items-center justify-between gap-4 rounded-lg bg-gray-900 px-4 py-3"
                        >
                          <div class="min-w-0">
                            <div class="text-sm text-gray-200 font-medium">{{ p.name }}</div>
                            <div v-if="p.description" class="text-xs text-gray-500 mt-0.5">{{ p.description }}</div>
                          </div>
                          <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input
                              type="checkbox"
                              :checked="localTeamPermissions[team.id]?.includes(p.code)"
                              class="sr-only peer"
                              @change="toggleTeamPermission(team.id, p.code, $event)"
                            />
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <div class="flex justify-end pt-2">
                <button
                  class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50"
                  :disabled="permissionSaving || !canManagePermissions"
                  @click="saveAllPermissions"
                >
                  {{ permissionSaving ? 'Guardando...' : 'Guardar cambios' }}
                </button>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'schedules'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">Jornadas laborales</h2>
              <div class="flex items-center gap-2">
                <button
                  v-if="editingScheduleId"
                  class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800"
                  @click="cancelEditScheduleTemplate"
                >
                  Cancelar edicion
                </button>
                <button
                  class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50"
                  :disabled="scheduleSaving"
                  @click="saveScheduleTemplate"
                >
                  {{ scheduleSaving ? 'Guardando...' : (editingScheduleId ? 'Guardar cambios' : 'Agregar jornada') }}
                </button>
              </div>
            </div>

            <p class="text-sm text-gray-400">Define horarios y dias laborales en la tabla schedules para reutilizarlos al crear usuarios.</p>

            <div class="rounded-xl border border-gray-800 bg-gray-950 p-4 space-y-3">
              <h3 class="text-sm text-white font-semibold">Nueva jornada</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Hora de entrada</label>
                  <input v-model="scheduleForm.start_time" type="time" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" />
                </div>
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Hora de salida</label>
                  <input v-model="scheduleForm.end_time" type="time" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" />
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <label v-for="d in dayOptions" :key="`cfg-day-${d}`" class="inline-flex items-center gap-2 text-xs text-gray-300 bg-gray-800 px-2 py-1 rounded">
                  <input type="checkbox" :value="d" v-model="scheduleForm.days" class="accent-blue-500" />
                  <span>{{ d }}</span>
                </label>
              </div>
            </div>

            <div class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
              <div class="px-4 py-3 border-b border-gray-800 text-sm text-gray-200">Jornadas existentes</div>
              <div v-if="schedulesLoading" class="px-4 py-4 text-sm text-gray-400">Cargando jornadas...</div>
              <div v-else-if="!scheduleTemplates.length" class="px-4 py-4 text-sm text-gray-500">No hay jornadas cargadas.</div>
              <table v-else class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                  <tr>
                    <th class="text-left px-4 py-2">Horario</th>
                    <th class="text-left px-4 py-2">Dias</th>
                    <th class="text-right px-4 py-2">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in scheduleTemplates" :key="s.id" class="border-t border-gray-800 text-gray-200">
                    <td class="px-4 py-2">{{ s.start_time }} - {{ s.end_time }}</td>
                    <td class="px-4 py-2">{{ (s.days || []).join(', ') }}</td>
                    <td class="px-4 py-2 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button
                          class="px-2 py-1 rounded border border-gray-700 text-xs text-gray-200 hover:bg-gray-800"
                          @click="editScheduleTemplate(s)"
                        >
                          Editar
                        </button>
                        <button
                          class="px-2 py-1 rounded border border-red-700 text-xs text-red-300 hover:bg-red-900/20 disabled:opacity-50"
                          :disabled="scheduleDeletingId === s.id"
                          @click="deleteScheduleTemplate(s.id)"
                        >
                          {{ scheduleDeletingId === s.id ? 'Eliminando...' : 'Eliminar' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>


        </div>
      </main>
    </div>

    <AppToast :show="toast.show" :message="toast.message" :type="toast.type" @close="toast.show = false" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppToast from '../components/AppToast.vue'

definePageMeta({ auth: true })
declare const process: any
declare const $fetch: any

const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const { token, fetchUser, logout, apiBase, setToken, user } = useAuth()

const isAdminUser = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_admin) || Number(currentUser?.user_type_id || 0) === 1
})

const isHrTeamUser = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_hr_team)
})

const tabs = computed(() => {
  if (isAdminUser.value) {
    return [
      { id: 'dashboard', label: 'Dashboard' },
      { id: 'permissions', label: 'Permisos' },
      { id: 'schedules', label: 'Jornadas laborales' },
    ]
  }

  if (isHrTeamUser.value) {
    return [
      { id: 'dashboard', label: 'Bienvenida' },
      { id: 'schedules', label: 'Jornadas laborales' },
    ]
  }

  return []
})

const activeTab = ref('dashboard')
const loading = ref(true)
const saving = ref(false)
const toast = ref<{ show: boolean; type: 'success' | 'error'; message: string }>({
  show: false,
  type: 'success',
  message: '',
})
let toastTimer: any = null

const widgets = ref<Array<{ key: string; name: string }>>([])
const slots = ref<Array<string | null>>([null, null, null, null, null, null, null, null, null])
const visibility = ref<Record<string, boolean>>({})
const welcomeSettings = ref<{ title: string; subtitle: string; show_date: boolean }>({
  title: 'Bienvenido',
  subtitle: 'Aqui tenes un resumen de la actividad del sistema.',
  show_date: true,
})
const canManagePermissions = ref(false)
const permissionsLoading = ref(false)
const permissionSaving = ref(false)
const permissionsCatalog = ref<Array<{ id: number; code: string; name: string; description: string | null }>>([])
const permissionTeams = ref<Array<{ id: number; name: string; permission_codes: string[] }>>([])
const selectedPermissionTeamId = ref<number | null>(null)
const selectedPermissionCodes = ref<string[]>([])
const searchQuery = ref('')
const localTeamPermissions = ref<Record<number, string[]>>({})
const expandedTeams = ref<number[]>([])

const GROUP_LABELS: Record<string, string> = {
  employees: 'Usuarios',
  requests: 'Solicitudes',
}

const permissionGroups = computed(() => {
  const groups: Record<string, { prefix: string; label: string; permissions: typeof permissionsCatalog.value }> = {}
  for (const p of permissionsCatalog.value) {
    const prefix = p.code.split('.')[0]
    if (!groups[prefix]) {
      groups[prefix] = {
        prefix,
        label: GROUP_LABELS[prefix] ?? prefix,
        permissions: [],
      }
    }
    groups[prefix].permissions.push(p)
  }
  return Object.values(groups)
})

function toggleTeamExpanded(teamId: number) {
  const idx = expandedTeams.value.indexOf(teamId)
  if (idx >= 0) expandedTeams.value.splice(idx, 1)
  else expandedTeams.value.push(teamId)
}

const dayOptions = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const schedulesLoading = ref(false)
const scheduleSaving = ref(false)
const scheduleDeletingId = ref<number | null>(null)
const editingScheduleId = ref<number | null>(null)
const scheduleTemplates = ref<Array<{ id: number; start_time: string; end_time: string; days: string[]; label: string }>>([])
const scheduleForm = ref<{ start_time: string; end_time: string; days: string[] }>({
  start_time: '09:00',
  end_time: '18:00',
  days: ['L', 'M', 'X', 'J', 'V'],
})

function showToast(type: 'success' | 'error', message: string) {
  toast.value = { show: true, type, message }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 2800)
}

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

function widgetSlot(key: string): number {
  return slots.value.findIndex((k) => k === key)
}

function onSlotChange(key: string, e: Event) {
  const target = e.target as HTMLSelectElement
  const next = Number(target.value)

  const current = widgetSlot(key)
  if (current >= 0) slots.value[current] = null
  if (next >= 0) {
    const occupying = slots.value[next]
    if (occupying && occupying !== key) {
      slots.value[next] = key
      if (current >= 0) {
        slots.value[current] = occupying
      }
      return
    }
    slots.value[next] = key
  }
}

function resetDefaults() {
  if (!isAdminUser.value) return
  slots.value = [null, null, null, null, null, null, null, null, null]
  const order = ['welcome', 'birthdays', 'team', 'vacation', 'notifications', 'worked_hours', 'holidays', 'announcements', 'documents']
  order.forEach((k, idx) => {
    slots.value[idx] = k
    visibility.value[k] = true
  })
  welcomeSettings.value = {
    title: 'Bienvenido',
    subtitle: 'Aqui tenes un resumen de la actividad del sistema.',
    show_date: true,
  }
}

async function loadLayout() {
  if (!token.value) return
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    widgets.value = (res?.widgets || []).map((w: any) => ({ key: String(w.key), name: String(w.name) }))
    slots.value = Array.isArray(res?.slots) ? res.slots.slice(0, 9) : [null, null, null, null, null, null, null, null, null]
    while (slots.value.length < 9) slots.value.push(null)

    const vis: Record<string, boolean> = {}
    for (const w of widgets.value) {
      vis[w.key] = !!(res?.visibility?.[w.key] ?? true)
    }
    visibility.value = vis

    const ws = res?.settings?.welcome || {}
    welcomeSettings.value = {
      title: typeof ws?.title === 'string' && ws.title.trim() ? ws.title.trim() : 'Bienvenido',
      subtitle: typeof ws?.subtitle === 'string' && ws.subtitle.trim() ? ws.subtitle.trim() : 'Aqui tenes un resumen de la actividad del sistema.',
      show_date: ws?.show_date !== false,
    }
  } catch (e) {
    console.error('layout load failed', e)
  } finally {
    loading.value = false
  }
}

async function saveLayout() {
  if (!token.value) return
  saving.value = true
  try {
    const normalizedSlots = slots.value.map((k) => {
      if (!k) return null
      return visibility.value[k] ? k : null
    })

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        slots: normalizedSlots,
        visibility: visibility.value,
        settings: {
          welcome: welcomeSettings.value,
        },
      },
    })
    showToast('success', 'Configuracion guardada correctamente')
  } catch (e) {
    console.error('layout save failed', e)
    showToast('error', 'No se pudo guardar la configuracion')
  } finally {
    saving.value = false
  }
}

const selectedPermissionTeamName = computed(() => {
  const t = permissionTeams.value.find((x) => x.id === selectedPermissionTeamId.value)
  return t?.name || ''
})

function selectPermissionTeam(id: number) {
  selectedPermissionTeamId.value = id
  const t = permissionTeams.value.find((x) => x.id === id)
  selectedPermissionCodes.value = Array.isArray(t?.permission_codes) ? [...t!.permission_codes] : []
}

function togglePermissionCode(code: string, e: Event) {
  const checked = (e.target as HTMLInputElement).checked
  const set = new Set(selectedPermissionCodes.value)
  if (checked) set.add(code)
  else set.delete(code)
  selectedPermissionCodes.value = Array.from(set.values())
}

async function loadPermissions() {
  if (!isAdminUser.value) {
    canManagePermissions.value = false
    permissionsCatalog.value = []
    permissionTeams.value = []
    return
  }
  if (!token.value) return
  permissionsLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/permissions/catalog`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    canManagePermissions.value = true
    permissionsCatalog.value = Array.isArray(res?.permissions) ? res.permissions : []
    permissionTeams.value = Array.isArray(res?.teams) ? res.teams : []
    const map: Record<number, string[]> = {}
    for (const t of permissionTeams.value) {
      map[t.id] = Array.isArray(t.permission_codes) ? [...t.permission_codes] : []
    }
    localTeamPermissions.value = map
    if (!selectedPermissionTeamId.value && permissionTeams.value.length) {
      selectPermissionTeam(permissionTeams.value[0].id)
    }
  } catch (e: any) {
    if (e?.status === 403 || e?.data?.message === 'Forbidden') {
      canManagePermissions.value = false
    }
    permissionsCatalog.value = []
    permissionTeams.value = []
  } finally {
    permissionsLoading.value = false
  }
}

function toggleTeamPermission(teamId: number, code: string, e: Event) {
  const checked = (e.target as HTMLInputElement).checked
  const current = localTeamPermissions.value[teamId] ? [...localTeamPermissions.value[teamId]] : []
  const set = new Set(current)
  if (checked) set.add(code)
  else set.delete(code)
  localTeamPermissions.value[teamId] = Array.from(set)
}

async function saveAllPermissions() {
  if (!token.value || !canManagePermissions.value) return
  permissionSaving.value = true
  try {
    for (const team of permissionTeams.value) {
      const codes = localTeamPermissions.value[team.id] || []
      await $fetch(`${apiBase || 'http://localhost:8000'}/api/permissions/teams/${team.id}`, {
        method: 'PUT',
        headers: { Authorization: `Bearer ${token.value}` },
        body: { permission_codes: codes },
      })
      team.permission_codes = [...codes]
    }
    showToast('success', 'Permisos actualizados')
  } catch (e) {
    showToast('error', 'No se pudieron guardar los permisos')
  } finally {
    permissionSaving.value = false
  }
}

async function loadScheduleTemplates() {
  if (!token.value) return
  schedulesLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/schedules`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    scheduleTemplates.value = Array.isArray(res?.schedules) ? res.schedules : []
  } catch (e: any) {
    if (!(e?.status === 403 || e?.data?.message === 'Forbidden')) {
      showToast('error', 'No se pudieron cargar las jornadas')
    }
    scheduleTemplates.value = []
  } finally {
    schedulesLoading.value = false
  }
}

async function saveScheduleTemplate() {
  if (!token.value) return
  scheduleSaving.value = true
  try {
    const method = editingScheduleId.value ? 'PUT' : 'POST'
    const endpoint = editingScheduleId.value
      ? `${apiBase || 'http://localhost:8000'}/api/settings/schedules/${editingScheduleId.value}`
      : `${apiBase || 'http://localhost:8000'}/api/settings/schedules`

    await $fetch(endpoint, {
      method,
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        start_time: scheduleForm.value.start_time,
        end_time: scheduleForm.value.end_time,
        days: scheduleForm.value.days,
      },
    })
    showToast('success', editingScheduleId.value ? 'Jornada actualizada' : 'Jornada guardada')
    editingScheduleId.value = null
    scheduleForm.value = { start_time: '09:00', end_time: '18:00', days: ['L', 'M', 'X', 'J', 'V'] }
    await loadScheduleTemplates()
  } catch (e: any) {
    showToast('error', e?.data?.message || 'No se pudo guardar la jornada')
  } finally {
    scheduleSaving.value = false
  }
}

function editScheduleTemplate(schedule: { id: number; start_time: string; end_time: string; days: string[] }) {
  editingScheduleId.value = Number(schedule.id)
  scheduleForm.value = {
    start_time: schedule.start_time || '09:00',
    end_time: schedule.end_time || '18:00',
    days: Array.isArray(schedule.days) && schedule.days.length ? [...schedule.days] : ['L', 'M', 'X', 'J', 'V'],
  }
}

function cancelEditScheduleTemplate() {
  editingScheduleId.value = null
  scheduleForm.value = { start_time: '09:00', end_time: '18:00', days: ['L', 'M', 'X', 'J', 'V'] }
}

async function deleteScheduleTemplate(id: number) {
  if (!token.value) return
  const ok = confirm('¿Eliminar esta jornada laboral?')
  if (!ok) return

  scheduleDeletingId.value = id
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/schedules/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (editingScheduleId.value === id) {
      cancelEditScheduleTemplate()
    }
    showToast('success', 'Jornada eliminada')
    await loadScheduleTemplates()
  } catch (e: any) {
    showToast('error', e?.data?.message || 'No se pudo eliminar la jornada')
  } finally {
    scheduleDeletingId.value = null
  }
}

watch(activeTab, async (tab) => {
  if (tab === 'schedules' && !scheduleTemplates.value.length && !schedulesLoading.value) {
    await loadScheduleTemplates()
  }
})

watch(tabs, (nextTabs) => {
  const ids = nextTabs.map((t) => t.id)
  if (!ids.includes(activeTab.value)) {
    activeTab.value = ids[0] || 'dashboard'
  }
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

  const canAccessSettings = Boolean((user.value as any)?.can_access_settings) || Boolean((user.value as any)?.is_admin) || Boolean((user.value as any)?.is_hr_team) || Number((user.value as any)?.user_type_id || 0) === 1
  if (!canAccessSettings) {
    return router.push('/dashboard')
  }

  await loadLayout()
  await loadPermissions()
  await loadScheduleTemplates()
})

onBeforeUnmount(() => {
  if (toastTimer) clearTimeout(toastTimer)
})
</script>
