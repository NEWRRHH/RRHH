<template>
  <section class="rounded-2xl border border-gray-800 bg-gray-900 p-4 sm:p-5 space-y-5">
    <div>
      <h2 class="text-white font-semibold">Empresa y políticas</h2>
      <p class="text-sm text-gray-400 mt-1">Logo de la empresa, reglas de vacaciones y resumen mensual de asistencia.</p>
    </div>

    <!-- company logo: sidebar + browser tab icon (company admin) -->
    <div v-if="isAdmin" class="rounded-xl border border-gray-800 bg-gray-800/30 p-4 flex flex-wrap items-center gap-4">
      <div class="w-16 h-16 shrink-0 rounded-xl border border-gray-700 bg-white/5 flex items-center justify-center overflow-hidden">
        <img v-if="logoUrl" :src="logoUrl" alt="Logo de la empresa" class="w-full h-full object-contain" />
        <span v-else class="text-xs text-gray-500">Sin logo</span>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-gray-200">Logo de la empresa</p>
        <p class="text-xs text-gray-500">Se muestra en el menú lateral y como icono de la pestaña del navegador. PNG, JPG o WebP, máx. 1 MB.</p>
        <input id="settings-logo" ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="uploadLogo" />
        <div class="mt-2 flex flex-wrap gap-2">
          <label for="settings-logo" class="cursor-pointer px-3 py-1.5 rounded-lg border border-gray-700 text-sm text-gray-300 hover:bg-gray-800" :class="{ 'opacity-50 pointer-events-none': logoBusy }">
            {{ logoBusy ? 'Subiendo...' : (logoUrl ? 'Cambiar logo' : 'Subir logo') }}
          </label>
          <button v-if="logoUrl" type="button" class="px-3 py-1.5 rounded-lg text-sm text-gray-400 hover:text-red-300 disabled:opacity-50" :disabled="logoBusy" @click="removeLogo">Quitar</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="h-14 rounded-lg bg-gray-800 animate-pulse"></div>
    </div>

    <form v-else class="space-y-4" @submit.prevent="save">
      <fieldset class="rounded-xl border border-gray-800 bg-gray-800/30 p-4 space-y-2">
        <legend class="px-1 text-sm font-medium text-gray-200">Vacaciones</legend>
        <p class="text-xs text-gray-400">
          Las vacaciones solo se pueden pedir dentro del año en curso. Puedes permitir pedir también los primeros días del
          año siguiente: esos días se descuentan de las vacaciones del año en curso, no del siguiente.
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <label for="next-year-days" class="!mb-0 text-sm text-gray-200">Días del año siguiente que se pueden pedir</label>
          <input
            id="next-year-days"
            v-model.number="form.vacation_next_year_days"
            type="number"
            inputmode="numeric"
            min="0"
            :max="maxNextYearDays"
            required
            :disabled="!canEdit"
            class="!mb-0 w-20 rounded-lg bg-gray-800 border border-gray-700 px-3 py-1.5 text-white text-sm text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <p class="text-xs text-gray-500">{{ nextYearHint }}</p>
      </fieldset>

      <fieldset class="rounded-xl border border-gray-800 bg-gray-800/30 p-4 space-y-3">
        <legend class="px-1 text-sm font-medium text-gray-200">Resumen mensual de asistencia</legend>
        <p class="text-xs text-gray-400">
          El sistema lo genera automáticamente al terminar cada mes: un PDF con los fichajes del mes en
          Documentos &gt; Asistencia de cada empleado.
        </p>
        <label class="flex items-start gap-3 text-sm text-gray-200">
          <input
            v-model="form.attendance_summary_email"
            type="checkbox"
            class="mt-0.5 accent-blue-600"
            :disabled="!canEdit"
          />
          <span>
            Enviarlo también por email a cada empleado
            <span class="block text-xs text-gray-500">Con el PDF adjunto, a la dirección de su ficha.</span>
          </span>
        </label>
      </fieldset>

      <div v-if="canEdit" class="flex justify-end">
        <button
          type="submit"
          class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium disabled:opacity-50"
          :disabled="saving || !dirty"
        >
          {{ saving ? 'Guardando...' : 'Guardar políticas' }}
        </button>
      </div>
      <p v-else class="text-xs text-gray-500">Solo un administrador o RRHH puede cambiar estas políticas.</p>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

declare const $fetch: any
const { $swal } = useNuxtApp()
const { user, apiBase } = useAuth()

// --- company logo -------------------------------------------------------------
const isAdmin = computed(() => Boolean((user.value as any)?.is_admin))
const logoInput = ref<HTMLInputElement | null>(null)
const logoBusy = ref(false)
const logoUrl = computed(() => {
  const logo = String((user.value as any)?.company?.logo || '')
  return logo && logo.startsWith('/') ? `${apiBase}${logo}` : logo
})

// the sidebar and the favicon read user.company.logo: update it in place
function setCompanyLogo(logo: string | null) {
  if (user.value?.company) user.value = { ...user.value, company: { ...user.value.company, logo } }
}

async function uploadLogo(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoBusy.value = true
  try {
    const body = new FormData()
    body.append('logo', file)
    const res: any = await $fetch('/api/settings/company-logo', { method: 'POST', body })
    setCompanyLogo(res?.logo || null)
    $swal.toast('success', 'Logo actualizado')
  } catch (err: any) {
    $swal.toast('error', err?.data?.message || 'No se pudo subir el logo')
  } finally {
    logoBusy.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function removeLogo() {
  logoBusy.value = true
  try {
    await $fetch('/api/settings/company-logo', { method: 'DELETE' })
    setCompanyLogo(null)
    $swal.toast('success', 'Logo quitado')
  } catch (err: any) {
    $swal.toast('error', err?.data?.message || 'No se pudo quitar el logo')
  } finally {
    logoBusy.value = false
  }
}

type Policies = {
  vacation_next_year_days: number
  attendance_summary_email: boolean
}

const loading = ref(true)
const saving = ref(false)
const canEdit = ref(false)
const maxNextYearDays = ref(90)
const form = ref<Policies>({ vacation_next_year_days: 0, attendance_summary_email: true })
const saved = ref<Policies>({ ...form.value })

const dirty = computed(() => JSON.stringify(form.value) !== JSON.stringify(saved.value))

const nextYearHint = computed(() => {
  const n = Number(form.value.vacation_next_year_days) || 0
  const year = new Date().getFullYear()
  if (n <= 0) return `Ahora: solo hasta el 31/12/${year}.`
  const max = new Date(year, 11, 31)
  max.setDate(max.getDate() + n)
  const dd = String(max.getDate()).padStart(2, '0')
  const mm = String(max.getMonth() + 1).padStart(2, '0')
  return `Ahora: hasta el ${dd}/${mm}/${max.getFullYear()}, descontando de los días de ${year}.`
})

function apply(settings: any) {
  const value: Policies = {
    vacation_next_year_days: Number(settings?.vacation_next_year_days || 0),
    attendance_summary_email: Boolean(settings?.attendance_summary_email),
  }
  form.value = { ...value }
  saved.value = { ...value }
}

async function load() {
  loading.value = true
  try {
    const res: any = await $fetch('/api/settings/policies')
    canEdit.value = Boolean(res?.can_edit)
    maxNextYearDays.value = Number(res?.max_next_year_days || 90)
    apply(res?.settings)
  } catch (e) {
    console.error('policies load failed', e)
    $swal.toast('error', 'No se pudieron cargar las políticas')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const res: any = await $fetch('/api/settings/policies', { method: 'PUT', body: form.value })
    apply(res?.settings)
    $swal.toast('success', 'Políticas guardadas')
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudieron guardar las políticas')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
