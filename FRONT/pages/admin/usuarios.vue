<template>
  <div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-2">
      <input
        v-model="q"
        type="search"
        placeholder="Buscar por nombre o email..."
        class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @input="debouncedLoad"
      />
      <select v-model="companyId" class="px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load(1)">
        <option value="">Todas las empresas</option>
        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="status" class="px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load(1)">
        <option value="">Todos</option>
        <option value="active">Activos</option>
        <option value="disabled">Desactivados</option>
      </select>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
      <table class="w-full text-sm min-w-[820px]">
        <thead class="text-xs text-gray-400 border-b border-gray-800">
          <tr>
            <th class="text-left font-medium px-4 py-3">Usuario</th>
            <th class="text-left font-medium px-4 py-3">Empresa</th>
            <th class="text-left font-medium px-4 py-3">Rol</th>
            <th class="text-left font-medium px-4 py-3">Último acceso</th>
            <th class="text-right font-medium px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Cargando...</td></tr>
          <tr v-else-if="!rows.length"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay usuarios que coincidan.</td></tr>
          <tr v-for="u in rows" v-else :key="u.id" class="border-t border-gray-800">
            <td class="px-4 py-2.5">
              <div class="text-gray-100">{{ u.name }}</div>
              <div class="text-[11px] text-gray-500">{{ u.email }}</div>
            </td>
            <td class="px-4 py-2.5">
              <NuxtLink v-if="u.company" :to="`/admin/empresas/${u.company.id}`" class="text-gray-300 hover:text-white">{{ u.company.name }}</NuxtLink>
              <span v-else-if="u.is_super_admin" class="text-[11px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300">Superadmin</span>
              <span v-else class="text-gray-500">—</span>
            </td>
            <td class="px-4 py-2.5">
              <span class="text-gray-300">{{ u.role?.name || '—' }}</span>
              <span v-if="u.disabled_at" class="ml-2 text-[11px] px-2 py-0.5 rounded-full bg-red-500/15 text-red-300">Desactivado</span>
            </td>
            <td class="px-4 py-2.5 text-gray-400">{{ timeAgo(u.last_login_at) }}</td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <template v-if="!u.is_super_admin">
                <button class="text-xs px-2 py-1 rounded-md text-gray-300 hover:bg-gray-800" @click="resetPassword(u)">Resetear contraseña</button>
                <button class="text-xs px-2 py-1 rounded-md hover:bg-gray-800" :class="u.disabled_at ? 'text-emerald-300' : 'text-red-300'" @click="onToggle(u)">
                  {{ u.disabled_at ? 'Reactivar' : 'Desactivar' }}
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <AdminPagination :meta="meta" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Usuarios de todas las empresas' })

const { resetPassword, toggleDisabled } = useAdminUserActions()
const q = ref('')
const companyId = ref<string | number>('')
const status = ref('')
const rows = ref<any[]>([])
const meta = ref<any>(null)
const loading = ref(true)
const companies = ref<any[]>([])

async function load(page = 1) {
  loading.value = true
  try {
    const res: any = await $fetch('/api/admin/users', {
      query: { q: q.value || undefined, company_id: companyId.value || undefined, status: status.value || undefined, page },
    })
    rows.value = res?.data || []
    meta.value = res
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | null = null
function debouncedLoad() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => load(1), 300)
}

async function onToggle(u: any) {
  if (await toggleDisabled(u)) await load(meta.value?.current_page || 1)
}

onMounted(async () => {
  load(1)
  try {
    const res: any = await $fetch('/api/admin/companies', { query: { per_page: 100 } })
    companies.value = res?.data || []
  } catch {
    companies.value = []
  }
})
</script>
