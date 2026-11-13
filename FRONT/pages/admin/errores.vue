<template>
  <div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
      <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900">
        <button
          v-for="opt in statusOptions"
          :key="opt.id"
          class="px-3 py-1.5 rounded-lg text-sm"
          :class="status === opt.id ? 'bg-violet-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
          @click="status = opt.id; load(1)"
        >
          {{ opt.label }}
        </button>
      </div>
      <input
        v-model="q"
        type="search"
        placeholder="Buscar en mensaje, excepción o URL..."
        class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @input="debouncedLoad"
      />
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900 divide-y divide-gray-800">
      <p v-if="loading" class="px-5 py-6 text-center text-sm text-gray-500">Cargando...</p>
      <p v-else-if="!rows.length" class="px-5 py-6 text-center text-sm text-gray-500">
        {{ status === 'open' ? 'No hay errores pendientes.' : 'No hay errores que coincidan.' }}
      </p>
      <button v-for="e in rows" v-else :key="e.id" class="w-full text-left px-5 py-3 hover:bg-gray-800/40" @click="openDetail(e.id)">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm text-gray-100 truncate">{{ e.message }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5 truncate">
              {{ shortClass(e.exception) }} · {{ e.file }}:{{ e.line }} · {{ e.company?.name || 'Plataforma' }}
            </div>
          </div>
          <div class="shrink-0 text-right">
            <div class="text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-300 tabular-nums inline-block">×{{ e.occurrences }}</div>
            <div class="text-[11px] text-gray-500 mt-1">{{ timeAgo(e.last_seen_at) }}</div>
            <div v-if="e.resolved_at" class="text-[11px] text-emerald-300 mt-0.5">✓ Resuelto</div>
          </div>
        </div>
      </button>
    </div>
    <AdminPagination :meta="meta" @change="load" />

    <!-- Detail drawer -->
    <div v-if="detail" class="fixed inset-0 z-50 flex justify-end bg-black/60" @click.self="detail = null">
      <aside class="w-full max-w-2xl h-full overflow-y-auto bg-gray-900 border-l border-gray-700 p-6 space-y-4">
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-base font-semibold text-white break-words">{{ detail.message }}</h2>
          <button class="text-gray-400 hover:text-white" aria-label="Cerrar" @click="detail = null">✕</button>
        </div>
        <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-1.5 text-xs">
          <dt class="text-gray-500">Excepción</dt><dd class="text-gray-200 break-all">{{ detail.exception }}</dd>
          <dt class="text-gray-500">Archivo</dt><dd class="text-gray-200 break-all">{{ detail.file }}:{{ detail.line }}</dd>
          <dt class="text-gray-500">Petición</dt><dd class="text-gray-200 break-all">{{ detail.method }} {{ detail.url }}</dd>
          <dt class="text-gray-500">Empresa</dt><dd class="text-gray-200">{{ detail.company?.name || 'Plataforma' }}</dd>
          <dt class="text-gray-500">Usuario</dt><dd class="text-gray-200">{{ detail.user ? `${detail.user.name} <${detail.user.email}>` : '—' }}</dd>
          <dt class="text-gray-500">Ocurrencias</dt><dd class="text-gray-200">{{ detail.occurrences }}</dd>
          <dt class="text-gray-500">Primera vez</dt><dd class="text-gray-200">{{ formatDateTime(detail.created_at) }}</dd>
          <dt class="text-gray-500">Última vez</dt><dd class="text-gray-200">{{ formatDateTime(detail.last_seen_at) }}</dd>
        </dl>
        <div class="flex gap-2">
          <button
            v-if="!detail.resolved_at"
            class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm"
            @click="setResolved(detail.id, true)"
          >
            Marcar como resuelto
          </button>
          <button v-else class="px-3 py-2 rounded-lg border border-gray-700 text-gray-200 hover:bg-gray-800 text-sm" @click="setResolved(detail.id, false)">
            Reabrir
          </button>
        </div>
        <div>
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Traza</h3>
          <pre class="text-[11px] leading-relaxed text-gray-300 bg-gray-950 border border-gray-800 rounded-lg p-3 overflow-x-auto whitespace-pre">{{ detail.trace }}</pre>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Logs de errores' })

const route = useRoute()
const { $swal } = useNuxtApp() as any
const statusOptions = [
  { id: 'open', label: 'Abiertos' },
  { id: 'resolved', label: 'Resueltos' },
  { id: 'all', label: 'Todos' },
]
const status = ref('open')
const q = ref('')
const rows = ref<any[]>([])
const meta = ref<any>(null)
const loading = ref(true)
const detail = ref<any>(null)

const shortClass = (cls: string) => String(cls || '').split('\\').pop()

async function load(page = 1) {
  loading.value = true
  try {
    const res: any = await $fetch('/api/admin/errors', { query: { status: status.value, q: q.value || undefined, page } })
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

async function openDetail(id: number) {
  try {
    detail.value = await $fetch(`/api/admin/errors/${id}`)
  } catch {
    $swal.toast('error', 'No se pudo cargar el error')
  }
}

async function setResolved(id: number, resolved: boolean) {
  try {
    await $fetch(`/api/admin/errors/${id}/${resolved ? 'resolve' : 'reopen'}`, { method: 'POST' })
    $swal.toast('success', resolved ? 'Marcado como resuelto' : 'Error reabierto')
    detail.value = null
    await load(meta.value?.current_page || 1)
  } catch {
    $swal.toast('error', 'No se pudo actualizar el error')
  }
}

onMounted(async () => {
  await load(1)
  const id = Number(route.query.id || 0)
  if (id) await openDetail(id)
})
</script>
