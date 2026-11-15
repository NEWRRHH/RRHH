<template>
  <!-- The company's national holidays catalog: "Importar nacionales" (Festivos tab) copies from here -->
  <div class="space-y-4">
    <p class="text-sm text-gray-400">
      Lista de feriados de tu país. Desde "Festivos del año" → <strong class="text-gray-200">Importar nacionales</strong> se copian al calendario de la empresa,
      donde puedes marcar cuáles se trabajan.
    </p>

    <div class="flex flex-wrap items-end gap-2">
      <label class="!mb-0 text-xs text-gray-400 font-normal">País
        <input
          v-model="country"
          maxlength="2"
          list="nh-countries"
          class="!mb-0 mt-1 block w-24 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm uppercase"
          @change="load"
        />
        <datalist id="nh-countries"><option v-for="c in countries" :key="c" :value="c" /></datalist>
      </label>
      <label class="!mb-0 text-xs text-gray-400 font-normal">Año
        <input v-model.number="year" type="number" min="2000" max="2100" class="!mb-0 mt-1 block w-28 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load" />
      </label>
      <label class="!mb-0 sm:ml-auto text-xs text-gray-400 font-normal">Importar CSV para {{ country.toUpperCase() }}
        <input
          type="file"
          accept=".csv,text/csv,text/plain"
          class="mt-1 block max-w-full text-sm text-gray-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-700 file:text-white"
          @change="importCsv"
        />
      </label>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr,320px] gap-4">
      <div class="rounded-2xl border border-gray-800 bg-gray-900 divide-y divide-gray-800">
        <div v-if="loading" class="p-4 space-y-2">
          <div v-for="i in 4" :key="i" class="h-10 rounded-lg bg-gray-800 animate-pulse"></div>
        </div>
        <p v-else-if="!holidays.length" class="px-5 py-6 text-center text-sm text-gray-500">
          No hay feriados cargados para {{ country.toUpperCase() }} en {{ year }}.
        </p>
        <template v-else>
        <div v-for="h in holidays" :key="h.id" class="px-4 sm:px-5 py-2.5 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm text-gray-100 truncate">{{ h.name }}</div>
            <div class="text-[11px] text-gray-500">
              {{ h.recurring ? `${h.date.slice(8, 10)}/${h.date.slice(5, 7)} · cada año` : formatDate(h.date) }} · {{ h.type === 'regional' ? 'Regional' : 'Nacional' }}
            </div>
          </div>
          <RowActionsMenu :label="`Acciones de ${h.name}`">
            <button type="button" class="row-action" @click="edit(h)">Editar</button>
            <button type="button" class="row-action row-action--danger" @click="remove(h)">Eliminar</button>
          </RowActionsMenu>
        </div>
        </template>
      </div>

      <form class="rounded-2xl border border-gray-800 bg-gray-900 p-4 sm:p-5 space-y-3 h-fit" @submit.prevent="save">
        <h3 class="text-sm font-semibold text-white">{{ form.id ? 'Editar feriado' : 'Agregar feriado' }}</h3>
        <input v-model="form.date" type="date" required aria-label="Fecha" class="!mb-0 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
        <input v-model="form.name" required maxlength="150" placeholder="Nombre" aria-label="Nombre" class="!mb-0 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
        <select v-model="form.type" aria-label="Tipo" class="!mb-0 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm">
          <option value="national">Nacional</option>
          <option value="regional">Regional</option>
        </select>
        <label class="!mb-0 !font-normal flex items-center gap-2 text-sm text-gray-300">
          <input v-model="form.recurring" type="checkbox" class="accent-blue-500 w-4 h-4" /> Se repite cada año
        </label>
        <div class="flex gap-2">
          <button v-if="form.id" type="button" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" @click="resetForm">Cancelar</button>
          <button :disabled="saving" class="flex-1 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm disabled:opacity-50">
            {{ saving ? 'Guardando...' : form.id ? 'Guardar cambios' : `Agregar a ${country.toUpperCase()}` }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

const { $swal } = useNuxtApp() as any
const { user } = useAuth()

const country = ref(String((user.value as any)?.company?.country_code || 'ES'))
const year = ref(new Date().getFullYear())
const countries = ref<string[]>([])
const holidays = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const emptyForm = () => ({ id: null as number | null, date: '', name: '', type: 'national', recurring: false })
const form = ref(emptyForm())

const errorText = (e: any, fallback: string) => {
  const errors = e?.data?.errors
  return errors ? String(Object.values(errors).flat()[0]) : (e?.data?.message || fallback)
}
const formatDate = (iso: string) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)}`

async function load() {
  loading.value = true
  try {
    const res: any = await $fetch('/api/holidays/national', { query: { country_code: country.value.toUpperCase(), year: year.value } })
    holidays.value = res?.holidays || []
    countries.value = res?.countries || []
    if (res?.country_code) country.value = res.country_code
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudieron cargar los feriados'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.value = emptyForm()
}

function edit(h: any) {
  form.value = { id: h.id, date: String(h.date).slice(0, 10), name: h.name, type: h.type || 'national', recurring: !!h.recurring }
}

async function save() {
  saving.value = true
  try {
    const { id, ...body } = form.value
    await $fetch(id ? `/api/holidays/national/${id}` : '/api/holidays/national', {
      method: id ? 'PUT' : 'POST',
      body: { ...body, country_code: country.value },
    })
    $swal.toast('success', id ? 'Feriado actualizado' : 'Feriado agregado')
    resetForm()
    await load()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo guardar'))
  } finally {
    saving.value = false
  }
}

async function remove(h: any) {
  if (!(await $swal.confirm('Eliminar feriado', `¿Eliminar "${h.name}" de la lista?`, 'Eliminar', 'Cancelar'))) return
  try {
    await $fetch(`/api/holidays/national/${h.id}`, { method: 'DELETE' })
    if (form.value.id === h.id) resetForm()
    await load()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo eliminar'))
  }
}

async function importCsv(event: Event) {
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
    const preview: any = await $fetch('/api/holidays/national/import', { method: 'POST', body: build('preview') })
    const s = preview.summary || {}
    const ok = await $swal.confirm(
      'Importar CSV',
      `${s.new || 0} nuevos, ${s.duplicate || 0} ya existen (se mantienen), ${s.error || 0} con errores (se ignoran). ¿Importar?`,
      'Importar',
      'Cancelar',
    )
    if (!ok) return
    const res: any = await $fetch('/api/holidays/national/import', { method: 'POST', body: build('commit') })
    $swal.toast('success', `${res.created} feriado(s) importados`)
    await load()
  } catch (e: any) {
    $swal.toast('error', errorText(e, 'No se pudo importar el CSV'))
  } finally {
    input.value = ''
  }
}

onMounted(load)
</script>
