<template>
  <div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
      <input
        v-model="q"
        type="search"
        placeholder="Buscar por nombre o slug..."
        class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @input="debouncedLoad"
      />
      <select v-model="status" class="px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load(1)">
        <option value="">Todos los estados</option>
        <option value="active">Activas</option>
        <option value="suspended">Suspendidas</option>
      </select>
      <button class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm font-medium" @click="openCreate">Nueva empresa</button>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
      <table class="w-full text-sm min-w-[760px]">
        <thead class="text-xs text-gray-400 border-b border-gray-800">
          <tr>
            <th class="text-left font-medium px-4 py-3">Empresa</th>
            <th class="text-left font-medium px-4 py-3">Estado</th>
            <th class="text-left font-medium px-4 py-3">Plan</th>
            <th class="text-right font-medium px-4 py-3">Usuarios</th>
            <th class="text-left font-medium px-4 py-3">Último acceso</th>
            <th class="text-left font-medium px-4 py-3">Alta</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="px-4 py-6 text-center text-gray-500">Cargando...</td></tr>
          <tr v-else-if="!rows.length"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay empresas que coincidan.</td></tr>
          <tr
            v-for="c in rows"
            v-else
            :key="c.id"
            class="border-t border-gray-800 hover:bg-gray-800/40 cursor-pointer"
            @click="navigateTo(`/admin/empresas/${c.id}`)"
          >
            <td class="px-4 py-3">
              <div class="text-white font-medium">{{ c.name }}</div>
              <div class="text-[11px] text-gray-500">{{ c.slug }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="text-[11px] px-2 py-0.5 rounded-full" :class="c.status === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">
                {{ c.status === 'active' ? 'Activa' : 'Suspendida' }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-300">{{ c.plan || '—' }}</td>
            <td class="px-4 py-3 text-right tabular-nums text-gray-200">{{ c.users_count }}</td>
            <td class="px-4 py-3 text-gray-400">{{ timeAgo(c.last_login_at) }}</td>
            <td class="px-4 py-3 text-gray-400">{{ formatDate(c.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <AdminPagination :meta="meta" @change="load" />

    <!-- Create company -->
    <div v-if="createOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="createOpen = false">
      <form class="w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-900 p-6 space-y-4" @submit.prevent="submitCreate">
        <h2 class="text-lg font-semibold text-white">Nueva empresa</h2>
        <p class="text-xs text-gray-400">Se crea con sus roles por defecto, tipos de evento, una jornada L–V 9 a 18 y su usuario administrador.</p>
        <div v-if="createError" class="rounded-lg bg-red-900/30 border border-red-500/30 px-3 py-2 text-sm text-red-200">{{ createError }}</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="sm:col-span-2 text-xs text-gray-400">Nombre de la empresa
            <input v-model="form.company_name" required minlength="2" maxlength="150" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Plan (opcional)
            <input v-model="form.plan" maxlength="50" placeholder="ej: basico, pro" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Nombre del administrador
            <input v-model="form.admin_name" required class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Email del administrador
            <input v-model="form.admin_email" type="email" required class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
          <label class="text-xs text-gray-400">Contraseña inicial
            <input v-model="form.admin_password" type="text" required minlength="8" class="mt-1 w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
          </label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" @click="createOpen = false">Cancelar</button>
          <button type="submit" :disabled="creating" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm disabled:opacity-50">
            {{ creating ? 'Creando...' : 'Crear empresa' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Empresas' })

const { $swal } = useNuxtApp() as any
const q = ref('')
const status = ref('')
const rows = ref<any[]>([])
const meta = ref<any>(null)
const loading = ref(true)

const createOpen = ref(false)
const creating = ref(false)
const createError = ref('')
const emptyForm = () => ({ company_name: '', plan: '', admin_name: '', admin_email: '', admin_password: '' })
const form = ref(emptyForm())

async function load(page = 1) {
  loading.value = true
  try {
    const res: any = await $fetch('/api/admin/companies', { query: { q: q.value || undefined, status: status.value || undefined, page } })
    rows.value = res?.data || []
    meta.value = res
  } catch {
    rows.value = []
    $swal.toast('error', 'No se pudieron cargar las empresas')
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | null = null
function debouncedLoad() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => load(1), 300)
}

function openCreate() {
  form.value = emptyForm()
  createError.value = ''
  createOpen.value = true
}

async function submitCreate() {
  creating.value = true
  createError.value = ''
  try {
    const res: any = await $fetch('/api/admin/companies', { method: 'POST', body: { ...form.value, plan: form.value.plan || null } })
    createOpen.value = false
    $swal.toast('success', 'Empresa creada')
    await navigateTo(`/admin/empresas/${res.company.id}`)
  } catch (e: any) {
    const errors = e?.data?.errors
    createError.value = errors ? Object.values(errors).flat().join(' ') : (e?.data?.message || 'No se pudo crear la empresa')
  } finally {
    creating.value = false
  }
}

onMounted(() => load(1))
</script>
