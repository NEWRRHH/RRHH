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
        <h1 class="text-base font-semibold text-white">Configuracion</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto">
        <div class="max-w-5xl mx-auto space-y-4">
          <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="px-4 py-2 rounded-lg text-sm transition"
              :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>

          <section v-if="activeTab === 'dashboard'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">Widgets del Dashboard</h2>
              <div class="flex gap-2">
                <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="resetDefaults">
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

            <p class="text-sm text-gray-400">
              Activa/desactiva cards y elige su posicion. Los cambios se reflejan en el dashboard para este usuario.
            </p>

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

            <div v-else class="space-y-3">
              <div
                v-for="w in widgets"
                :key="w.key"
                class="rounded-xl border border-gray-800 bg-gray-950 p-3 grid grid-cols-1 md:grid-cols-[1fr_160px] gap-3 items-center"
              >
                <div class="flex items-center gap-3">
                  <label class="inline-flex items-center gap-2 text-gray-200">
                    <input v-model="visibility[w.key]" type="checkbox" class="accent-blue-600" />
                    <span class="font-medium">{{ w.name }}</span>
                  </label>
                  <span class="text-xs text-gray-500">{{ w.key }}</span>
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

          <section v-else-if="activeTab === 'profile'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
            <h2 class="text-white font-semibold mb-2">Perfil</h2>
            <p class="text-sm text-gray-400">Proximamente: preferencias de perfil y privacidad.</p>
          </section>

          <section v-else class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
            <h2 class="text-white font-semibold mb-2">Notificaciones</h2>
            <p class="text-sm text-gray-400">Proximamente: reglas y canales de notificacion.</p>
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

const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const { token, fetchUser, logout, apiBase, setToken } = useAuth()

const tabs = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'profile', label: 'Perfil' },
  { id: 'notifications', label: 'Notificaciones' },
]

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

  await loadLayout()
})

onBeforeUnmount(() => {
  if (toastTimer) clearTimeout(toastTimer)
})
</script>
