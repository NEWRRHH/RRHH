<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition" aria-label="Abrir menú" @click="openSidebar">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Nóminas</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-4 sm:p-6 overflow-auto">
        <div class="max-w-6xl mx-auto space-y-4">
          <!-- 1. month + files -->
          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4 sm:p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-white font-semibold">Repartir nóminas</h2>
                <p class="text-sm text-gray-400 mt-1">
                  Sube los PDF que te manda la gestoría, <strong class="text-gray-200">nombrados con el DNI</strong> de cada empleado
                  (p. ej. <code class="px-1 rounded bg-gray-800 text-gray-300">30111222A.pdf</code>). Revisa a quién va cada uno y pulsa <strong class="text-gray-200">Enviar</strong>.
                </p>
              </div>
              <label class="!mb-0 text-xs text-gray-400 font-normal">Mes
                <input
                  v-model="period"
                  type="month"
                  class="!mb-0 mt-1 block rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm"
                  :disabled="!!batch && batch.status === 'draft' && files.length > 0"
                  @change="openPeriod"
                />
              </label>
            </div>

            <!-- drop zone -->
            <div
              v-if="!batch || batch.status === 'draft'"
              class="rounded-xl border-2 border-dashed p-6 text-center transition"
              :class="dragging ? 'border-blue-500 bg-blue-500/10' : 'border-gray-700 bg-gray-950/40'"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="onDrop"
            >
              <svg class="w-10 h-10 mx-auto text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm9 3v6m-3-3l3-3 3 3" />
              </svg>
              <p class="mt-2 text-sm text-gray-300">Arrastra aquí la carpeta o los PDF de {{ periodLabel }}</p>
              <div class="mt-3 flex flex-wrap justify-center gap-2">
                <input ref="folderInput" type="file" class="hidden" webkitdirectory multiple @change="onPick" />
                <input ref="filesInput" type="file" class="hidden" accept="application/pdf,.pdf" multiple @change="onPick" />
                <button type="button" class="px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm disabled:opacity-50" :disabled="uploading" @click="folderInput?.click()">
                  Seleccionar carpeta
                </button>
                <button type="button" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800 text-sm disabled:opacity-50" :disabled="uploading" @click="filesInput?.click()">
                  Seleccionar archivos
                </button>
              </div>
              <p class="mt-2 text-[11px] text-gray-500">Solo PDF, hasta 10 MB cada uno. Los que no son PDF se ignoran.</p>
              <div v-if="uploading" class="mt-4 max-w-sm mx-auto">
                <div class="h-2 rounded-full bg-gray-800 overflow-hidden">
                  <div class="h-full bg-blue-500 transition-all" :style="{ width: `${progress}%` }"></div>
                </div>
                <p class="mt-1 text-xs text-gray-400 tabular-nums">Subiendo {{ uploadedCount }} de {{ uploadTotal }}…</p>
              </div>
            </div>
          </section>

          <!-- 2. review + send -->
          <section v-if="batch" class="rounded-2xl border border-gray-800 bg-gray-900 p-4 sm:p-5 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-white font-semibold mr-2">{{ batch.period_label }}</h3>
              <span class="text-[11px] px-2 py-0.5 rounded-full border" :class="batch.status === 'sent' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' : 'border-amber-500/40 bg-amber-500/10 text-amber-200'">
                {{ batch.status === 'sent' ? 'Enviado' : 'Borrador' }}
              </span>
              <span class="text-xs text-gray-400">{{ files.length }} archivo(s) · {{ readyCount }} listo(s) · {{ unmatchedCount }} sin empleado · {{ sentCount }} enviado(s)</span>
              <div class="ml-auto flex flex-wrap gap-2">
                <button
                  v-if="batch.status === 'draft' && !sentCount"
                  type="button"
                  class="px-3 py-2 rounded-lg border border-red-500/40 text-red-300 hover:bg-red-500/10 text-sm"
                  @click="discard"
                >Descartar</button>
                <button
                  v-if="readyCount"
                  type="button"
                  class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium disabled:opacity-50"
                  :disabled="sending || uploading"
                  @click="send"
                >{{ sending ? 'Enviando…' : `Enviar a ${readyCount} empleado(s)` }}</button>
              </div>
            </div>

            <div v-if="loading" class="space-y-2">
              <div v-for="i in 4" :key="i" class="h-12 rounded-lg bg-gray-800 animate-pulse"></div>
            </div>
            <p v-else-if="!files.length" class="text-sm text-gray-500 py-6 text-center">Todavía no subiste nóminas para este mes.</p>

            <div v-else class="rounded-xl border border-gray-800 divide-y divide-gray-800">
              <div v-for="f in sortedFiles" :key="f.id" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 sm:px-4 py-2.5">
                <svg class="w-6 h-6 shrink-0 text-red-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5" />
                </svg>
                <div class="min-w-0 flex-1 basis-40">
                  <div class="text-sm text-gray-100 truncate" :title="f.original_name">{{ f.original_name }}</div>
                  <div class="text-[11px]" :class="statusMeta(f).text">{{ statusMeta(f).label }}</div>
                </div>
                <!-- who gets it -->
                <select
                  :value="f.user?.id ?? ''"
                  :disabled="!!f.sent_at || batch.status !== 'draft'"
                  class="!mb-0 w-full sm:w-64 rounded-lg bg-gray-800 border px-2 py-1.5 text-sm text-white disabled:opacity-60"
                  :class="f.user ? 'border-gray-700' : 'border-amber-500/60'"
                  :aria-label="`Empleado de ${f.original_name}`"
                  @change="assign(f, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">— Elegir empleado —</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}{{ e.dni ? ` · ${e.dni}` : ' · sin DNI' }}</option>
                </select>
                <button
                  v-if="!f.sent_at && batch.status === 'draft'"
                  type="button"
                  class="shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-red-300 hover:bg-gray-800"
                  :aria-label="`Quitar ${f.original_name}`"
                  title="Quitar"
                  @click="removeFile(f)"
                >✕</button>
              </div>
            </div>

            <!-- active employees without a payslip in this batch -->
            <details v-if="missing.length && files.length" class="rounded-xl border border-gray-800 bg-gray-950/40 px-4 py-3">
              <summary class="cursor-pointer text-sm text-gray-300">
                {{ missing.length }} empleado(s) sin nómina este mes
              </summary>
              <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1 text-xs text-gray-400">
                <li v-for="e in missing" :key="e.id" class="truncate">{{ e.name }}{{ e.dni ? ` · ${e.dni}` : ' · sin DNI' }}</li>
              </ul>
            </details>
          </section>

          <!-- 3. history -->
          <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4 sm:p-5">
            <h3 class="text-white font-semibold mb-3">Envíos anteriores</h3>
            <p v-if="!history.length" class="text-sm text-gray-500">Todavía no hay envíos.</p>
            <ul v-else class="divide-y divide-gray-800">
              <li v-for="b in history" :key="b.id">
                <button type="button" class="w-full flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-left hover:bg-gray-800/40 rounded-lg px-2" @click="openBatch(b.id)">
                  <span class="text-sm text-gray-100 w-36">{{ b.period_label }}</span>
                  <span class="text-[11px] px-2 py-0.5 rounded-full border" :class="b.status === 'sent' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' : 'border-amber-500/40 bg-amber-500/10 text-amber-200'">
                    {{ b.status === 'sent' ? 'Enviado' : 'Borrador' }}
                  </span>
                  <span class="text-xs text-gray-400 tabular-nums">{{ b.sent_count }}/{{ b.files_count }} enviadas</span>
                  <span v-if="b.sent_at" class="ml-auto text-xs text-gray-500">{{ formatDateTime(b.sent_at) }}</span>
                </button>
              </li>
            </ul>
          </section>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })

const router = useRouter()
const { user, fetchUser, logout } = useAuth()
const { $swal } = useNuxtApp() as any
const sidebar = ref<{ open: boolean } | null>(null)

const pad = (n: number) => String(n).padStart(2, '0')
const today = new Date()
// payslips are usually for the month that just ended
const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1)
const period = ref(`${prev.getFullYear()}-${pad(prev.getMonth() + 1)}`)

const batch = ref<any | null>(null)
const files = ref<any[]>([])
const employees = ref<any[]>([])
const missing = ref<any[]>([])
const history = ref<any[]>([])
const loading = ref(false)
const sending = ref(false)
const dragging = ref(false)
const uploading = ref(false)
const uploadTotal = ref(0)
const uploadedCount = ref(0)
const progress = computed(() => (uploadTotal.value ? Math.round((uploadedCount.value / uploadTotal.value) * 100) : 0))
const folderInput = ref<HTMLInputElement | null>(null)
const filesInput = ref<HTMLInputElement | null>(null)

const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
const periodLabel = computed(() => {
  const [y, m] = period.value.split('-').map(Number)
  return m ? `${MONTHS[m - 1]} ${y}` : ''
})

const readyCount = computed(() => files.value.filter((f) => f.user && !f.sent_at).length)
const unmatchedCount = computed(() => files.value.filter((f) => !f.user && !f.sent_at).length)
const sentCount = computed(() => files.value.filter((f) => f.sent_at).length)
// unmatched first, so RRHH sees what needs attention
const sortedFiles = computed(() => [...files.value].sort((a, b) => Number(!!a.user) - Number(!!b.user) || a.original_name.localeCompare(b.original_name)))

function statusMeta(f: any) {
  if (f.sent_at) return { label: 'Enviada', text: 'text-emerald-300' }
  if (!f.user) return { label: f.match === 'ambiguous' ? 'El DNI coincide con varios empleados: elige uno' : 'No se encontró el DNI: elige el empleado', text: 'text-amber-300' }
  if (f.match === 'manual') return { label: 'Asignada a mano', text: 'text-sky-300' }
  return { label: 'Coincide por DNI', text: 'text-gray-400' }
}

const errorText = (e: any, fallback: string) => {
  const errors = e?.data?.errors
  return errors ? String(Object.values(errors).flat()[0]) : (e?.data?.message || fallback)
}
const formatDateTime = (iso: string) => parseDate(iso)?.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) ?? ''

function openSidebar() { if (sidebar.value) sidebar.value.open = true }
async function onLogout() { await logout(); router.push('/login') }

async function loadHistory() {
  try {
    const res: any = await $fetch('/api/payroll/batches')
    history.value = res?.batches || []
  } catch { history.value = [] }
}

async function openBatch(id: number) {
  loading.value = true
  try {
    const res: any = await $fetch(`/api/payroll/batches/${id}`)
    batch.value = res.batch
    period.value = res.batch.period
    files.value = res.files || []
    employees.value = res.employees || []
    missing.value = res.missing || []
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo abrir el lote'))
  } finally {
    loading.value = false
  }
}

/** Draft of the chosen month (created when needed). */
async function ensureBatch() {
  if (batch.value && batch.value.status === 'draft' && batch.value.period === period.value) return batch.value
  const res: any = await $fetch('/api/payroll/batches', { method: 'POST', body: { period: period.value } })
  await openBatch(res.batch.id)
  return batch.value
}

async function openPeriod() {
  const draft = history.value.find((b) => b.period === period.value && b.status === 'draft')
  if (draft) await openBatch(draft.id)
  else { batch.value = null; files.value = []; missing.value = [] }
}

function onPick(e: Event) {
  const input = e.target as HTMLInputElement
  void upload(Array.from(input.files || []))
  input.value = ''
}

// a dropped folder is read recursively
async function onDrop(e: DragEvent) {
  dragging.value = false
  const items = Array.from(e.dataTransfer?.items || [])
  const entries = items.map((i) => (i as any).webkitGetAsEntry?.()).filter(Boolean)
  if (!entries.length) return void upload(Array.from(e.dataTransfer?.files || []))
  const out: File[] = []
  const walk = async (entry: any): Promise<void> => {
    if (entry.isFile) {
      out.push(await new Promise<File>((resolve, reject) => entry.file(resolve, reject)))
    } else if (entry.isDirectory) {
      const reader = entry.createReader()
      let batchEntries: any[] = []
      do {
        batchEntries = await new Promise<any[]>((resolve) => reader.readEntries(resolve))
        for (const child of batchEntries) await walk(child)
      } while (batchEntries.length)
    }
  }
  for (const entry of entries) await walk(entry)
  void upload(out)
}

async function upload(list: File[]) {
  const pdfs = list.filter((f) => f.name.toLowerCase().endsWith('.pdf'))
  if (!pdfs.length) {
    $swal.toast('error', 'No hay archivos PDF para subir.')
    return
  }
  try {
    await ensureBatch()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo crear el lote'))
    return
  }
  uploading.value = true
  uploadTotal.value = pdfs.length
  uploadedCount.value = 0
  try {
    // chunks of 10 so each request stays well under the upload limit
    for (let i = 0; i < pdfs.length; i += 10) {
      const fd = new FormData()
      pdfs.slice(i, i + 10).forEach((f) => fd.append('files[]', f, f.name))
      const res: any = await $fetch(`/api/payroll/batches/${batch.value.id}/files`, { method: 'POST', body: fd })
      files.value.push(...(res?.files || []))
      uploadedCount.value = Math.min(pdfs.length, i + 10)
    }
    const matched = files.value.filter((f) => f.user && !f.sent_at).length
    $swal.toast('success', `${pdfs.length} nómina(s) subidas · ${matched} con empleado`)
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudieron subir algunas nóminas'))
  } finally {
    uploading.value = false
    await openBatch(batch.value.id)
    await loadHistory()
  }
}

async function assign(f: any, value: string) {
  try {
    const res: any = await $fetch(`/api/payroll/batches/${batch.value.id}/files/${f.id}`, { method: 'PUT', body: { user_id: value ? Number(value) : null } })
    Object.assign(f, res.file)
    await openBatch(batch.value.id) // refresh "sin nómina"
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo asignar'))
  }
}

async function removeFile(f: any) {
  try {
    await $fetch(`/api/payroll/batches/${batch.value.id}/files/${f.id}`, { method: 'DELETE' })
    files.value = files.value.filter((x) => x.id !== f.id)
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo quitar'))
  }
}

async function send() {
  const pending = unmatchedCount.value
  const ok = await $swal.confirm(
    'Enviar nóminas',
    `Se enviarán ${readyCount.value} nómina(s) de ${periodLabel.value}. Cada empleado la verá en Documentos → Nóminas con un aviso.` +
      (pending ? ` ${pending} sin empleado quedarán en el borrador.` : ''),
    'Enviar',
    'Cancelar',
  )
  if (!ok) return
  sending.value = true
  try {
    const res: any = await $fetch(`/api/payroll/batches/${batch.value.id}/send`, { method: 'POST' })
    $swal.toast('success', `${res.sent} nómina(s) enviadas`)
    await openBatch(batch.value.id)
    await loadHistory()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudieron enviar'))
  } finally {
    sending.value = false
  }
}

async function discard() {
  if (!(await $swal.confirm('Descartar', `¿Descartar el borrador de ${periodLabel.value} y sus archivos?`, 'Descartar', 'Cancelar'))) return
  try {
    await $fetch(`/api/payroll/batches/${batch.value.id}`, { method: 'DELETE' })
    batch.value = null
    files.value = []
    await loadHistory()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo descartar'))
  }
}

onBeforeMount(async () => {
  await fetchUser()
  if (!(user.value as any)?.can_manage_payroll) {
    router.push('/dashboard')
    return
  }
  await loadHistory()
  await openPeriod()
})
</script>
