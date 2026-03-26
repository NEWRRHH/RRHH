<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition" @click="openSidebar">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Comunicados</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto">
        <div class="max-w-6xl mx-auto grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-4">
          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5 h-max">
            <h2 class="text-white font-semibold mb-4">Nuevo comunicado</h2>
            <form class="space-y-3" @submit.prevent="createAnnouncement">
              <div>
                <label class="block text-xs text-gray-400 mb-1">Titulo</label>
                <input
                  v-model="form.title"
                  class="w-full rounded-xl bg-gray-800 border border-gray-700 px-3 py-2 text-white"
                  placeholder="Ej: Actualizacion de politicas"
                />
              </div>

              <div>
                <label class="block text-xs text-gray-400 mb-1">Mensaje</label>
                <textarea
                  v-model="form.body"
                  class="w-full rounded-xl bg-gray-800 border border-gray-700 px-3 py-2 text-white min-h-[120px]"
                  placeholder="Escribe el comunicado..."
                />
              </div>

              <div>
                <label class="block text-xs text-gray-400 mb-2">Destino</label>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    @click="form.scope = 'all'"
                    :class="form.scope === 'all' ? 'border-blue-500 bg-blue-600/20 text-blue-100' : 'border-gray-700 bg-gray-800 text-gray-300'"
                    class="rounded-xl border px-3 py-2 text-sm"
                  >
                    Todos
                  </button>
                  <button
                    type="button"
                    @click="form.scope = 'team'"
                    :class="form.scope === 'team' ? 'border-blue-500 bg-blue-600/20 text-blue-100' : 'border-gray-700 bg-gray-800 text-gray-300'"
                    class="rounded-xl border px-3 py-2 text-sm"
                  >
                    Por equipo
                  </button>
                </div>
              </div>

              <div v-if="form.scope === 'team'">
                <label class="block text-xs text-gray-400 mb-1">Equipo</label>
                <select v-model="form.team_id" class="w-full rounded-xl bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm">
                  <option value="">Selecciona un equipo</option>
                  <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
              </div>

              <button
                class="w-full rounded-xl px-3 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium disabled:opacity-50"
                :disabled="saving"
              >
                {{ saving ? 'Enviando...' : 'Enviar comunicado' }}
              </button>
            </form>
          </section>

          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-white font-semibold">Historial</h2>
              <button class="text-xs text-gray-300 border border-gray-700 rounded-lg px-2.5 py-1.5 hover:bg-gray-800" @click="loadAnnouncements">
                Actualizar
              </button>
            </div>

            <div v-if="loading" class="space-y-2">
              <div v-for="i in 5" :key="i" class="h-20 rounded-xl bg-gray-800 animate-pulse"></div>
            </div>

            <div v-else class="space-y-2">
              <article v-for="a in announcements" :key="a.id" class="rounded-xl border border-gray-800 bg-gray-800/60 p-3">
                <div class="flex items-start gap-2">
                  <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold text-white truncate">{{ a.title }}</h3>
                    <p class="text-xs text-gray-400">{{ a.author }} · {{ formatDate(a.created_at) }}</p>
                  </div>
                  <span
                    class="text-[11px] rounded-full px-2 py-1 border"
                    :class="a.scope === 'team' ? 'border-amber-500/50 bg-amber-500/15 text-amber-100' : 'border-blue-500/50 bg-blue-500/15 text-blue-100'"
                  >
                    {{ a.scope === 'team' ? `Equipo: ${a.team_name || '-'}` : 'Todos' }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-200 whitespace-pre-wrap">{{ a.body }}</p>
              </article>
              <div v-if="!announcements.length" class="text-sm text-gray-500 text-center py-10">
                Todavia no hay comunicados.
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>

    <AppToast :show="toast.show" :message="toast.message" :type="toast.type" @close="toast.show = false" />
  </div>
</template>

<script setup lang="ts">
import { onBeforeMount, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppToast from '../components/AppToast.vue'

definePageMeta({ auth: true })

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const loading = ref(false)
const saving = ref(false)
const teams = ref<any[]>([])
const announcements = ref<any[]>([])
const form = ref<{ title: string; body: string; scope: 'all' | 'team'; team_id: string }>({
  title: '',
  body: '',
  scope: 'all',
  team_id: '',
})
const toast = ref<{ show: boolean; type: 'success' | 'error'; message: string }>({ show: false, type: 'success', message: '' })
let toastTimer: any = null

function showToast(type: 'success' | 'error', message: string) {
  toast.value = { show: true, type, message }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toast.value.show = false), 2800)
}

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

async function loadTeams() {
  if (!token.value) return
  try {
    teams.value = await $fetch(`${apiBase || 'http://localhost:8000'}/api/announcements/teams`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e) {
    console.error('announcement teams load failed', e)
    teams.value = []
  }
}

async function loadAnnouncements() {
  if (!token.value) return
  loading.value = true
  try {
    announcements.value = await $fetch(`${apiBase || 'http://localhost:8000'}/api/announcements?limit=100`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e) {
    console.error('announcements load failed', e)
    announcements.value = []
    showToast('error', 'No se pudieron cargar los comunicados')
  } finally {
    loading.value = false
  }
}

async function createAnnouncement() {
  if (!token.value) return
  if (!form.value.title.trim() || !form.value.body.trim()) {
    showToast('error', 'Completa titulo y mensaje')
    return
  }
  if (form.value.scope === 'team' && !form.value.team_id) {
    showToast('error', 'Selecciona un equipo')
    return
  }

  saving.value = true
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/announcements`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        title: form.value.title.trim(),
        body: form.value.body.trim(),
        scope: form.value.scope,
        team_id: form.value.scope === 'team' ? Number(form.value.team_id) : null,
      },
    })
    form.value = { title: '', body: '', scope: 'all', team_id: '' }
    showToast('success', 'Comunicado enviado')
    await loadAnnouncements()
  } catch (e: any) {
    console.error('announcement create failed', e)
    showToast('error', e?.data?.message || 'No se pudo enviar el comunicado')
  } finally {
    saving.value = false
  }
}

function formatDate(dt?: string) {
  if (!dt) return '-'
  try {
    return new Date(dt).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return dt
  }
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

  await loadTeams()
  await loadAnnouncements()
})

onBeforeUnmount(() => {
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

