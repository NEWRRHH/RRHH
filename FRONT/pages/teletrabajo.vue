<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />
    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          aria-label="Abrir menú"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Teletrabajo</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-4 sm:p-6 overflow-auto">
        <div class="max-w-6xl mx-auto space-y-4">
          <div
            v-if="!loading && !canRequest"
            class="rounded-xl border border-amber-600/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"
          >
            {{ blockReason || 'No puedes solicitar teletrabajo.' }}
          </div>

          <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <!-- calendar -->
            <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
              <div class="flex items-center justify-between gap-2 mb-3">
                <button class="w-8 h-8 rounded-lg text-gray-300 hover:bg-gray-800" aria-label="Mes anterior" @click="changeMonth(-1)">&#60;</button>
                <h2 class="text-white font-semibold">{{ monthNames[month] }} {{ year }}</h2>
                <button class="w-8 h-8 rounded-lg text-gray-300 hover:bg-gray-800" aria-label="Mes siguiente" @click="changeMonth(1)">&#62;</button>
              </div>

              <div class="grid grid-cols-7 text-xs text-gray-500 mb-1">
                <div v-for="d in weekHeaders" :key="d" class="text-center py-1">{{ d }}</div>
              </div>
              <div v-if="loading" class="grid grid-cols-7 gap-1">
                <div v-for="i in 35" :key="i" class="h-14 sm:h-16 rounded-lg bg-gray-800 animate-pulse"></div>
              </div>
              <div v-else class="grid grid-cols-7 gap-1">
                <button
                  v-for="cell in cells"
                  :key="cell.iso"
                  type="button"
                  class="h-14 sm:h-16 rounded-lg p-1.5 text-left relative border transition"
                  :class="cellClass(cell)"
                  :disabled="!isSelectable(cell.iso)"
                  :aria-pressed="selected.has(cell.iso)"
                  :aria-label="cellAria(cell)"
                  :title="cellTitle(cell.iso)"
                  @click="toggle(cell.iso)"
                >
                  <span class="text-xs sm:text-sm">{{ cell.day }}</span>
                  <span
                    v-if="requestByDate[cell.iso]"
                    class="absolute left-1 right-1 bottom-1 text-[9px] sm:text-[10px] leading-tight truncate rounded px-1"
                    :class="statusChip(requestByDate[cell.iso].approval_status)"
                  >{{ statusShort(requestByDate[cell.iso].approval_status) }}</span>
                  <span
                    v-else-if="holidays[cell.iso] || blocked[cell.iso]"
                    class="absolute left-1 right-1 bottom-1 text-[9px] sm:text-[10px] leading-tight truncate text-gray-500"
                  >{{ holidays[cell.iso] || blocked[cell.iso] }}</span>
                  <svg
                    v-if="selected.has(cell.iso)"
                    class="absolute top-1 right-1 w-3.5 h-3.5 text-white"
                    fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
                  ><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </button>
              </div>

              <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-sky-600"></span>Seleccionado</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500/40"></span>Pendiente</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-sky-500/40"></span>Aprobado</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-500/40"></span>Rechazado</span>
                <span>Días laborables: {{ scheduleDays.join(', ') || '—' }}</span>
              </div>
            </section>

            <!-- request form -->
            <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4 space-y-3 self-start">
              <h2 class="text-white font-semibold">Nueva solicitud</h2>
              <p class="text-xs text-gray-400">
                Toca los días que quieres teletrabajar. No depende de tus días de vacaciones: RRHH la aprueba en Solicitudes.
              </p>

              <div class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-300 min-h-[44px]">
                <template v-if="selectedList.length">
                  <div class="text-white font-medium mb-1">{{ selectedList.length }} día(s)</div>
                  <div class="flex flex-wrap gap-1">
                    <button
                      v-for="iso in selectedList"
                      :key="iso"
                      type="button"
                      class="text-[11px] px-1.5 py-0.5 rounded bg-sky-600/30 text-sky-100 hover:bg-sky-600/50"
                      :aria-label="`Quitar ${formatIso(iso)}`"
                      @click="toggle(iso)"
                    >{{ formatIso(iso) }} ✕</button>
                  </div>
                </template>
                <span v-else class="text-gray-500">Ningún día seleccionado</span>
              </div>

              <div>
                <label for="remote-description" class="block text-xs text-gray-400 mb-1">Comentario <span class="text-gray-500">(opcional)</span></label>
                <textarea
                  id="remote-description"
                  v-model="description"
                  maxlength="1000"
                  class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm min-h-20"
                  placeholder="Ej: espero una entrega a domicilio"
                />
              </div>

              <div class="flex gap-2">
                <button
                  type="button"
                  class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm disabled:opacity-50"
                  :disabled="!selected.size || saving"
                  @click="selected = new Set()"
                >Limpiar</button>
                <button
                  type="button"
                  class="flex-1 px-3 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium disabled:opacity-50"
                  :disabled="!canRequest || !selected.size || saving"
                  @click="submit"
                >{{ saving ? 'Enviando...' : 'Enviar a RRHH' }}</button>
              </div>
            </section>
          </div>

          <!-- my requests -->
          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
            <h2 class="text-sm font-semibold text-white mb-3">Mis solicitudes de teletrabajo ({{ year }})</h2>
            <div v-if="!requests.length" class="text-sm text-gray-500">Todavía no hay solicitudes este año.</div>
            <ul v-else class="space-y-2">
              <li
                v-for="req in requests"
                :key="req.id"
                class="rounded-xl border border-gray-800 bg-gray-950 px-3 py-2 flex flex-wrap items-center gap-2"
              >
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-gray-200">
                    {{ req.start_date === req.end_date ? formatIso(req.start_date) : `${formatIso(req.start_date)} al ${formatIso(req.end_date)}` }}
                  </p>
                  <p v-if="req.description" class="text-xs text-gray-400 truncate">{{ req.description }}</p>
                  <p v-if="req.approval_comment" class="text-xs text-amber-200">Motivo RRHH: {{ req.approval_comment }}</p>
                </div>
                <span class="text-[11px] px-2 py-1 rounded-full border uppercase tracking-wide" :class="statusBadge(req.approval_status)">
                  {{ statusLabel(req.approval_status) }}
                </span>
                <button
                  v-if="req.can_cancel"
                  type="button"
                  class="text-xs px-2 py-1 rounded-lg border border-gray-700 text-gray-300 hover:bg-red-500/10 hover:text-red-200 disabled:opacity-50"
                  :disabled="cancellingId === req.id"
                  @click="cancel(req)"
                >{{ cancellingId === req.id ? 'Anulando...' : 'Anular' }}</button>
              </li>
            </ul>
          </section>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })
declare const $fetch: any

type RemoteRequest = {
  id: number
  start_date: string
  end_date: string
  description: string | null
  approval_status: 'pending' | 'approved' | 'rejected'
  approval_comment: string | null
  created_at: string | null
  can_cancel: boolean
}

const { $swal } = useNuxtApp()
const router = useRouter()
const { fetchUser, logout, user } = useAuth()
const sidebar = ref<{ open: boolean } | null>(null)

const weekHeaders = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const letterOf = (d: Date) => weekHeaders[(d.getDay() + 6) % 7]

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth())
const loadedYear = ref<number | null>(null)
const todayIso = toIso(now)

const loading = ref(true)
const saving = ref(false)
const cancellingId = ref<number | null>(null)
const canRequest = ref(false)
const blockReason = ref<string | null>(null)
const scheduleDays = ref<string[]>([])
const holidays = ref<Record<string, string>>({})
const blocked = ref<Record<string, string>>({})
const requests = ref<RemoteRequest[]>([])
const selected = ref<Set<string>>(new Set())
const description = ref('')

function toIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function fromIso(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, m - 1, d)
}
function formatIso(iso: string): string {
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}

const cells = computed(() => {
  const first = new Date(year.value, month.value, 1)
  const offset = (first.getDay() + 6) % 7
  const out: Array<{ iso: string; day: number; inMonth: boolean }> = []
  const total = Math.ceil((offset + new Date(year.value, month.value + 1, 0).getDate()) / 7) * 7
  for (let i = 0; i < total; i++) {
    const d = new Date(year.value, month.value, 1 - offset + i)
    out.push({ iso: toIso(d), day: d.getDate(), inMonth: d.getMonth() === month.value })
  }
  return out
})

// latest non-cancelled request covering each date
const requestByDate = computed<Record<string, RemoteRequest>>(() => {
  const map: Record<string, RemoteRequest> = {}
  for (const req of [...requests.value].reverse()) {
    for (let d = fromIso(req.start_date); d <= fromIso(req.end_date); d.setDate(d.getDate() + 1)) {
      map[toIso(d)] = req
    }
  }
  return map
})

function isWorking(iso: string): boolean {
  return scheduleDays.value.includes(letterOf(fromIso(iso))) && !holidays.value[iso]
}

function isSelectable(iso: string): boolean {
  if (!canRequest.value || iso < todayIso || !isWorking(iso) || blocked.value[iso]) return false
  const req = requestByDate.value[iso]
  return !req || req.approval_status === 'rejected'
}

function cellClass(cell: { iso: string; inMonth: boolean }): string {
  const req = requestByDate.value[cell.iso]
  if (selected.value.has(cell.iso)) return 'bg-sky-600 border-sky-400 text-white'
  if (req && req.approval_status !== 'rejected') {
    return req.approval_status === 'approved' ? 'bg-sky-500/20 border-sky-500/40 text-sky-100' : 'bg-amber-500/15 border-amber-500/40 text-amber-100'
  }
  if (!isSelectable(cell.iso)) return 'bg-gray-950 border-gray-900 text-gray-600 cursor-not-allowed'
  return cell.inMonth ? 'bg-gray-900 border-gray-800 text-gray-100 hover:bg-gray-800' : 'bg-gray-950 border-gray-800 text-gray-500 hover:bg-gray-900'
}

function cellTitle(iso: string): string {
  const req = requestByDate.value[iso]
  if (req) return `Teletrabajo · ${statusLabel(req.approval_status)}`
  return holidays.value[iso] || blocked.value[iso] || ''
}

function cellAria(cell: { iso: string; day: number }): string {
  const extra = cellTitle(cell.iso)
  return `${formatIso(cell.iso)}${extra ? ` · ${extra}` : ''}${selected.value.has(cell.iso) ? ' · seleccionado' : ''}`
}

const selectedList = computed(() => [...selected.value].sort())

function toggle(iso: string) {
  const next = new Set(selected.value)
  if (next.has(iso)) next.delete(iso)
  else if (isSelectable(iso)) next.add(iso)
  selected.value = next
}

function statusLabel(s: string) {
  return s === 'approved' ? 'Aprobado' : s === 'rejected' ? 'Rechazado' : 'Pendiente'
}
function statusShort(s: string) {
  return s === 'approved' ? 'Aprobado' : s === 'rejected' ? 'Rechazado' : 'Pendiente'
}
function statusChip(s: string) {
  if (s === 'approved') return 'bg-sky-500/30 text-sky-100'
  if (s === 'rejected') return 'bg-red-500/30 text-red-100 line-through'
  return 'bg-amber-500/30 text-amber-100'
}
function statusBadge(s: string) {
  if (s === 'approved') return 'border-sky-500/40 text-sky-300 bg-sky-500/10'
  if (s === 'rejected') return 'border-red-500/40 text-red-300 bg-red-500/10'
  return 'border-amber-500/40 text-amber-200 bg-amber-500/10'
}

async function load() {
  loading.value = true
  try {
    const res: any = await $fetch(`/api/remote-work?year=${year.value}`)
    canRequest.value = Boolean(res?.can_request)
    blockReason.value = res?.block_reason || null
    scheduleDays.value = Array.isArray(res?.schedule_days) ? res.schedule_days : []
    holidays.value = res?.holidays || {}
    blocked.value = res?.blocked || {}
    requests.value = res?.requests || []
    loadedYear.value = year.value
  } catch (e) {
    console.error('remote work load failed', e)
    $swal.toast('error', 'No se pudo cargar el teletrabajo')
  } finally {
    loading.value = false
  }
}

function changeMonth(delta: number) {
  const d = new Date(year.value, month.value + delta, 1)
  year.value = d.getFullYear()
  month.value = d.getMonth()
  if (loadedYear.value !== year.value) void load()
}

async function submit() {
  if (!selected.value.size) return
  saving.value = true
  try {
    const res: any = await $fetch('/api/remote-work', {
      method: 'POST',
      body: { dates: selectedList.value, description: description.value.trim() || null },
    })
    selected.value = new Set()
    description.value = ''
    await load()
    $swal.toast('success', `Solicitud enviada a RRHH (${res?.days || 0} día(s))`)
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo enviar la solicitud')
  } finally {
    saving.value = false
  }
}

async function cancel(req: RemoteRequest) {
  cancellingId.value = req.id
  try {
    await $fetch(`/api/remote-work/${req.id}`, { method: 'DELETE' })
    await load()
    $swal.toast('success', 'Solicitud anulada')
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo anular la solicitud')
  } finally {
    cancellingId.value = null
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}
const openSidebar = () => {
  if (sidebar.value) sidebar.value.open = true
}

onMounted(async () => {
  await fetchUser()
  const u: any = user.value || {}
  const canView = Boolean(u?.can_view_vacations) || (Array.isArray(u?.permissions) && u.permissions.includes('vacations.view'))
  if (!canView) {
    router.push('/dashboard')
    return
  }
  await load()
})
</script>
