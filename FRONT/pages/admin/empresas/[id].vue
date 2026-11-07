<template>
  <div class="space-y-6">
    <NuxtLink to="/admin/empresas" class="text-xs text-gray-400 hover:text-white">← Empresas</NuxtLink>

    <div v-if="loading" class="h-24 rounded-2xl bg-gray-900 border border-gray-800 animate-pulse"></div>
    <div v-else-if="!company" class="text-sm text-gray-400">Empresa no encontrada.</div>

    <template v-else>
      <!-- Header -->
      <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5 flex flex-col lg:flex-row lg:items-center gap-4">
        <div class="flex-1 min-w-0">
          <div v-if="!editing" class="flex items-center gap-3 flex-wrap">
            <h2 class="text-xl font-semibold text-white">{{ company.name }}</h2>
            <span class="text-[11px] px-2 py-0.5 rounded-full" :class="statusClass">{{ statusLabel }}</span>
            <span v-if="company.plan" class="text-[11px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300">Plan {{ company.plan }}</span>
          </div>
          <form v-else class="flex flex-col sm:flex-row gap-2" @submit.prevent="saveEdit">
            <input v-model="editForm.name" required minlength="2" class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
            <input v-model="editForm.plan" placeholder="Plan" class="sm:w-40 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-sm" />
            <button class="px-3 py-2 rounded-lg bg-violet-600 text-white text-sm">Guardar</button>
            <button type="button" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 text-sm" @click="editing = false">Cancelar</button>
          </form>
          <div class="text-xs text-gray-500 mt-1">{{ company.slug }} · alta {{ formatDate(company.created_at) }}</div>
        </div>
        <div v-if="!company.deleted_at" class="flex flex-wrap gap-2">
          <button
            class="px-3 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm disabled:opacity-50"
            :disabled="company.status !== 'active' || busy"
            title="Ver la aplicación como la ve el administrador de esta empresa"
            @click="impersonate"
          >
            Entrar como empresa
          </button>
          <button v-if="!editing" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800 text-sm" @click="startEdit">Editar</button>
          <button
            class="px-3 py-2 rounded-lg border text-sm disabled:opacity-50"
            :class="company.status === 'active' ? 'border-amber-500/40 text-amber-300 hover:bg-amber-500/10' : 'border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10'"
            :disabled="busy"
            @click="toggleStatus"
          >
            {{ company.status === 'active' ? 'Suspender' : 'Reactivar' }}
          </button>
          <button class="px-3 py-2 rounded-lg border border-red-500/40 text-red-300 hover:bg-red-500/10 text-sm disabled:opacity-50" :disabled="busy" @click="remove">Eliminar</button>
        </div>
      </section>

      <!-- Usage -->
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <AdminStatCard label="Usuarios" :value="formatNumber(usage.users)" />
        <AdminStatCard label="Activos (30 días)" :value="formatNumber(usage.users_active_30d)" />
        <AdminStatCard label="Documentos" :value="formatNumber(usage.documents)" />
        <AdminStatCard label="Almacenamiento" :value="formatBytes(usage.storage_bytes)" />
        <AdminStatCard label="Fichajes (30 días)" :value="formatNumber(usage.attendances_30d)" />
        <AdminStatCard label="Solicitudes pendientes" :value="formatNumber(usage.requests_pending)" />
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <!-- Users -->
        <section class="xl:col-span-2 rounded-2xl border border-gray-800 bg-gray-900 overflow-x-auto">
          <h3 class="text-sm font-semibold text-white px-5 pt-5 pb-3">Usuarios</h3>
          <table class="w-full text-sm min-w-[640px]">
            <thead class="text-xs text-gray-400 border-y border-gray-800">
              <tr>
                <th class="text-left font-medium px-5 py-2">Usuario</th>
                <th class="text-left font-medium px-3 py-2">Rol</th>
                <th class="text-left font-medium px-3 py-2">Último acceso</th>
                <th class="text-right font-medium px-5 py-2">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id" class="border-t border-gray-800">
                <td class="px-5 py-2.5">
                  <div class="text-gray-100">{{ u.name }}</div>
                  <div class="text-[11px] text-gray-500">{{ u.email }}</div>
                </td>
                <td class="px-3 py-2.5">
                  <span class="text-gray-300">{{ u.role?.name || 'Sin rol' }}</span>
                  <span v-if="u.disabled_at" class="ml-2 text-[11px] px-2 py-0.5 rounded-full bg-red-500/15 text-red-300">Desactivado</span>
                </td>
                <td class="px-3 py-2.5 text-gray-400">{{ timeAgo(u.last_login_at) }}</td>
                <td class="px-5 py-2.5 text-right whitespace-nowrap">
                  <button class="text-xs px-2 py-1 rounded-md text-gray-300 hover:bg-gray-800" @click="onReset(u)">Resetear contraseña</button>
                  <button class="text-xs px-2 py-1 rounded-md hover:bg-gray-800" :class="u.disabled_at ? 'text-emerald-300' : 'text-red-300'" @click="onToggle(u)">
                    {{ u.disabled_at ? 'Reactivar' : 'Desactivar' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Roles -->
        <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
          <h3 class="text-sm font-semibold text-white mb-3">Roles</h3>
          <ul class="space-y-2">
            <li v-for="r in roles" :key="r.id" class="flex items-center justify-between text-sm">
              <span class="text-gray-200">{{ r.name }} <span v-if="r.is_admin" class="text-[11px] text-amber-300">(admin)</span></span>
              <span class="text-xs text-gray-500 tabular-nums">{{ r.users_count }} usuario(s)</span>
            </li>
          </ul>
        </section>
      </div>

      <!-- Audit -->
      <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-white">Actividad reciente</h3>
          <NuxtLink :to="`/admin/auditoria?company_id=${company.id}`" class="text-xs text-violet-300 hover:text-violet-200">Ver todo</NuxtLink>
        </div>
        <p v-if="!audit.length" class="text-sm text-gray-500">Sin actividad registrada.</p>
        <ul class="divide-y divide-gray-800">
          <li v-for="a in audit" :key="a.id" class="py-2 flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-200">{{ auditLabel(a.action) }} <span class="text-gray-500">· {{ a.actor_name }}</span></span>
            <span class="text-xs text-gray-500 shrink-0">{{ formatDateTime(a.created_at) }}</span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Detalle de empresa' })

const route = useRoute()
const { $swal } = useNuxtApp() as any
const { fetchUser } = useAuth()
const { resetPassword, toggleDisabled } = useAdminUserActions()

const id = computed(() => Number(route.params.id))
const loading = ref(true)
const busy = ref(false)
const company = ref<any>(null)
const usage = ref<any>({})
const users = ref<any[]>([])
const roles = ref<any[]>([])
const audit = ref<any[]>([])
const editing = ref(false)
const editForm = ref({ name: '', plan: '' })

const statusLabel = computed(() => (company.value?.deleted_at ? 'Eliminada' : company.value?.status === 'active' ? 'Activa' : 'Suspendida'))
const statusClass = computed(() =>
  company.value?.deleted_at ? 'bg-red-500/15 text-red-300' : company.value?.status === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300',
)

async function load() {
  loading.value = true
  try {
    const res: any = await $fetch(`/api/admin/companies/${id.value}`)
    company.value = res.company
    usage.value = res.usage || {}
    users.value = res.users || []
    roles.value = res.roles || []
    audit.value = res.audit || []
  } catch {
    company.value = null
  } finally {
    loading.value = false
  }
}

function startEdit() {
  editForm.value = { name: company.value.name, plan: company.value.plan || '' }
  editing.value = true
}

async function saveEdit() {
  try {
    await $fetch(`/api/admin/companies/${id.value}`, { method: 'PUT', body: { name: editForm.value.name, plan: editForm.value.plan || null } })
    editing.value = false
    $swal.toast('success', 'Empresa actualizada')
    await load()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo guardar')
  }
}

async function toggleStatus() {
  const suspending = company.value.status === 'active'
  if (suspending) {
    const ok = await $swal.confirm('Suspender empresa', `Ningún usuario de ${company.value.name} podrá entrar hasta que la reactives.`, 'Suspender', 'Cancelar')
    if (!ok) return
  }
  busy.value = true
  try {
    await $fetch(`/api/admin/companies/${id.value}/${suspending ? 'suspend' : 'reactivate'}`, { method: 'POST' })
    $swal.toast('success', suspending ? 'Empresa suspendida' : 'Empresa reactivada')
    await load()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo cambiar el estado')
  } finally {
    busy.value = false
  }
}

async function remove() {
  const ok = await $swal.confirm('Eliminar empresa', `${company.value.name} quedará eliminada y sus usuarios no podrán entrar. Los datos se conservan.`, 'Eliminar', 'Cancelar')
  if (!ok) return
  busy.value = true
  try {
    await $fetch(`/api/admin/companies/${id.value}`, { method: 'DELETE' })
    $swal.toast('success', 'Empresa eliminada')
    await navigateTo('/admin/empresas')
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo eliminar')
  } finally {
    busy.value = false
  }
}

async function impersonate() {
  const ok = await $swal.confirm(
    'Entrar como empresa',
    `Vas a ver la aplicación como el administrador de ${company.value.name}. La sesión dura 2 horas y queda registrada en la auditoría.`,
    'Entrar',
    'Cancelar',
  )
  if (!ok) return
  busy.value = true
  try {
    await $fetch('/api/auth/impersonate', { method: 'POST', body: { company_id: id.value } })
    await fetchUser()
    await navigateTo('/dashboard')
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo entrar en la empresa')
  } finally {
    busy.value = false
  }
}

async function onReset(u: any) {
  await resetPassword(u)
}

async function onToggle(u: any) {
  if (await toggleDisabled(u)) await load()
}

onMounted(load)
</script>
