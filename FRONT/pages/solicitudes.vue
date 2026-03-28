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
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Solicitudes</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto space-y-4">
        <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900 w-max">
          <button
            v-for="f in filters"
            :key="f.id"
            class="px-4 py-2 rounded-lg text-sm transition"
            :class="statusFilter === f.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
            @click="changeFilter(f.id)"
          >
            {{ f.label }}
          </button>
        </div>
        <div class="text-xs">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-1 border"
            :class="reverbStatus === 'conectado'
              ? 'border-emerald-500/40 text-emerald-300 bg-emerald-500/10'
              : reverbStatus === 'reintentando'
                ? 'border-amber-500/40 text-amber-300 bg-amber-500/10'
                : 'border-gray-600 text-gray-300 bg-gray-800/60'"
          >
            Reverb: {{ reverbStatus }}
          </span>
        </div>
        <div class="rounded-xl border border-gray-800 bg-gray-900/60 px-3 py-2 text-xs text-gray-300">
          <p class="text-[11px] uppercase tracking-wide text-gray-400 mb-1">Debug Reverb</p>
          <p v-if="lastRealtimeEvent">
            Evento: <span class="text-white">{{ lastRealtimeEvent.action || 'unknown' }}</span>
            | Solicitud #<span class="text-white">{{ lastRealtimeEvent.request_id ?? '-' }}</span>
            | Usuario #<span class="text-white">{{ lastRealtimeEvent.requester_id ?? '-' }}</span>
            | Canal: <span class="text-white">{{ lastRealtimeEvent.source || '-' }}</span>
            | Hora: <span class="text-white">{{ lastRealtimeEvent.received_at || '-' }}</span>
          </p>
          <p v-else class="text-gray-500">Aun sin eventos recibidos.</p>
        </div>

        <div v-if="loading" class="text-gray-400">Cargando solicitudes...</div>
        <div v-if="actionError" class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
          {{ actionError }}
        </div>

        <div
          v-else
          class="grid gap-4"
          :class="canReview ? 'grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)]' : 'grid-cols-1'"
        >
          <aside v-if="canReview" class="rounded-2xl border border-gray-800 bg-gray-900 p-3 max-h-[75vh] overflow-auto">
            <button
              class="w-full text-left px-3 py-2 rounded-lg mb-2"
              :class="selectedRequesterId === null ? 'bg-blue-600/20 border border-blue-500 text-white' : 'bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700'"
              @click="selectedRequesterId = null"
            >
              Todos ({{ requests.length }})
            </button>
            <button
              v-for="u in requesterItems"
              :key="u.id"
              class="w-full text-left px-3 py-2 rounded-lg mb-2"
              :class="selectedRequesterId === u.id ? 'bg-blue-600/20 border border-blue-500 text-white' : 'bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700'"
              @click="selectedRequesterId = u.id"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="truncate">{{ u.full_name }}</span>
                <span class="text-xs text-gray-400">{{ u.count }}</span>
              </div>
            </button>
          </aside>

          <section>
            <div v-if="!filteredRequests.length" class="text-gray-500">No hay solicitudes para este filtro.</div>
            <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-4">
              <article
                v-for="item in filteredRequests"
                :key="item.id"
                class="rounded-2xl border border-gray-800 bg-gray-900 p-4 space-y-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-white font-semibold">{{ item.title }}</p>
                    <p class="text-xs text-gray-400">{{ item.event_type_name }} - {{ item.start_date }} a {{ item.end_date }}</p>
                    <p v-if="!item.all_day" class="text-xs text-blue-300">Horario: {{ item.start_time }} - {{ item.end_time }}</p>
                    <p class="text-xs text-gray-400 mt-1">Solicita: {{ item.requester.full_name }}</p>
                  </div>
                  <span class="px-2 py-1 rounded-full text-[11px] border uppercase tracking-wide" :class="statusBadgeClass(item.approval_status)">
                    {{ statusLabel(item.approval_status) }}
                  </span>
                </div>

                <p v-if="item.description" class="text-sm text-gray-300">{{ item.description }}</p>
                <div class="text-xs text-gray-400 flex items-center gap-2">
                  <span>Color solicitado:</span>
                  <span class="inline-block w-4 h-4 rounded border border-gray-700" :style="{ backgroundColor: item.color || '#3B82F6' }" />
                </div>
                <p v-if="item.approval_comment" class="text-sm text-amber-200 border border-amber-500/30 bg-amber-500/10 rounded-lg px-3 py-2">
                  Comentario RRHH: {{ item.approval_comment }}
                </p>

                <div v-if="item.approval_status === 'pending' && canReview" class="flex gap-2 pt-1">
                  <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-500 disabled:opacity-60" :disabled="savingId === item.id" @click="review(item.id, 'approved')">
                    Aprobar
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
        <h2 class="text-white font-semibold">Desaprobar solicitud</h2>
        <p class="text-xs text-gray-400">Es obligatorio indicar el motivo.</p>
        <textarea
          v-model="rejectModal.comment"
          class="w-full min-h-28 rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white"
          placeholder="Escribe el motivo de rechazo..."
        />
        <div class="flex justify-end gap-2">
          <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="closeReject">Cancelar</button>
          <button
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
const { token, apiBase, fetchUser, logout, setToken, user, realtimeInstance, connectRealtime } = useAuth()
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
const reverbStatus = ref<'conectado' | 'reintentando' | 'desconectado'>('desconectado')
const lastRealtimeEvent = ref<{ action?: string; request_id?: number; requester_id?: number; source?: string; received_at?: string } | null>(null)
let realtimeRetryTimer: ReturnType<typeof setTimeout> | null = null
let pollingTimer: ReturnType<typeof setInterval> | null = null
const filters = [
  { id: 'pending', label: 'Pendientes' },
  { id: 'approved', label: 'Aprobadas' },
  { id: 'rejected', label: 'Rechazadas' },
  { id: 'all', label: 'Todas' },
]

const rejectModal = ref<{ open: boolean; id: number | null; comment: string }>({
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
  if (!token.value) return
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/api/timeoff/requests?status=${statusFilter.value}`, {
      headers: { Authorization: `Bearer ${token.value}` },
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
  const now = new Date()
  lastRealtimeEvent.value = {
    action: payload?.action || 'updated',
    request_id: payload?.request_id != null ? Number(payload.request_id) : undefined,
    requester_id: payload?.requester_id != null ? Number(payload.requester_id) : undefined,
    source,
    received_at: now.toLocaleTimeString('es-AR', { hour12: false }),
  }
  if (realtimeRefreshing.value) return
  realtimeRefreshing.value = true
  try {
    await loadRequests()
  } finally {
    realtimeRefreshing.value = false
  }
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
  reverbStatus.value = 'desconectado'
}

function setupRealtimeListeners() {
  teardownRealtimeListeners()
  const echo: any = realtimeInstance?.()
  const uid = Number((user.value as any)?.id || 0)
  if (!echo || !uid) {
    reverbStatus.value = 'reintentando'
    realtimeRetryTimer = setTimeout(() => setupRealtimeListeners(), 1500)
    return
  }

  userRealtimeChannel = echo.private(`user.${uid}`)
  userRealtimeChannel.listen('.TimeOffRequestUpdated', (e: any) => {
    void handleRealtimeUpdate(e, `user.${uid}`)
  })

  if (canReview.value) {
    reviewerRealtimeChannel = echo.private('timeoff.reviewers')
    reviewerRealtimeChannel.listen('.TimeOffRequestUpdated', (e: any) => {
      void handleRealtimeUpdate(e, 'timeoff.reviewers')
    })
  }
  reverbStatus.value = 'conectado'
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

async function review(id: number, decision: 'approved' | 'rejected', comment = '') {
  if (!token.value) return
  savingId.value = id
  actionError.value = ''
  try {
    await $fetch(`${apiBase}/api/timeoff/requests/${id}/review`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { decision, comment: comment || null },
    })
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
  await review(rejectModal.value.id, 'rejected', rejectModal.value.comment.trim())
}

onBeforeMount(async () => {
  if (!token.value) {
    if (process.client) {
      const saved = localStorage.getItem('rrhh_token')
      if (saved) {
        setToken(saved)
      } else {
        return router.push('/login')
      }
    }
  }

  await fetchUser()
  if (!realtimeInstance?.() && token.value) {
    connectRealtime({
      host: config.public.reverbHost,
      port: config.public.reverbPort,
      appId: config.public.reverbAppId,
      key: config.public.reverbAppKey,
      token: token.value,
      authEndpoint: `${config.public.apiBase}/broadcasting/auth`,
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
