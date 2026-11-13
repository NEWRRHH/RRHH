<template>
  <div class="space-y-4">
    <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900">
      <button
        v-for="t in tabs"
        :key="t.id"
        class="px-4 py-1.5 rounded-lg text-sm"
        :class="tab === t.id ? 'bg-violet-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
        @click="tab = t.id"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- PLANS -->
    <section v-if="tab === 'plans'" class="space-y-4">
      <p class="text-sm text-gray-400">Los límites se aplican al crear empleados y al subir documentos. Vacío = ilimitado. Las empresas nuevas reciben el plan por defecto.</p>
      <div class="rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
          <thead class="text-xs text-gray-400 border-b border-gray-800">
            <tr>
              <th class="text-left font-medium px-4 py-3">Código</th>
              <th class="text-left font-medium px-4 py-3">Nombre</th>
              <th class="text-right font-medium px-4 py-3">Máx. empleados</th>
              <th class="text-right font-medium px-4 py-3">Máx. almacenamiento</th>
              <th class="text-right font-medium px-4 py-3">Empresas</th>
              <th class="text-right font-medium px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in plans" :key="p.id" class="border-t border-gray-800">
              <td class="px-4 py-2.5 text-gray-400 font-mono text-xs">{{ p.code }}</td>
              <td class="px-4 py-2.5 text-gray-100">
                {{ p.name }}
                <span v-if="p.is_default" class="ml-2 text-[11px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300">Por defecto</span>
              </td>
              <td class="px-4 py-2.5 text-right tabular-nums text-gray-200">{{ p.max_employees ?? '∞' }}</td>
              <td class="px-4 py-2.5 text-right tabular-nums text-gray-200">{{ p.max_storage_mb ? formatBytes(p.max_storage_mb * 1048576) : '∞' }}</td>
              <td class="px-4 py-2.5 text-right tabular-nums text-gray-200">{{ p.companies_count }}</td>
              <td class="px-4 py-2.5 text-right whitespace-nowrap">
                <button class="text-xs px-2 py-1 rounded-md text-gray-300 hover:bg-gray-800" @click="editPlan(p)">Editar</button>
                <button class="text-xs px-2 py-1 rounded-md text-red-300 hover:bg-gray-800" @click="deletePlan(p)">Eliminar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <form class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-3" @submit.prevent="savePlan">
        <h3 class="text-sm font-semibold text-white">{{ planForm.id ? `Editar plan "${planForm.code}"` : 'Nuevo plan' }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <label class="text-xs text-gray-400">Código
            <input v-model="planForm.code" :disabled="!!planForm.id" required pattern="[a-z0-9_-]+" placeholder="ej: enterprise" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm disabled:opacity-50" />
          </label>
          <label class="text-xs text-gray-400">Nombre
            <input v-model="planForm.name" required class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Máx. empleados
            <input v-model.number="planForm.max_employees" type="number" min="1" placeholder="ilimitado" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Máx. almacenamiento (MB)
            <input v-model.number="planForm.max_storage_mb" type="number" min="1" placeholder="ilimitado" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input v-model="planForm.is_default" type="checkbox" class="!w-auto !m-0" /> Plan por defecto para empresas nuevas
          </label>
          <div class="flex gap-2">
            <button v-if="planForm.id" type="button" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 text-sm" @click="resetPlanForm">Cancelar</button>
            <button class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm">{{ planForm.id ? 'Guardar' : 'Crear plan' }}</button>
          </div>
        </div>
      </form>
    </section>

    <!-- NATIONAL HOLIDAYS -->
    <section v-else-if="tab === 'holidays'" class="space-y-4">
      <p class="text-sm text-gray-400">Catálogo de feriados por país. Cada empresa los copia desde su pantalla de Festivos con "Importar nacionales".</p>
      <div class="flex flex-wrap items-end gap-2">
        <label class="text-xs text-gray-400">País
          <input v-model="country" maxlength="2" list="countries" class="mt-1 w-24 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm uppercase" @change="loadNational" />
          <datalist id="countries"><option v-for="c in countries" :key="c" :value="c" /></datalist>
        </label>
        <label class="text-xs text-gray-400">Año
          <input v-model.number="nhYear" type="number" min="2000" max="2100" class="mt-1 w-28 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="loadNational" />
        </label>
        <label class="ml-auto text-xs text-gray-400">Importar CSV para {{ country.toUpperCase() }}
          <input type="file" accept=".csv,text/csv,text/plain" class="mt-1 block text-sm text-gray-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-700 file:text-white" @change="importNationalCsv" />
        </label>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[1fr,320px] gap-4">
        <div class="rounded-2xl border border-gray-800 bg-gray-900 divide-y divide-gray-800">
          <p v-if="!nationalHolidays.length" class="px-5 py-6 text-center text-sm text-gray-500">No hay feriados cargados para {{ country.toUpperCase() }} en {{ nhYear }}.</p>
          <div v-for="h in nationalHolidays" :key="h.id" class="px-5 py-2.5 flex items-center justify-between gap-3">
            <div>
              <div class="text-sm text-gray-100">{{ h.name }}</div>
              <div class="text-[11px] text-gray-500">
                {{ h.recurring ? `${h.date.slice(8, 10)}/${h.date.slice(5, 7)} · cada año` : formatDate(h.date) }} · {{ h.type === 'regional' ? 'Regional' : 'Nacional' }}
              </div>
            </div>
            <button class="text-xs px-2 py-1 rounded-md text-red-300 hover:bg-gray-800" @click="deleteNational(h)">Eliminar</button>
          </div>
        </div>

        <form class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-3 h-fit" @submit.prevent="addNational">
          <h3 class="text-sm font-semibold text-white">Agregar feriado</h3>
          <input v-model="nhForm.date" type="date" required class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          <input v-model="nhForm.name" required placeholder="Nombre" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          <select v-model="nhForm.type" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm">
            <option value="national">Nacional</option>
            <option value="regional">Regional</option>
          </select>
          <label class="flex items-center gap-2 text-sm text-gray-300"><input v-model="nhForm.recurring" type="checkbox" class="!w-auto !m-0" /> Se repite cada año</label>
          <button class="w-full px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm">Agregar a {{ country.toUpperCase() }}</button>
        </form>
      </div>
    </section>

    <!-- PERMISSIONS -->
    <section v-else class="space-y-4">
      <p class="text-sm text-gray-400">Catálogo de permisos que las empresas asignan a sus roles. Los códigos los usa la aplicación, por eso no se editan desde aquí.</p>
      <div class="rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
          <thead class="text-xs text-gray-400 border-b border-gray-800">
            <tr>
              <th class="text-left font-medium px-4 py-3">Código</th>
              <th class="text-left font-medium px-4 py-3">Permiso</th>
              <th class="text-right font-medium px-4 py-3">Roles que lo usan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in permissions" :key="p.id" class="border-t border-gray-800">
              <td class="px-4 py-2.5 text-gray-400 font-mono text-xs">{{ p.code }}</td>
              <td class="px-4 py-2.5">
                <div class="text-gray-100">{{ p.name }}</div>
                <div v-if="p.description" class="text-[11px] text-gray-500">{{ p.description }}</div>
              </td>
              <td class="px-4 py-2.5 text-right tabular-nums text-gray-200">{{ p.roles_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

definePageMeta({ layout: 'admin', title: 'Configuración global' })

const { $swal } = useNuxtApp() as any
const tabs = [
  { id: 'plans', label: 'Planes' },
  { id: 'holidays', label: 'Feriados nacionales' },
  { id: 'permissions', label: 'Permisos' },
]
const tab = ref('plans')
const errorText = (e: any, fallback: string) => {
  const errors = e?.data?.errors
  return errors ? Object.values(errors).flat().join(' ') : (e?.data?.message || fallback)
}

// ---- plans
const plans = ref<any[]>([])
const emptyPlan = () => ({ id: null as number | null, code: '', name: '', max_employees: null as number | null, max_storage_mb: null as number | null, is_default: false })
const planForm = ref(emptyPlan())

async function loadPlans() {
  plans.value = (await $fetch('/api/admin/plans').catch(() => [])) as any[]
}
function editPlan(p: any) {
  planForm.value = { id: p.id, code: p.code, name: p.name, max_employees: p.max_employees, max_storage_mb: p.max_storage_mb, is_default: p.is_default }
}
function resetPlanForm() { planForm.value = emptyPlan() }
async function savePlan() {
  const f = planForm.value
  const body = { code: f.code, name: f.name, max_employees: f.max_employees || null, max_storage_mb: f.max_storage_mb || null, is_default: f.is_default }
  try {
    if (f.id) await $fetch(`/api/admin/plans/${f.id}`, { method: 'PUT', body })
    else await $fetch('/api/admin/plans', { method: 'POST', body })
    $swal.toast('success', 'Plan guardado')
    resetPlanForm()
    await loadPlans()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo guardar el plan'))
  }
}
async function deletePlan(p: any) {
  if (!(await $swal.confirm('Eliminar plan', `¿Eliminar el plan "${p.name}"?`, 'Eliminar', 'Cancelar'))) return
  try {
    await $fetch(`/api/admin/plans/${p.id}`, { method: 'DELETE' })
    await loadPlans()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo eliminar'))
  }
}

// ---- national holidays
const country = ref('ES')
const nhYear = ref(new Date().getFullYear())
const countries = ref<string[]>([])
const nationalHolidays = ref<any[]>([])
const nhForm = ref({ date: '', name: '', type: 'national', recurring: false })

async function loadNational() {
  try {
    const res: any = await $fetch('/api/admin/national-holidays', { query: { country_code: country.value.toUpperCase(), year: nhYear.value } })
    nationalHolidays.value = res?.holidays || []
    countries.value = res?.countries || []
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudieron cargar los feriados'))
  }
}
async function addNational() {
  try {
    await $fetch('/api/admin/national-holidays', { method: 'POST', body: { ...nhForm.value, country_code: country.value } })
    nhForm.value = { date: '', name: '', type: 'national', recurring: false }
    await loadNational()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo agregar'))
  }
}
async function deleteNational(h: any) {
  if (!(await $swal.confirm('Eliminar feriado', `¿Eliminar "${h.name}" del catálogo?`, 'Eliminar', 'Cancelar'))) return
  await $fetch(`/api/admin/national-holidays/${h.id}`, { method: 'DELETE' }).catch(() => null)
  await loadNational()
}
async function importNationalCsv(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const build = (mode: string) => {
    const data = new FormData()
    data.append('file', file)
    data.append('mode', mode)
    data.append('country_code', country.value)
    data.append('on_duplicate', 'skip')
    return data
  }
  try {
    const preview: any = await $fetch('/api/admin/national-holidays/import', { method: 'POST', body: build('preview') })
    const s = preview.summary || {}
    const ok = await $swal.confirm(
      'Importar CSV',
      `${s.new || 0} nuevos, ${s.duplicate || 0} ya existen (se mantienen), ${s.error || 0} con errores (se ignoran). ¿Importar?`,
      'Importar',
      'Cancelar',
    )
    if (!ok) return
    const res: any = await $fetch('/api/admin/national-holidays/import', { method: 'POST', body: build('commit') })
    $swal.toast('success', `${res.created} feriado(s) importados`)
    await loadNational()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo importar el CSV'))
  } finally {
    input.value = ''
  }
}

// ---- permissions
const permissions = ref<any[]>([])
async function loadPermissions() {
  permissions.value = (await $fetch('/api/admin/permissions').catch(() => [])) as any[]
}

watch(tab, (t) => {
  if (t === 'holidays' && !nationalHolidays.value.length) loadNational()
  if (t === 'permissions' && !permissions.value.length) loadPermissions()
})

onMounted(loadPlans)
</script>
