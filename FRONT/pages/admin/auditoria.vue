<template>
  <div class="space-y-4">
    <div class="flex flex-col lg:flex-row gap-2 lg:items-center">
      <select v-model="action" class="px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load(1)">
        <option value="">Todas las acciones</option>
        <option v-for="a in actions" :key="a" :value="a">{{ auditLabel(a) }}</option>
      </select>
      <select v-model="companyId" class="px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" @change="load(1)">
        <option value="">Todas las empresas</option>
        <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
      <input
        v-model="q"
        type="search"
        placeholder="Buscar por usuario..."
        class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @input="debouncedLoad"
      />
      <label class="flex items-center gap-2 text-sm text-gray-300 whitespace-nowrap">
        <input v-model="includeLogins" type="checkbox" class="rounded border-gray-600 bg-gray-800" @change="load(1)" />
        Incluir inicios de sesión
      </label>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
      <table class="w-full text-sm min-w-[820px]">
        <thead class="text-xs text-gray-400 border-b border-gray-800">
          <tr>
            <th class="text-left font-medium px-4 py-3">Fecha</th>
            <th class="text-left font-medium px-4 py-3">Acción</th>
            <th class="text-left font-medium px-4 py-3">Quién</th>
            <th class="text-left font-medium px-4 py-3">Empresa</th>
            <th class="text-left font-medium px-4 py-3">Detalle</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Cargando...</td></tr>
          <tr v-else-if="!rows.length"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin registros.</td></tr>
          <tr v-for="a in rows" v-else :key="a.id" class="border-t border-gray-800 align-top">
            <td class="px-4 py-2.5 text-gray-400 whitespace-nowrap">{{ formatDateTime(a.created_at) }}</td>
            <td class="px-4 py-2.5 text-gray-100 whitespace-nowrap">{{ auditLabel(a.action) }}</td>
            <td class="px-4 py-2.5 text-gray-300 break-all">{{ a.actor_name }}</td>
            <td class="px-4 py-2.5 text-gray-300">{{ a.company?.name || '—' }}</td>
            <td class="px-4 py-2.5 text-[11px] text-gray-400 break-all">{{ describe(a) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <AdminPagination :meta="meta" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Auditoría' })

const route = useRoute()
const action = ref('')
const companyId = ref(String(route.query.company_id || ''))
const q = ref('')
const includeLogins = ref(false)
const rows = ref<any[]>([])
const meta = ref<any>(null)
const actions = ref<string[]>([])
const companies = ref<any[]>([])
const loading = ref(true)

function describe(a: any): string {
  if (a.description) return a.description
  const m = a.metadata || {}
  const parts: string[] = []
  for (const [k, v] of Object.entries(m)) {
    parts.push(`${k}: ${Array.isArray(v) ? v.join(', ') || '—' : typeof v === 'object' ? JSON.stringify(v) : v}`)
  }
  if (a.target_type) parts.unshift(`${a.target_type} #${a.target_id}`)
  return parts.join(' · ')
}

async function load(page = 1) {
  loading.value = true
  try {
    const res: any = await $fetch('/api/admin/audit', {
      query: {
        action: action.value || undefined,
        company_id: companyId.value || undefined,
        q: q.value || undefined,
        include_logins: includeLogins.value ? 1 : undefined,
        page,
      },
    })
    rows.value = res?.logs?.data || []
    meta.value = res?.logs || null
    actions.value = res?.actions || []
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
