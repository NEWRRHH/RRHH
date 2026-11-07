<template>
  <div class="space-y-6">
    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-900/20 px-4 py-3 text-sm text-red-200">{{ error }}</div>

    <!-- Headline numbers -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
      <AdminStatCard label="Empresas" :value="formatNumber(t.companies)" :hint="`${formatNumber(t.companies_active)} activas · ${formatNumber(t.companies_suspended)} suspendidas`" :loading="loading" />
      <AdminStatCard label="Empleados (todas las empresas)" :value="formatNumber(t.employees)" :hint="`+${formatNumber(t.employees_new_month)} este mes`" :loading="loading" />
      <AdminStatCard label="Empresas nuevas este mes" :value="formatNumber(t.companies_new_month)" :loading="loading" />
      <AdminStatCard label="Inicios de sesión hoy" :value="formatNumber(t.logins_today)" :loading="loading" />
      <AdminStatCard label="Fichajes hoy" :value="formatNumber(t.attendances_today)" :hint="`${formatNumber(t.working_now)} trabajando ahora`" :loading="loading" />
      <AdminStatCard
        label="Errores abiertos"
        :value="formatNumber(t.errors_open)"
        :hint="t.errors_open ? `${formatNumber(t.errors_today)} vistos hoy` : 'Sin errores pendientes'"
        :tone="t.errors_open ? 'warning' : 'neutral'"
        :loading="loading"
      />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <AdminChart
        title="Empresas nuevas por mes"
        subtitle="Últimos 12 meses"
        label-header="Mes"
        :labels="charts.companies_per_month.labels"
        :datasets="[{ label: 'Empresas nuevas', data: charts.companies_per_month.values }]"
      />
      <AdminChart
        title="Actividad diaria"
        subtitle="Últimos 30 días"
        type="line"
        :labels="shortDays(charts.activity_per_day.labels)"
        :datasets="[
          { label: 'Inicios de sesión', data: charts.activity_per_day.logins },
          { label: 'Fichajes', data: charts.activity_per_day.attendances },
        ]"
      />
      <AdminChart
        title="Empleados por empresa"
        subtitle="Top 10"
        label-header="Empresa"
        horizontal
        :height="280"
        :labels="charts.employees_by_company.labels"
        :datasets="[{ label: 'Empleados', data: charts.employees_by_company.values }]"
      />
      <AdminChart
        title="Errores por día"
        subtitle="Últimos 30 días (ocurrencias)"
        :height="280"
        :labels="shortDays(charts.errors_per_day.labels)"
        :datasets="[{ label: 'Errores', data: charts.errors_per_day.errors }]"
      />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-white">Últimos errores sin resolver</h3>
          <NuxtLink to="/admin/errores" class="text-xs text-violet-300 hover:text-violet-200">Ver todos</NuxtLink>
        </div>
        <p v-if="!loading && !latestErrors.length" class="text-sm text-gray-500">No hay errores pendientes.</p>
        <ul class="divide-y divide-gray-800">
          <li v-for="e in latestErrors" :key="e.id" class="py-2.5">
            <NuxtLink :to="`/admin/errores?id=${e.id}`" class="block group">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-gray-200 truncate group-hover:text-white">{{ e.message }}</span>
                <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-300 tabular-nums">×{{ e.occurrences }}</span>
              </div>
              <div class="text-[11px] text-gray-500 mt-0.5">{{ shortClass(e.exception) }} · {{ e.company?.name || 'Plataforma' }} · {{ timeAgo(e.last_seen_at) }}</div>
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-white">Últimas empresas registradas</h3>
          <NuxtLink to="/admin/empresas" class="text-xs text-violet-300 hover:text-violet-200">Ver todas</NuxtLink>
        </div>
        <p v-if="!loading && !latestCompanies.length" class="text-sm text-gray-500">Todavía no hay empresas.</p>
        <ul class="divide-y divide-gray-800">
          <li v-for="c in latestCompanies" :key="c.id" class="py-2.5">
            <NuxtLink :to="`/admin/empresas/${c.id}`" class="flex items-center justify-between gap-3 group">
              <div class="min-w-0">
                <div class="text-sm text-gray-200 truncate group-hover:text-white">{{ c.name }}</div>
                <div class="text-[11px] text-gray-500">{{ formatDate(c.created_at) }} · {{ c.users_count }} usuario(s)</div>
              </div>
              <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full" :class="c.status === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">
                {{ c.status === 'active' ? 'Activa' : 'Suspendida' }}
              </span>
            </NuxtLink>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin', title: 'Dashboard de plataforma' })

const loading = ref(true)
const error = ref('')
const t = ref<Record<string, number>>({})
const empty = { labels: [] as string[], values: [] as number[] }
const charts = ref<any>({
  companies_per_month: empty,
  employees_by_company: empty,
  activity_per_day: { labels: [], logins: [], attendances: [] },
  errors_per_day: { labels: [], errors: [] },
})
const latestErrors = ref<any[]>([])
const latestCompanies = ref<any[]>([])

// "2026-11-07" -> "07/11"
const shortDays = (labels: string[]) => labels.map((d) => `${d.slice(8, 10)}/${d.slice(5, 7)}`)
const shortClass = (cls: string) => String(cls || '').split('\\').pop()

onMounted(async () => {
  try {
    const res: any = await $fetch('/api/admin/dashboard')
    t.value = res?.totals || {}
    charts.value = res?.charts || charts.value
    latestErrors.value = res?.latest_errors || []
    latestCompanies.value = res?.latest_companies || []
  } catch (e: any) {
    error.value = e?.data?.message || 'No se pudo cargar el dashboard'
  } finally {
    loading.value = false
  }
})
</script>
