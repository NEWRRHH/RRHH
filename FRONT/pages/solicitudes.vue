<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Solicitudes</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-4 sm:p-6 overflow-auto space-y-4">
        <!-- scrolls sideways on narrow phones instead of overflowing the page -->
        <div class="flex max-w-full w-max overflow-x-auto rounded-xl border border-gray-700 p-1 bg-gray-900">
          <button
            v-for="f in filters"
            :key="f.id"
            class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-2 rounded-lg text-sm transition"
            :class="statusFilter === f.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
            @click="changeFilter(f.id)"
          >
            {{ f.label }}
          </button>
        </div>
        <div v-if="loading" class="space-y-4">
          <div class="flex gap-4">
            <div class="hidden xl:block w-48 space-y-3" v-if="canReview">
              <div v-for="i in 5" :key="'sldr-' + i" class="h-12 rounded-xl bg-gray-800 animate-pulse"></div>
            </div>
            <div class="flex-1 grid grid-cols-1 xl:grid-cols-2 gap-4">
              <div v-for="i in 4" :key="'slc-' + i" class="rounded-2xl border border-gray-800 bg-gray-900 p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 space-y-2">
                    <div class="h-4 w-3/4 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-3 w-1/2 rounded bg-gray-800 animate-pulse"></div>
                  </div>
                  <div class="h-5 w-20 rounded-full bg-gray-800 animate-pulse"></div>
                </div>
                <div class="h-3 w-full rounded bg-gray-800 animate-pulse"></div>
                <div class="flex gap-2">
                  <div class="h-8 w-20 rounded-lg bg-gray-800 animate-pulse"></div>
                  <div class="h-8 w-24 rounded-lg bg-gray-800 animate-pulse"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="actionError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
          {{ actionError }}
        </div>

        <div
          v-if="!loading"
          class="grid gap-4"
          :class="canReview ? 'grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)]' : 'grid-cols-1'"
        >
          <!-- requesters: horizontal chips on phones/tablets, side list on desktop -->
          <aside
            v-if="canReview"
            class="min-w-0 flex gap-2 overflow-x-auto pb-1 xl:block xl:pb-3 xl:rounded-2xl xl:border xl:border-gray-800 xl:bg-gray-900 xl:p-3 xl:max-h-[75vh] xl:overflow-auto"
          >
            <button
              class="shrink-0 whitespace-nowrap text-left px-3 py-2 rounded-lg text-sm xl:w-full xl:mb-2"
              :class="selectedRequesterId === null ? 'bg-blue-600/20 border border-blue-500 text-white' : 'bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700'"
              @click="selectedRequesterId = null"
            >
              Todos ({{ requests.length }})
            </button>
            <button
              v-for="u in requesterItems"
              :key="u.id"
              class="shrink-0 max-w-[220px] text-left px-3 py-2 rounded-lg text-sm xl:max-w-none xl:w-full xl:mb-2"
              :class="selectedRequesterId === u.id ? 'bg-blue-600/20 border border-blue-500 text-white' : 'bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700'"
              @click="selectedRequesterId = u.id"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="truncate">{{ u.full_name }}</span>
                <span class="text-xs text-gray-400">{{ u.count }}</span>
              </div>
            </button>
          </aside>

          <section class="min-w-0">
            <div v-if="!filteredRequests.length" class="text-gray-500">No hay solicitudes para este filtro.</div>
            <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-4">
              <article
                v-for="item in filteredRequests"
                :key="item.id"
                class="min-w-0 rounded-2xl border border-gray-800 bg-gray-900 p-4 space-y-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center" :class="eventTypeVisual(item.event_type_code, item.event_type_name).tone">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="eventTypeVisual(item.event_type_code, item.event_type_name).icon" />
                    </svg>
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-white font-semibold break-words">{{ item.title }}</p>
                    <p class="text-xs text-gray-400">{{ item.event_type_name }} · {{ item.start_date }} a {{ item.end_date }}</p>
                    <p v-if="!item.all_day" class="text-xs text-blue-300">Horario: {{ item.start_time }} - {{ item.end_time }}</p>
                    <p class="text-xs text-gray-400 mt-1 break-words">Solicita: {{ item.requester.full_name }}</p>
                  </div>
                  <span class="shrink-0 px-2 py-1 rounded-full text-[11px] border uppercase tracking-wide" :class="statusBadgeClass(item.approval_status)">
                    {{ statusLabel(item.approval_status) }}
                  </span>
                </div>

                <p v-if="item.description" class="text-sm text-gray-300 whitespace-pre-wrap break-words">{{ item.description }}</p>
                <p v-if="item.approval_comment" class="text-sm text-amber-200 border border-amber-500/30 bg-amber-500/10 rounded-lg px-3 py-2 break-words">
                  Comentario RRHH: {{ item.approval_comment }}
                </p>

                <!-- multi-day attendance request: one line per day; RRHH ticks the ones it accepts -->
                <div v-if="isMultiDay(item)" class="rounded-lg border border-gray-800 bg-gray-950/60 divide-y divide-gray-800">
                  <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500">
                    Días solicitados
                    <span v-if="item.approval_status === 'pending' && canReview" class="normal-case tracking-normal">· marca los que apruebas</span>
                  </div>
                  <label
                    v-for="d in item.request_days"
                    :key="d.date"
                    class="!mb-0 !font-normal flex items-center gap-3 px-3 py-2 text-sm"
                    :class="item.approval_status === 'pending' && canReview && d.status === 'pending' ? 'cursor-pointer hover:bg-gray-800/40' : ''"
                  >
                    <input
                      v-if="item.approval_status === 'pending' && canReview && (d.status || 'pending') === 'pending'"
                      type="checkbox"
                      class="accent-emerald-500 w-4 h-4"
                      :checked="chosenDays(item).includes(d.date)"
                      @change="toggleDay(item, d.date)"
                    />
                    <span class="tabular-nums" :class="dayStatusClass(d.status)">{{ formatDay(d.date) }}</span>
                    <span class="text-xs text-gray-400">{{ dayKindLabel(d.kind) }} · {{ d.start_time }}–{{ d.end_time }}</span>
                    <span v-if="d.status === 'approved'" class="ml-auto text-[11px] text-emerald-300">Aprobado</span>
                    <span v-else-if="d.status === 'rejected'" class="ml-auto text-[11px] text-red-300">Rechazado</span>
                  </label>
                </div>

                <div v-if="item.approval_status === 'pending' && canReview" class="grid grid-cols-2 sm:flex gap-2 pt-1">
                  <button
                    class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-500 disabled:opacity-60"
                    :disabled="savingId === item.id || (isMultiDay(item) && !chosenDays(item).length)"
                    @click="approve(item)"
                  >
                    {{ isMultiDay(item) && chosenDays(item).length < pendingDays(item).length ? `Aprobar ${chosenDays(item).length} de ${pendingDays(item).length}` : 'Aprobar' }}
                  </button>
                  <button class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-500 disabled:opacity-60" :disabled="savingId === item.id" @click="openReject(item.id)">
                    Desaprobar
                  </button>
                </div>
              </article>
            </div>
          </section>
        </div>
      </main>
    </div>

    <div v-if="rejectModal.open" class="fixed inset-0 z-50 bg-black/70 p-4 flex items-center justify-center" @click.self="closeReject">
      <div class="w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-4 space-y-3">
        <template v-if="rejectModal.partial">
          <h2 class="text-white font-semibold">Aprobar algunos días</h2>
          <p class="text-xs text-gray-400">
            Se aprueban {{ rejectModal.partial.length }} {{ rejectModal.partial.length === 1 ? 'día' : 'días' }} y se rechaza el resto.
            Puedes explicar el motivo (opcional).
          </p>
        </template>
        <template v-else>
          <h2 class="text-white font-semibold">Desaprobar solicitud</h2>
          <p class="text-xs text-gray-400">Es obligatorio indicar el motivo.</p>
        </template>
        <textarea
          v-model="rejectModal.comment"
          class="w-full min-h-28 rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
          :placeholder="rejectModal.partial ? 'Motivo de los días rechazados...' : 'Escribe el motivo de rechazo...'"
        />
        <div class="flex justify-end gap-2">
          <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="closeReject">Cancelar</button>
          <button
            v-if="rejectModal.partial"
            class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-50"
            :disabled="savingId === rejectModal.id"
            @click="confirmReject"
          >
            Confirmar
          </button>
          <button
            v-else
            class="px-3 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 disabled:opacity-50"
            :disabled="!rejectModal.comment.trim() || savingId === rejectModal.id"
            @click="confirmReject"
          >
            Confirmar rechazo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })
declare const $fetch: any
declare const process: any
declare function useRuntimeConfig(): any

const router = useRouter()
const { authenticated, apiBase, fetchUser, logout, user, realtimeInstance, connectRealtime } = useAuth()
const config = useRuntimeConfig()
const sidebar = ref<{ open: boolean } | null>(null)

const loading = ref(true)
const savingId = ref<number | null>(null)
const actionError = ref('')
const canReview = ref(false)
const statusFilter = ref<'pending' | 'approved' | 'rejected' | 'all'>('pending')
const requests = ref<any[]>([])
const selectedRequesterId = ref<number | null>(null)
const realtimeRefreshing = ref(false)
let realtimeRetryTimer: ReturnType<typeof setTimeout> | null = null
let pollingTimer: ReturnType<typeof setInterval> | null = null
const filters: Array<{ id: 'pending' | 'approved' | 'rejected' | 'all'; label: string }> = [
  { id: 'pending', label: 'Pendientes' },
  { id: 'approved', label: 'Aprobadas' },
  { id: 'rejected', label: 'Rechazadas' },
  { id: 'all', label: 'Todas' },
]

// partial: multi-day request approved only for some days (the note explains the rest)
const rejectModal = ref<{ open: boolean; id: number | null; comment: string; partial?: string[] | null }>({
  open: false,
  id: null,
  comment: '',
})
let reviewerRealtimeChannel: any = null
let userRealtimeChannel: any = null

const requesterItems = computed(() => {
  const map = new Map<number, { id: number; full_name: string; count: number }>()
  for (const r of requests.value) {
    const requester = r?.requester
    if (!requester?.id) continue
    const current = map.get(requester.id) || { id: requester.id, full_name: requester.full_name || `Usuario #${requester.id}`, count: 0 }
    current.count++
    map.set(requester.id, current)
  }
  return Array.from(map.values()).sort((a, b) => a.full_name.localeCompare(b.full_name, 'es'))
})

const filteredRequests = computed(() => {
  if (!selectedRequesterId.value) return requests.value
  return requests.value.filter((r: any) => Number(r?.requester?.id || 0) === Number(selectedRequesterId.value))
})

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

function canReviewRequests(): boolean {
  const u: any = user.value
  if (!u) return false
  const perms = Array.isArray(u.permissions) ? u.permissions : []
  return perms.includes('requests.review')
}

function statusLabel(status: string): string {
  if (status === 'approved') return 'Aprobada'
  if (status === 'rejected') return 'Rechazada'
  return 'Pendiente'
}

function statusBadgeClass(status: string): string {
  if (status === 'approved') return 'border-emerald-500/40 text-emerald-300 bg-emerald-500/10'
  if (status === 'rejected') return 'border-red-500/40 text-red-300 bg-red-500/10'
  return 'border-gray-500/40 text-gray-200 bg-gray-500/20'
}

async function loadRequests() {
  if (!authenticated.value) return
  loading.value = true
  try {
    const res: any = await $fetch(`/api/timeoff/requests?status=${statusFilter.value}`, {
    })
    canReview.value = !!res?.can_review
    requests.value = Array.isArray(res?.requests) ? res.requests : []
    if (selectedRequesterId.value && !requests.value.some((r: any) => Number(r?.requester?.id || 0) === Number(selectedRequesterId.value))) {
      selectedRequesterId.value = null
    }
  } catch (e: any) {
    canReview.value = false
    requests.value = []
  } finally {
    loading.value = false
  }
}

async function handleRealtimeUpdate(payload?: any, source = 'unknown') {
  if (realtimeRefreshing.value) return
  realtimeRefreshing.value = true
  try {
    await loadRequests()
  } finally {
    realtimeRefreshing.value = false
  }
}

// Reviewers channel is per company (multi-tenant).
function reviewersChannelName() {
  return `company.${Number((user.value as any)?.company_id || 0)}.timeoff.reviewers`
}

function teardownRealtimeListeners() {
  if (realtimeRetryTimer) {
    clearTimeout(realtimeRetryTimer)
    realtimeRetryTimer = null
  }
  try {
    reviewerRealtimeChannel?.stopListening?.('.TimeOffRequestUpdated')
    userRealtimeChannel?.stopListening?.('.TimeOffRequestUpdated')
  } catch (_e) {
    // ignore
  } finally {
    reviewerRealtimeChannel = null
    userRealtimeChannel = null
  }
}

function setupRealtimeListeners() {
  teardownRealtimeListeners()
  const echo: any = realtimeInstance?.()
  const uid = Number((user.value as any)?.id || 0)
  if (!echo || !uid) {
    realtimeRetryTimer = setTimeout(() => setupRealtimeListeners(), 1500)
    return
  }

  userRealtimeChannel = echo.private(`user.${uid}`)
  userRealtimeChannel.listen('.TimeOffRequestUpdated', (e: any) => {
    void handleRealtimeUpdate(e, `user.${uid}`)
  })

  if (canReview.value) {
    reviewerRealtimeChannel = echo.private(reviewersChannelName())
    reviewerRealtimeChannel.listen('.TimeOffRequestUpdated', (e: any) => {
      void handleRealtimeUpdate(e, reviewersChannelName())
    })
  }
}

function changeFilter(next: 'pending' | 'approved' | 'rejected' | 'all') {
  statusFilter.value = next
  selectedRequesterId.value = null
  void loadRequests()
}

function openReject(id: number) {
  rejectModal.value = { open: true, id, comment: '' }
}

function closeReject() {
  rejectModal.value = { open: false, id: null, comment: '' }
}

// ── Multi-day attendance requests: RRHH ticks the days it accepts ──
const dayChoices = ref<Record<number, string[]>>({})
const pendingDays = (item: any) => (Array.isArray(item?.request_days) ? item.request_days.filter((d: any) => (d.status || 'pending') === 'pending') : [])
const isMultiDay = (item: any) => Array.isArray(item?.request_days) && item.request_days.length > 0
// every pending day starts ticked
const chosenDays = (item: any): string[] => dayChoices.value[item.id] ?? pendingDays(item).map((d: any) => d.date)
function toggleDay(item: any, date: string) {
  const current = chosenDays(item)
  dayChoices.value = {
    ...dayChoices.value,
    [item.id]: current.includes(date) ? current.filter((d) => d !== date) : [...current, date],
  }
}
// "2026-06-15" -> "lun 15/06"
const formatDay = (iso: string) => parseDate(iso)?.toLocaleDateString('es-AR', { weekday: 'short', day: '2-digit', month: '2-digit' }) ?? iso
const dayKindLabel = (kind: string) => (kind === 'entry' ? 'Falta entrada' : kind === 'exit' ? 'Falta salida' : 'Entrada y salida')
const dayStatusClass = (status: string) =>
  status === 'approved' ? 'text-emerald-300' : status === 'rejected' ? 'text-red-300 line-through' : 'text-gray-200'

function approve(item: any) {
  if (!isMultiDay(item)) return review(item.id, 'approved')
  const days = chosenDays(item)
  const rejectedCount = pendingDays(item).length - days.length
  // some days rejected: ask why (optional note sent with the approval)
  if (rejectedCount > 0) {
    rejectModal.value = { open: true, id: item.id, comment: '', partial: days }
    return
  }
  return review(item.id, 'approved', '', days)
}

async function review(id: number, decision: 'approved' | 'rejected', comment = '', approvedDates?: string[]) {
  if (!authenticated.value) return
  savingId.value = id
  actionError.value = ''
  try {
    await $fetch(`/api/timeoff/requests/${id}/review`, {
      method: 'POST',
      body: { decision, comment: comment || null, ...(approvedDates ? { approved_dates: approvedDates } : {}) },
    })
    delete dayChoices.value[id]
    closeReject()
    await loadRequests()
  } catch (e: any) {
    actionError.value = e?.data?.message || 'No se pudo procesar la solicitud.'
  } finally {
    savingId.value = null
  }
}

async function confirmReject() {
  if (!rejectModal.value.id) return
  const { id, comment, partial } = rejectModal.value
  if (partial) return review(id, 'approved', comment.trim(), partial)
  await review(id, 'rejected', comment.trim())
}

onBeforeMount(async () => {
  await fetchUser()

  const currentUser: any = user.value || {}
  const canView = Boolean(currentUser?.can_view_requests) || (Array.isArray(currentUser?.permissions) && currentUser.permissions.includes('requests.view'))
  if (!canView) {
    return router.push('/dashboard')
  }

  if (!realtimeInstance?.() && authenticated.value) {
    connectRealtime({
      host: config.public.reverbHost,
      port: config.public.reverbPort,
      appId: config.public.reverbAppId,
      key: config.public.reverbAppKey,
    })
  }
  canReview.value = canReviewRequests()
  await loadRequests()
  setupRealtimeListeners()
  pollingTimer = setInterval(() => {
    void loadRequests()
  }, 12000)
})

watch(canReview, () => {
  setupRealtimeListeners()
})

onBeforeUnmount(() => {
  teardownRealtimeListeners()
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
})
</script>
