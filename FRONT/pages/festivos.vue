<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition" @click="openSidebar">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="text-base font-semibold text-white">Festivos</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-4 sm:p-6 overflow-auto">
        <div class="max-w-7xl mx-auto space-y-4">
          <!-- Toolbar -->
          <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center rounded-xl border border-gray-700 bg-gray-900">
              <button class="px-3 py-2 text-gray-300 hover:text-white" aria-label="Año anterior" @click="changeYear(-1)">‹</button>
              <span class="px-2 text-sm font-semibold text-white tabular-nums">{{ year }}</span>
              <button class="px-3 py-2 text-gray-300 hover:text-white" aria-label="Año siguiente" @click="changeYear(1)">›</button>
            </div>
            <span class="text-sm text-gray-400">{{ holidays.length }} festivo(s)</span>
            <div class="ml-auto flex flex-wrap gap-2">
              <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800 text-sm" @click="openNational">Importar nacionales</button>
              <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800 text-sm" @click="openImport">Importar CSV</button>
              <button class="px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm" @click="openEditor(null, `${year}-01-01`)">Agregar festivo</button>
            </div>
          </div>
          <p class="text-xs text-gray-500">Haz clic en un día del calendario para agregar un festivo, o en uno marcado para editarlo. Los festivos no cuentan como días de vacaciones ni como ausencias.</p>

          <div class="grid grid-cols-1 xl:grid-cols-[1fr,320px] gap-4">
            <!-- Year calendar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <section v-for="m in months" :key="m.index" class="rounded-xl border border-gray-800 bg-gray-900 p-3">
                <h3 class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">{{ m.name }}</h3>
                <div class="grid grid-cols-7 gap-0.5 text-center text-[10px] text-gray-500 mb-1">
                  <span v-for="d in weekDays" :key="d">{{ d }}</span>
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                  <span v-for="n in m.offset" :key="'pad' + n"></span>
                  <button
                    v-for="day in m.days"
                    :key="day.iso"
                    type="button"
                    class="h-7 rounded-md text-xs tabular-nums transition"
                    :class="dayClass(day)"
                    :title="byDate[day.iso]?.name || ''"
                    :aria-label="byDate[day.iso] ? `${day.iso}: ${byDate[day.iso].name}` : `Agregar festivo el ${day.iso}`"
                    @click="onDayClick(day.iso)"
                  >
                    {{ day.n }}
                  </button>
                </div>
              </section>
            </div>

            <!-- List -->
            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 h-fit">
              <h3 class="text-sm font-semibold text-white mb-3">Listado {{ year }}</h3>
              <p v-if="loading" class="text-sm text-gray-500">Cargando...</p>
              <p v-else-if="!holidays.length" class="text-sm text-gray-500">No hay festivos cargados para este año.</p>
              <ul class="divide-y divide-gray-800">
                <li v-for="h in holidays" :key="h.id + h.date">
                  <button class="w-full text-left py-2 flex items-start justify-between gap-2 hover:bg-gray-800/40 rounded px-1" @click="openEditor(h)">
                    <span>
                      <span class="block text-sm text-gray-100">{{ h.name }}</span>
                      <span class="block text-[11px] text-gray-500">{{ formatLongDate(h.date) }}<template v-if="h.recurring"> · cada año</template></span>
                    </span>
                    <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full" :class="typeBadge(h.type)">{{ typeLabel(h.type) }}</span>
                  </button>
                </li>
              </ul>
            </section>
          </div>
        </div>
      </main>
    </div>

    <!-- Add / edit -->
    <div v-if="editor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="editor = null">
      <form class="w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-6 space-y-4" @submit.prevent="saveEditor">
        <h2 class="text-lg font-semibold text-white">{{ editor.id ? 'Editar festivo' : 'Nuevo festivo' }}</h2>
        <div v-if="editorError" class="rounded-lg bg-red-900/30 border border-red-500/30 px-3 py-2 text-sm text-red-200">{{ editorError }}</div>
        <label class="block text-xs text-gray-400">Fecha
          <input v-model="editor.date" type="date" required class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
        </label>
        <label class="block text-xs text-gray-400">Nombre
          <input v-model="editor.name" required maxlength="150" placeholder="ej: Navidad" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
        </label>
        <label class="block text-xs text-gray-400">Tipo
          <select v-model="editor.type" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm">
            <option value="national">Nacional</option>
            <option value="regional">Regional / local</option>
            <option value="company">De empresa</option>
          </select>
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-300">
          <input v-model="editor.recurring" type="checkbox" class="!w-auto !m-0 rounded border-gray-600 bg-gray-800" />
          Se repite todos los años en la misma fecha
        </label>
        <div class="flex justify-between gap-2 pt-2">
          <button v-if="editor.id" type="button" class="px-3 py-2 rounded-lg border border-red-500/40 text-red-300 hover:bg-red-500/10 text-sm" @click="removeEditor">Eliminar</button>
          <span v-else></span>
          <div class="flex gap-2">
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" @click="editor = null">Cancelar</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm disabled:opacity-50">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
          </div>
        </div>
      </form>
    </div>

    <!-- CSV import -->
    <div v-if="importOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="importOpen = false">
      <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-700 bg-gray-900 p-6 space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-white">Importar festivos desde CSV</h2>
            <p class="text-xs text-gray-400 mt-1">
              Columnas: <code>fecha;nombre;tipo;recurrente</code>. Fecha en DD/MM/AAAA o AAAA-MM-DD; tipo nacional, regional o empresa; recurrente si/no. Separador ; o , (máx. 1 MB, 500 filas).
            </p>
          </div>
          <a href="/api/holidays/template" class="shrink-0 text-xs px-3 py-1.5 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800">Descargar plantilla</a>
        </div>

        <input type="file" accept=".csv,text/csv,text/plain" class="block w-full text-sm text-gray-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-700 file:text-white" @change="onFile" />
        <div v-if="importError" class="rounded-lg bg-red-900/30 border border-red-500/30 px-3 py-2 text-sm text-red-200">{{ importError }}</div>

        <template v-if="preview">
          <div class="flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-1 rounded-full bg-emerald-500/15 text-emerald-300">{{ preview.summary.new }} nuevos</span>
            <span class="px-2 py-1 rounded-full bg-amber-500/15 text-amber-300">{{ preview.summary.duplicate }} ya existen</span>
            <span class="px-2 py-1 rounded-full bg-red-500/15 text-red-300">{{ preview.summary.error }} con errores (se ignoran)</span>
          </div>
          <div class="max-h-72 overflow-auto rounded-lg border border-gray-800">
            <table class="w-full text-xs">
              <thead class="sticky top-0 bg-gray-900 text-gray-400">
                <tr>
                  <th class="text-left font-medium px-3 py-2">Línea</th>
                  <th class="text-left font-medium px-3 py-2">Fecha</th>
                  <th class="text-left font-medium px-3 py-2">Nombre</th>
                  <th class="text-left font-medium px-3 py-2">Tipo</th>
                  <th class="text-left font-medium px-3 py-2">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in preview.rows" :key="r.line" class="border-t border-gray-800">
                  <td class="px-3 py-1.5 text-gray-500">{{ r.line }}</td>
                  <td class="px-3 py-1.5 text-gray-200 tabular-nums">{{ r.date ? formatShortDate(r.date) : '—' }}</td>
                  <td class="px-3 py-1.5 text-gray-200">{{ r.name || '—' }}<span v-if="r.recurring" class="text-gray-500"> · cada año</span></td>
                  <td class="px-3 py-1.5 text-gray-400">{{ r.type ? typeLabel(r.type) : '—' }}</td>
                  <td class="px-3 py-1.5">
                    <span v-if="r.status === 'new'" class="text-emerald-300">✓ Nuevo</span>
                    <span v-else-if="r.status === 'duplicate'" class="text-amber-300">⚠ Ya existe</span>
                    <span v-else class="text-red-300">✕ {{ r.error }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <fieldset v-if="preview.summary.duplicate" class="flex flex-wrap gap-4 text-sm text-gray-300">
            <legend class="sr-only">Duplicados</legend>
            <label class="flex items-center gap-2"><input v-model="onDuplicate" type="radio" value="skip" class="!w-auto !m-0" /> Mantener los existentes</label>
            <label class="flex items-center gap-2"><input v-model="onDuplicate" type="radio" value="overwrite" class="!w-auto !m-0" /> Sobrescribir con el CSV</label>
          </fieldset>
        </template>

        <div class="flex justify-end gap-2 pt-2">
          <button class="px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" @click="importOpen = false">Cancelar</button>
          <button
            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm disabled:opacity-50"
            :disabled="!preview || importing || (!preview.summary.new && !(preview.summary.duplicate && onDuplicate === 'overwrite'))"
            @click="commitImport"
          >
            {{ importing ? 'Importando...' : 'Importar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- National catalog -->
    <div v-if="nationalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="nationalOpen = false">
      <form class="w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-6 space-y-4" @submit.prevent="importNational">
        <h2 class="text-lg font-semibold text-white">Importar feriados nacionales</h2>
        <p class="text-xs text-gray-400">Copia los feriados del país que mantiene la plataforma. Las fechas que ya tengas no se tocan.</p>
        <div class="grid grid-cols-2 gap-3">
          <label class="text-xs text-gray-400">País (código)
            <input v-model="national.country_code" required maxlength="2" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm uppercase" />
          </label>
          <label class="text-xs text-gray-400">Año
            <input v-model.number="national.year" type="number" min="2000" max="2100" required class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" @click="nationalOpen = false">Cancelar</button>
          <button type="submit" :disabled="importing" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm disabled:opacity-50">Importar</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

type HolidayRow = { id: number; date: string; original_date: string; name: string; type: string; recurring: boolean }

const router = useRouter()
const { user, logout } = useAuth()
const { $swal } = useNuxtApp() as any
const sidebar = ref<{ open: boolean } | null>(null)

const year = ref(new Date().getFullYear())
const holidays = ref<HolidayRow[]>([])
const companyCountry = ref('ES')
const loading = ref(true)

const weekDays = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const pad = (n: number) => String(n).padStart(2, '0')
const todayIso = (() => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` })()

const months = computed(() => monthNames.map((name, index) => {
  const first = new Date(year.value, index, 1)
  const total = new Date(year.value, index + 1, 0).getDate()
  return {
    index,
    name,
    offset: (first.getDay() + 6) % 7, // Monday first
    days: Array.from({ length: total }, (_, i) => {
      const date = new Date(year.value, index, i + 1)
      return { n: i + 1, iso: `${year.value}-${pad(index + 1)}-${pad(i + 1)}`, weekend: date.getDay() === 0 || date.getDay() === 6 }
    }),
  }
}))

const byDate = computed<Record<string, HolidayRow>>(() => Object.fromEntries(holidays.value.map((h) => [h.date, h])))

function dayClass(day: { iso: string; weekend: boolean }) {
  const h = byDate.value[day.iso]
  if (h) return 'bg-blue-600 text-white font-semibold hover:bg-blue-500'
  const base = day.weekend ? 'text-gray-600 hover:bg-gray-800' : 'text-gray-300 hover:bg-gray-800'
  return day.iso === todayIso ? `${base} ring-1 ring-gray-500` : base
}

const typeLabel = (t: string) => ({ national: 'Nacional', regional: 'Regional', company: 'Empresa' } as Record<string, string>)[t] || t
const typeBadge = (t: string) => t === 'national' ? 'bg-blue-500/15 text-blue-300' : t === 'regional' ? 'bg-violet-500/15 text-violet-300' : 'bg-gray-700 text-gray-300'
const formatLongDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })
const formatShortDate = (iso: string) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)}`

function openSidebar() { if (sidebar.value) sidebar.value.open = true }
async function onLogout() { await logout(); router.push('/login') }

async function load() {
  loading.value = true
  try {
    const res: any = await $fetch('/api/holidays', { query: { year: year.value } })
    holidays.value = res?.holidays || []
    companyCountry.value = res?.country_code || 'ES'
  } catch (e: any) {
    if (e?.status === 403) return router.push('/dashboard')
    holidays.value = []
  } finally {
    loading.value = false
  }
}

function changeYear(delta: number) {
  year.value += delta
  load()
}

// ---- add / edit
const editor = ref<any>(null)
const editorError = ref('')
const saving = ref(false)

function openEditor(h: HolidayRow | null, date?: string) {
  editorError.value = ''
  editor.value = h
    ? { id: h.id, date: h.original_date, name: h.name, type: h.type, recurring: h.recurring }
    : { id: null, date: date || todayIso, name: '', type: 'company', recurring: false }
}

function onDayClick(iso: string) {
  const h = byDate.value[iso]
  openEditor(h || null, iso)
}

async function saveEditor() {
  saving.value = true
  editorError.value = ''
  try {
    const body = { date: editor.value.date, name: editor.value.name, type: editor.value.type, recurring: editor.value.recurring }
    if (editor.value.id) await $fetch(`/api/holidays/${editor.value.id}`, { method: 'PUT', body })
    else await $fetch('/api/holidays', { method: 'POST', body })
    editor.value = null
    $swal.toast('success', 'Festivo guardado')
    await load()
  } catch (e: any) {
    const errors = e?.data?.errors
    editorError.value = errors?.date ? 'Ya hay un festivo en esa fecha.' : (e?.data?.message || 'No se pudo guardar')
  } finally {
    saving.value = false
  }
}

async function removeEditor() {
  const ok = await $swal.confirm('Eliminar festivo', `¿Eliminar "${editor.value.name}"?`, 'Eliminar', 'Cancelar')
  if (!ok) return
  try {
    await $fetch(`/api/holidays/${editor.value.id}`, { method: 'DELETE' })
    editor.value = null
    $swal.toast('success', 'Festivo eliminado')
    await load()
  } catch {
    $swal.toast('error', 'No se pudo eliminar')
  }
}

// ---- CSV import
const importOpen = ref(false)
const importFile = ref<File | null>(null)
const preview = ref<any>(null)
const importError = ref('')
const importing = ref(false)
const onDuplicate = ref<'skip' | 'overwrite'>('skip')

function openImport() {
  importOpen.value = true
  importFile.value = null
  preview.value = null
  importError.value = ''
  onDuplicate.value = 'skip'
}

function formData(mode: 'preview' | 'commit') {
  const data = new FormData()
  data.append('file', importFile.value as File)
  data.append('mode', mode)
  data.append('on_duplicate', onDuplicate.value)
  return data
}

async function onFile(event: Event) {
  importFile.value = (event.target as HTMLInputElement).files?.[0] || null
  preview.value = null
  importError.value = ''
  if (!importFile.value) return
  try {
    preview.value = await $fetch('/api/holidays/import', { method: 'POST', body: formData('preview') })
  } catch (e: any) {
    const errors = e?.data?.errors
    importError.value = errors ? Object.values(errors).flat().join(' ') : (e?.data?.message || 'No se pudo leer el archivo')
  }
}

async function commitImport() {
  if (!importFile.value) return
  importing.value = true
  try {
    const res: any = await $fetch('/api/holidays/import', { method: 'POST', body: formData('commit') })
    importOpen.value = false
    $swal.toast('success', `${res.created} creados, ${res.updated} actualizados`)
    await load()
  } catch (e: any) {
    importError.value = e?.data?.message || 'No se pudo importar'
  } finally {
    importing.value = false
  }
}

// ---- national catalog
const nationalOpen = ref(false)
const national = ref({ country_code: 'ES', year: year.value })

function openNational() {
  national.value = { country_code: companyCountry.value, year: year.value }
  nationalOpen.value = true
}

async function importNational() {
  importing.value = true
  try {
    const res: any = await $fetch('/api/holidays/import-national', { method: 'POST', body: national.value })
    nationalOpen.value = false
    if (!res.available) $swal.toast('info', 'La plataforma aún no tiene feriados cargados para ese país y año')
    else $swal.toast('success', `${res.created} feriado(s) importados`)
    year.value = national.value.year
    await load()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo importar')
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  if (!(user.value as any)?.can_manage_holidays) {
    router.push('/dashboard')
    return
  }
  load()
})
</script>
