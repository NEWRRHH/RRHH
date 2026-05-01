<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition" @click="openSidebar">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Documentos</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto">
        <div class="max-w-5xl mx-auto space-y-4">
          <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900">
            <button v-for="tab in tabs" :key="tab.id" class="px-4 py-2 rounded-lg text-sm transition"
              :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
              @click="changeTab(tab.id)">
              {{ tab.label }}
            </button>
          </div>

          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <h2 class="text-white font-semibold">{{ currentTabTitle }}</h2>
            <form
              v-if="canUploadInTab"
              class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-end"
              @submit.prevent="uploadDocument"
            >
              <div>
                <label class="block text-xs text-gray-400 mb-1">Descripcion</label>
                <input v-model="description" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" placeholder="Ej: justificante medico de marzo" />
              </div>
              <div class="flex gap-2">
                <input ref="fileInput" type="file" class="hidden" @change="onFileSelected" />
                <button type="button" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="selectFile">
                  {{ selectedFile ? selectedFile.name : 'Seleccionar archivo' }}
                </button>
                <button class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50" :disabled="uploading || !selectedFile">
                  {{ uploading ? 'Subiendo...' : 'Subir' }}
                </button>
              </div>
            </form>
            <div v-else class="rounded-xl border border-amber-600/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
              En la pestaña Nominas no se permite subir archivos. Las nominas se cargan externamente.
            </div>

            <div class="rounded-xl border border-gray-800 overflow-hidden">
              <div class="grid grid-cols-[70px_1fr_130px_90px_110px] px-3 py-2 text-xs text-gray-400 bg-gray-800/60 border-b border-gray-800">
                <div>Preview</div>
                <div>Documento</div>
                <div>Fecha</div>
                <div>Tamano</div>
                <div>Accion</div>
              </div>
              <div
                v-for="d in documents"
                :key="d.id"
                class="grid grid-cols-[70px_1fr_130px_90px_110px] px-3 py-2 text-sm text-gray-200 border-b border-gray-800 items-center gap-2"
              >
                <div
                  class="w-12 h-12 rounded-lg border border-gray-700 bg-gray-800/80 flex items-center justify-center"
                  :class="previewBoxClass(d)"
                  :title="fileTypeLabel(d)"
                >
                  <svg v-if="fileTypeKind(d) === 'pdf'" class="w-6 h-6 text-red-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                  </svg>
                  <svg v-else-if="fileTypeKind(d) === 'image'" class="w-6 h-6 text-emerald-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a2 2 0 012.8 0l1.2 1.2a2 2 0 002.8 0L20 8m-2 12H6a2 2 0 01-2-2V6a2 2 0 012-2h8" />
                  </svg>
                  <svg v-else-if="fileTypeKind(d) === 'sheet'" class="w-6 h-6 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zM9 8h6M9 12h6M9 16h4" />
                  </svg>
                  <svg v-else class="w-6 h-6 text-blue-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                  </svg>
                </div>
                <div class="truncate">
                  <div class="truncate">{{ d.original_name || d.filename }}</div>
                  <div class="text-xs text-gray-500 truncate">{{ d.description || '-' }}</div>
                </div>
                <div class="text-xs text-gray-400">{{ formatDate(d.created_at) }}</div>
                <div class="text-xs text-gray-400">{{ formatSize(d.size) }}</div>
                <div>
                  <button
                    class="px-2.5 py-1.5 rounded-lg border border-gray-700 text-xs text-gray-200 hover:bg-gray-800 disabled:opacity-50"
                    :disabled="downloadingId === d.id"
                    @click="downloadDocument(d)"
                  >
                    {{ downloadingId === d.id ? 'Bajando...' : 'Descargar' }}
                  </button>
                </div>
              </div>
              <div v-if="!documents.length" class="px-3 py-8 text-center text-gray-500 text-sm">No hay documentos en esta categoria.</div>
            </div>
          </section>
        </div>
      </main>
    </div>

    <AppToast :show="toast.show" :message="toast.message" :type="toast.type" @close="toast.show = false" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppToast from '../components/AppToast.vue'

definePageMeta({ auth: true })
declare const $fetch: any
declare const process: any

const { token, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const tabs = [
  { id: 'medical', label: 'Certificados medicos' },
  { id: 'receipt', label: 'Comprobantes' },
  { id: 'payroll', label: 'Nominas' },
]
const activeTab = ref<'medical' | 'receipt' | 'payroll'>('medical')
const documents = ref<any[]>([])
const downloadingId = ref<number | null>(null)
const description = ref('')
const selectedFile = ref<File | null>(null)
const uploading = ref(false)

const toast = ref<{ show: boolean; type: 'success' | 'error'; message: string }>({ show: false, type: 'success', message: '' })
let toastTimer: any = null

const currentTabTitle = computed(() => tabs.find((t) => t.id === activeTab.value)?.label || '')
const canUploadInTab = computed(() => activeTab.value !== 'payroll')

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

function changeTab(tabId: 'medical' | 'receipt' | 'payroll') {
  activeTab.value = tabId
  description.value = ''
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
  void loadDocuments()
}

function selectFile() {
  fileInput.value?.click()
}

function onFileSelected(e: Event) {
  const input = e.target as HTMLInputElement
  selectedFile.value = input.files?.[0] || null
}

async function loadDocuments() {
  if (!token.value) return
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/documents?category=${activeTab.value}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    documents.value = res?.documents || []
  } catch (e) {
    console.error('documents load failed', e)
    documents.value = []
  }
}

async function uploadDocument() {
  if (!token.value || !selectedFile.value || !canUploadInTab.value) return
  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', selectedFile.value)
    body.append('category', activeTab.value)
    body.append('description', description.value || '')

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/documents/upload`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body,
    })
    description.value = ''
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    await loadDocuments()
    showToast('success', 'Documento cargado correctamente')
  } catch (e: any) {
    console.error('documents upload failed', e)
    showToast('error', e?.data?.message || 'No se pudo cargar el documento')
  } finally {
    uploading.value = false
  }
}

function fileTypeKind(d: any): 'pdf' | 'image' | 'sheet' | 'file' {
  const mime = String(d?.mime_type || '').toLowerCase()
  const name = String(d?.original_name || d?.filename || '').toLowerCase()
  if (mime.includes('pdf') || name.endsWith('.pdf')) return 'pdf'
  if (mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name)) return 'image'
  if (mime.includes('spreadsheet') || /\.(xlsx?|csv|ods)$/.test(name)) return 'sheet'
  return 'file'
}

function previewBoxClass(d: any) {
  const kind = fileTypeKind(d)
  if (kind === 'pdf') return 'bg-red-500/10 border-red-500/30'
  if (kind === 'image') return 'bg-emerald-500/10 border-emerald-500/30'
  if (kind === 'sheet') return 'bg-green-500/10 border-green-500/30'
  return 'bg-blue-500/10 border-blue-500/30'
}

function fileTypeLabel(d: any) {
  const kind = fileTypeKind(d)
  if (kind === 'pdf') return 'PDF'
  if (kind === 'image') return 'Imagen'
  if (kind === 'sheet') return 'Hoja de calculo'
  return 'Documento'
}

async function downloadDocument(d: any) {
  if (!token.value || !d?.id) return
  downloadingId.value = Number(d.id)
  try {
    const res = await fetch(`${apiBase || 'http://localhost:8000'}/api/documents/${d.id}/download`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (!res.ok) {
      throw new Error('download_failed')
    }
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
    console.error('documents download failed', e)
    showToast('error', 'No se pudo descargar el documento')
  } finally {
    downloadingId.value = null
  }
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
    }
  } else {
    await fetchUser()
  }
  await loadDocuments()
})

onBeforeUnmount(() => {
  if (toastTimer) clearTimeout(toastTimer)
})
</script>
