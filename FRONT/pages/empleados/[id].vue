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

        <div>
          <h1 class="text-base font-semibold text-white">Empleado</h1>
          <p class="text-[11px] text-gray-400">{{ form.name || 'Cargando...' }}</p>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="flex-1 p-3 sm:p-4 lg:p-6 overflow-auto">
        <div class="max-w-6xl mx-auto space-y-4">
          <div class="flex items-center gap-2">
            <button @click="router.push('/empleados')" class="px-3 py-1.5 rounded-lg border border-gray-700 text-xs text-gray-300 hover:bg-gray-800">
              Volver
            </button>
          </div>

          <template v-if="loadingEmployee">
            <div class="rounded-2xl border border-gray-800 bg-gray-900 p-8 text-center text-gray-300">Cargando empleado...</div>
          </template>

          <template v-else-if="error">
            <div class="rounded-2xl border border-red-800 bg-red-900/20 p-8 text-center text-red-300">{{ error }}</div>
          </template>

          <template v-else>
            <div class="max-w-full overflow-x-auto">
              <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900 w-max">
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
            </div>

            <section v-if="activeTab === 'fichajes'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-white font-semibold">Fichajes mensuales</h2>
                <div class="flex items-center gap-2 flex-wrap">
                  <button @click="changeMonth(-1)" class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">&#8249;</button>
                  <div class="h-9 min-w-[140px] sm:min-w-[180px] px-3 rounded-lg border border-gray-700 bg-gray-800 text-sm text-white flex items-center justify-center">{{ monthLabel }}</div>
                  <button @click="changeMonth(1)" class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">&#8250;</button>
                </div>
              </div>

              <div class="overflow-x-auto rounded-xl border border-gray-800">
                <table class="min-w-[760px] sm:min-w-full text-xs sm:text-sm">
                  <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                      <th class="text-left px-3 py-2">Fecha</th>
                      <th class="text-left px-3 py-2">Entrada</th>
                      <th class="text-left px-3 py-2">Pausa</th>
                      <th class="text-left px-3 py-2">Reanuda</th>
                      <th class="text-left px-3 py-2">Salida</th>
                      <th class="text-left px-3 py-2">Horas</th>
                      <th class="text-left px-3 py-2">Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in attendanceRows" :key="r.date" class="border-t border-gray-800 text-gray-200">
                      <td class="px-3 py-2">{{ formatDate(r.date) }}</td>
                      <td class="px-3 py-2">{{ r.start_time || '--:--' }}</td>
                      <td class="px-3 py-2">{{ r.pause_time || '--:--' }}</td>
                      <td class="px-3 py-2">{{ r.resume_time || '--:--' }}</td>
                      <td class="px-3 py-2">{{ r.end_time || '--:--' }}</td>
                      <td class="px-3 py-2 font-semibold">{{ r.hours_worked || '--:--' }}</td>
                      <td class="px-3 py-2">
                        <span class="text-xs px-2 py-1 rounded-full border" :class="r.status === 'en_trabajo' ? 'border-emerald-500/50 text-emerald-300 bg-emerald-500/10' : 'border-gray-700 text-gray-300 bg-gray-800'">
                          {{ r.status || '-' }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="!attendanceRows.length">
                      <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay fichajes en este mes.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section v-else-if="activeTab === 'estadisticas'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
              <h2 class="text-white font-semibold mb-4">Estadisticas del mes</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Horas trabajadas</div>
                  <div class="text-2xl font-bold text-white tabular-nums">{{ attendanceSummary.worked_hhmm || '00:00' }}</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Objetivo mensual</div>
                  <div class="text-2xl font-bold text-white tabular-nums">{{ attendanceSummary.target_hhmm || '00:00' }}</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Cumplimiento</div>
                  <div class="text-2xl font-bold text-white">{{ attendanceSummary.compliance_percent || 0 }}%</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Dias con fichaje</div>
                  <div class="text-2xl font-bold text-white">{{ attendanceSummary.worked_days || 0 }} / {{ attendanceSummary.working_days || 0 }}</div>
                  <div class="text-xs text-gray-500 mt-1">Faltantes: {{ attendanceSummary.missing_days || 0 }}</div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mt-3">
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Solicitudes</div>
                  <div class="text-2xl font-bold text-white">{{ attendanceSummary.requests_total || 0 }}</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Pendientes</div>
                  <div class="text-2xl font-bold text-amber-300">{{ attendanceSummary.requests_pending || 0 }}</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Aprobadas</div>
                  <div class="text-2xl font-bold text-emerald-300">{{ attendanceSummary.requests_approved || 0 }}</div>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                  <div class="text-xs text-gray-400">Rechazadas</div>
                  <div class="text-2xl font-bold text-red-300">{{ attendanceSummary.requests_rejected || 0 }}</div>
                </div>
              </div>

              <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
                <div class="rounded-xl border border-gray-800 bg-gray-800/40 p-4">
                  <div class="text-sm text-gray-200 mb-3">Horas por dia (grafico de barras)</div>
                  <div class="space-y-2">
                    <div v-for="b in dailyBars" :key="b.date" class="grid grid-cols-[74px_1fr_48px] items-center gap-2">
                      <div class="text-[11px] text-gray-400">{{ b.label }}</div>
                      <div class="h-2 rounded-full bg-gray-700 overflow-hidden">
                        <div class="h-2 rounded-full bg-cyan-400" :style="{ width: `${b.percent}%` }"></div>
                      </div>
                      <div class="text-[11px] text-gray-300 tabular-nums text-right">{{ b.hhmm }}</div>
                    </div>
                    <div v-if="!dailyBars.length" class="text-xs text-gray-500">Sin datos para graficar.</div>
                  </div>
                </div>

                <div class="rounded-xl border border-gray-800 bg-gray-800/40 p-4">
                  <div class="text-sm text-gray-200 mb-3">Distribucion del mes</div>
                  <div class="space-y-3">
                    <div>
                      <div class="flex justify-between text-xs text-gray-400 mb-1">
                        <span>Trabajado vs Objetivo</span>
                        <span>{{ attendanceSummary.compliance_percent || 0 }}%</span>
                      </div>
                      <div class="h-3 rounded-full bg-gray-700 overflow-hidden">
                        <div class="h-3 rounded-full bg-emerald-400" :style="{ width: `${workedVsTargetPercent}%` }"></div>
                      </div>
                    </div>
                    <div>
                      <div class="flex justify-between text-xs text-gray-400 mb-1">
                        <span>Dias fichados</span>
                        <span>{{ workedDaysPercent }}%</span>
                      </div>
                      <div class="h-3 rounded-full bg-gray-700 overflow-hidden">
                        <div class="h-3 rounded-full bg-blue-400" :style="{ width: `${workedDaysPercent}%` }"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section v-else-if="activeTab === 'perfil'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
              <h2 class="text-white font-semibold mb-4">Perfil del empleado</h2>
              <form class="space-y-5" @submit.prevent="saveProfile">
                <div class="flex items-start gap-6">
                  <div class="flex-shrink-0">
                    <div class="w-24 h-24 rounded-full bg-gray-700 overflow-hidden">
                      <img v-if="preview" :src="preview" class="w-full h-full object-cover" />
                      <span v-else class="flex items-center justify-center h-full text-gray-400 text-2xl">{{ (form.name || '?').charAt(0).toUpperCase() }}</span>
                    </div>
                    <label class="block text-xs text-gray-300 mt-2 text-center" for="photo">Cambiar foto</label>
                    <input id="photo" type="file" class="hidden" @change="onPhotoChange" />
                  </div>

                  <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Nombre</label>
                      <input v-model="form.name" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Email</label>
                      <input v-model="form.email" type="email" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Telefono</label>
                      <input v-model="form.phone" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Equipo</label>
                      <select v-model="form.team_id" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                        <option :value="null">Sin equipo</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Fecha de ingreso</label>
                      <input v-model="form.hire_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Fecha de cumpleanos</label>
                      <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">DNI</label>
                      <input v-model="form.dni" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Nro seguridad social</label>
                      <input v-model="form.social_security_number" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Tipo de contrato</label>
                      <select v-model="form.contract_type" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                        <option value="">Sin definir</option>
                        <option value="indefinido">Indefinido</option>
                        <option value="temporal">Temporal</option>
                        <option value="practicas">Practicas</option>
                        <option value="autonomo">Autonomo</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Inicio de contrato</label>
                      <input v-model="form.contract_start_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Dias de vacaciones</label>
                      <input v-model.number="form.vacation_days_total" type="number" min="0" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-800">
                  <h3 class="text-sm text-gray-300 mb-2">Cambiar contrasena</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input v-model="form.password" type="password" placeholder="Nueva contrasena" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    <input v-model="form.password_confirmation" type="password" placeholder="Confirmar contrasena" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-800">
                  <h3 class="text-sm text-gray-300 mb-2">Horario</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input v-model="form.start_time" type="time" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    <input v-model="form.end_time" type="time" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                  </div>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <label v-for="d in dayOptions" :key="d" class="inline-flex items-center gap-2 text-xs text-gray-300 bg-gray-800 px-2 py-1 rounded">
                      <input type="checkbox" :value="d" v-model="form.days" class="accent-blue-500" />
                      <span>{{ d }}</span>
                    </label>
                  </div>
                </div>

                <div class="flex justify-end">
                  <button :disabled="savingProfile" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-50">
                    {{ savingProfile ? 'Guardando...' : 'Guardar cambios' }}
                  </button>
                </div>
              </form>
            </section>

            <section v-else class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
              <h2 class="text-white font-semibold mb-4">Documentos del empleado</h2>
              <div class="overflow-x-auto rounded-xl border border-gray-800">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                      <th class="text-left px-3 py-2">Documento</th>
                      <th class="text-left px-3 py-2">Categoria</th>
                      <th class="text-left px-3 py-2">Fecha</th>
                      <th class="text-left px-3 py-2">Tamano</th>
                      <th class="text-right px-3 py-2">Accion</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in employeeDocuments" :key="d.id" class="border-t border-gray-800 text-gray-200">
                      <td class="px-3 py-2">
                        <div class="truncate">{{ d.original_name || d.filename }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ d.description || '-' }}</div>
                      </td>
                      <td class="px-3 py-2 capitalize">{{ d.category || '-' }}</td>
                      <td class="px-3 py-2">{{ formatDate(d.created_at) }}</td>
                      <td class="px-3 py-2">{{ formatSize(d.size) }}</td>
                      <td class="px-3 py-2 text-right">
                        <button class="px-2.5 py-1.5 rounded-lg border border-gray-700 text-xs text-gray-200 hover:bg-gray-800 disabled:opacity-50" :disabled="downloadingDocId === d.id" @click="downloadEmployeeDocument(d)">
                          {{ downloadingDocId === d.id ? 'Bajando...' : 'Descargar' }}
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!employeeDocuments.length">
                      <td colspan="5" class="px-3 py-6 text-center text-gray-500">Sin documentos para este empleado.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>
        </div>
      </main>
    </div>

    <AppToast :show="toast.show" :message="toast.message" :type="toast.type" @close="toast.show = false" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onBeforeMount, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import AppSidebar from '../../components/AppSidebar.vue'
import AttendanceButton from '../../components/AttendanceButton.vue'
import UserMenu from '../../components/UserMenu.vue'
import AppToast from '../../components/AppToast.vue'

declare const process: any
declare const $fetch: any

type TabId = 'fichajes' | 'estadisticas' | 'perfil' | 'documentos'

const tabs: Array<{ id: TabId; label: string }> = [
  { id: 'fichajes', label: 'Fichajes' },
  { id: 'estadisticas', label: 'Estadisticas' },
  { id: 'perfil', label: 'Perfil' },
  { id: 'documentos', label: 'Documentos' },
]

const { token, fetchUser, logout, apiBase, setToken, user } = useAuth()
const router = useRouter()
const route = useRoute()
const sidebar = ref<{ open: boolean } | null>(null)

const activeTab = ref<TabId>('fichajes')
const loadingEmployee = ref(false)
const savingProfile = ref(false)
const error = ref<string | null>(null)
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const attendanceRows = ref<any[]>([])
const attendanceSummary = ref<any>({
  worked_hhmm: '00:00',
  target_hhmm: '00:00',
  compliance_percent: 0,
  worked_days: 0,
  working_days: 0,
  missing_days: 0,
})
const employeeDocuments = ref<any[]>([])
const teams = ref<any[]>([])
const preview = ref<string | null>(null)
const downloadingDocId = ref<number | null>(null)

const form = ref<any>({
  name: '',
  email: '',
  team_id: null,
  phone: '',
  hire_date: '',
  birth_date: '',
  dni: '',
  social_security_number: '',
  contract_type: '',
  contract_start_date: '',
  vacation_days_total: 0,
  password: '',
  password_confirmation: '',
  start_time: '',
  end_time: '',
  days: ['L', 'M', 'X', 'J', 'V'],
  photo: null,
})
const dayOptions = ['L', 'M', 'X', 'J', 'V', 'S', 'D']

const toast = ref<{ show: boolean; type: 'success' | 'error'; message: string }>({ show: false, type: 'success', message: '' })
let toastTimer: any = null

const employeeId = computed(() => Number(route.params.id))
const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1, 1)
  return d.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})
const workedVsTargetPercent = computed(() => {
  const worked = Number(attendanceSummary.value?.worked_minutes || 0)
  const target = Number(attendanceSummary.value?.target_minutes || 0)
  if (target <= 0) return 0
  return Math.max(0, Math.min(100, Math.round((worked / target) * 100)))
})
const workedDaysPercent = computed(() => {
  const workedDays = Number(attendanceSummary.value?.worked_days || 0)
  const totalDays = Number(attendanceSummary.value?.working_days || 0)
  if (totalDays <= 0) return 0
  return Math.max(0, Math.min(100, Math.round((workedDays / totalDays) * 100)))
})
const canViewEmployeeDetails = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.is_admin || Number(currentUser?.user_type_id || 0) === 1) return true
  if (currentUser?.can_view_employee_details) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.view_details')
})
const dailyBars = computed(() => {
  const rows = Array.isArray(attendanceRows.value) ? attendanceRows.value : []
  const withHours = rows
    .map((r: any) => {
      const h = parseHHMM(r?.hours_worked)
      return {
        date: String(r?.date || ''),
        minutes: h,
        hhmm: h > 0 ? minutesToHHMM(h) : '--:--',
        label: r?.date ? formatDate(r.date).slice(0, 5) : '--/--',
      }
    })
    .filter((x: any) => x.minutes > 0)

  const max = withHours.reduce((acc: number, row: any) => Math.max(acc, row.minutes), 0) || 1
  return withHours.slice(0, 12).map((row: any) => ({
    ...row,
    percent: Math.max(6, Math.round((row.minutes / max) * 100)),
  }))
})

function showToast(type: 'success' | 'error', message: string) {
  toast.value = { show: true, type, message }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toast.value.show = false), 2800)
}

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

function changeMonth(delta: number) {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1 + delta, 1)
  const yy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  selectedMonth.value = `${yy}-${mm}`
}

function formatDate(dt?: string) {
  if (!dt) return '-'
  try {
    return new Date(dt).toLocaleDateString('es-AR')
  } catch {
    return dt
  }
}

function formatSize(size?: number) {
  const s = Number(size || 0)
  if (s < 1024) return `${s} B`
  if (s < 1024 * 1024) return `${(s / 1024).toFixed(1)} KB`
  return `${(s / (1024 * 1024)).toFixed(1)} MB`
}

function parseHHMM(v?: string | null): number {
  if (!v) return 0
  const parts = String(v).split(':').map((x) => Number(x))
  if (parts.length < 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) return 0
  return (parts[0] * 60) + parts[1]
}

function minutesToHHMM(minutes: number): string {
  const m = Math.max(0, Number(minutes || 0))
  const h = Math.floor(m / 60)
  const mm = m % 60
  return `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
}

async function loadEmployee() {
  error.value = null
  loadingEmployee.value = true
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId.value}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    const employee = res?.employee || {}
    form.value.name = employee.name || ''
    form.value.email = employee.email || ''
    form.value.team_id = employee.team_id ?? null
    form.value.phone = employee.phone || ''
    form.value.hire_date = employee.hire_date || ''
    form.value.birth_date = employee.birth_date || ''
    form.value.dni = employee.dni || ''
    form.value.social_security_number = employee.social_security_number || ''
    form.value.contract_type = employee.contract_type || ''
    form.value.contract_start_date = employee.contract_start_date || ''
    form.value.vacation_days_total = Number(employee.vacation_days_total || 0)
    preview.value = employee.photo || employee.profile_photo_path || null

    const schedule = res?.schedule || null
    form.value.start_time = schedule?.start_time || ''
    form.value.end_time = schedule?.end_time || ''
    form.value.days = Array.isArray(schedule?.days) && schedule.days.length ? schedule.days : ['L', 'M', 'X', 'J', 'V']

    teams.value = res?.teams || []
  } catch (e: any) {
    console.error('employee load failed', e)
    error.value = e?.data?.message || 'No se pudo cargar el empleado'
  } finally {
    loadingEmployee.value = false
  }
}

async function loadAttendance() {
  if (!token.value || !employeeId.value) return
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId.value}/attendance-month?month=${selectedMonth.value}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    attendanceRows.value = res?.rows || []
    attendanceSummary.value = res?.summary || attendanceSummary.value
  } catch (e) {
    console.error('employee attendance load failed', e)
    attendanceRows.value = []
  }
}

async function loadDocuments() {
  if (!token.value || !employeeId.value) return
  try {
    const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId.value}/documents`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    employeeDocuments.value = res?.documents || []
  } catch (e) {
    console.error('employee documents load failed', e)
    employeeDocuments.value = []
  }
}

async function saveProfile() {
  if (!token.value || !employeeId.value) return
  savingProfile.value = true
  try {
    const data = new FormData()
    data.append('_method', 'PUT')
    data.append('name', form.value.name || '')
    data.append('email', form.value.email || '')
    if (form.value.team_id !== null && form.value.team_id !== '') data.append('team_id', String(form.value.team_id))
    data.append('phone', form.value.phone || '')
    data.append('hire_date', form.value.hire_date || '')
    data.append('birth_date', form.value.birth_date || '')
    data.append('dni', form.value.dni || '')
    data.append('social_security_number', form.value.social_security_number || '')
    data.append('contract_type', form.value.contract_type || '')
    data.append('contract_start_date', form.value.contract_start_date || '')
    data.append('vacation_days_total', String(Number(form.value.vacation_days_total || 0)))
    if (form.value.password) {
      data.append('password', form.value.password)
      data.append('password_confirmation', form.value.password_confirmation || '')
    }
    if (form.value.start_time) data.append('start_time', form.value.start_time)
    if (form.value.end_time) data.append('end_time', form.value.end_time)
    for (const d of form.value.days || []) data.append('days[]', d)
    if (form.value.photo) data.append('photo', form.value.photo)

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId.value}`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: data,
    })

    form.value.password = ''
    form.value.password_confirmation = ''
    showToast('success', 'Empleado actualizado correctamente')
    await loadEmployee()
  } catch (e: any) {
    console.error('employee save failed', e)
    showToast('error', e?.data?.message || 'No se pudo guardar el empleado')
  } finally {
    savingProfile.value = false
  }
}

async function downloadEmployeeDocument(d: any) {
  if (!token.value || !employeeId.value || !d?.id) return
  downloadingDocId.value = Number(d.id)
  try {
    const res = await fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employeeId.value}/documents/${d.id}/download`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (!res.ok) throw new Error('download_failed')

    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = d.original_name || `documento_${d.id}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    showToast('success', 'Descarga iniciada')
  } catch (e) {
    console.error('employee doc download failed', e)
    showToast('error', 'No se pudo descargar el documento')
  } finally {
    downloadingDocId.value = null
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

watch(selectedMonth, () => {
  if (activeTab.value === 'fichajes' || activeTab.value === 'estadisticas') {
    loadAttendance()
  }
})

watch(activeTab, (tab) => {
  if (tab === 'documentos') {
    loadDocuments()
  }
  if (tab === 'fichajes' || tab === 'estadisticas') {
    loadAttendance()
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
  if (!canViewEmployeeDetails.value) {
    return router.push('/dashboard')
  }

  await loadEmployee()
  await loadAttendance()
  await loadDocuments()
})

onBeforeUnmount(() => {
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

