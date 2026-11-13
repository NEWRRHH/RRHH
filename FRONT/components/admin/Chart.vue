<template>
  <section class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <h3 class="text-sm font-semibold text-white">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-gray-500 mt-0.5">{{ subtitle }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 text-[11px] px-2 py-1 rounded-md border border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 transition"
        :aria-pressed="showTable"
        @click="showTable = !showTable"
      >
        {{ showTable ? 'Ver gráfico' : 'Ver tabla' }}
      </button>
    </div>

    <!-- Table view: the accessible alternative to the chart -->
    <div v-if="showTable" class="max-h-72 overflow-auto rounded-lg border border-gray-800">
      <table class="w-full text-xs">
        <thead class="sticky top-0 bg-gray-900 text-gray-400">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ labelHeader }}</th>
            <th v-for="ds in datasets" :key="ds.label" class="text-right font-medium px-3 py-2">{{ ds.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(label, i) in labels" :key="label + i" class="border-t border-gray-800 text-gray-300">
            <td class="px-3 py-1.5">{{ label }}</td>
            <td v-for="ds in datasets" :key="ds.label" class="px-3 py-1.5 text-right tabular-nums">{{ ds.data[i] ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else :style="{ height: `${height}px` }" class="relative">
      <p v-if="isEmpty" class="absolute inset-0 flex items-center justify-center text-xs text-gray-500">Sin datos todavía</p>
      <ClientOnly v-else>
        <Bar v-if="type === 'bar'" :data="chartData" :options="chartOptions" :aria-label="title" role="img" />
        <Line v-else :data="chartData" :options="chartOptions" :aria-label="title" role="img" />
      </ClientOnly>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Bar, Line } from 'vue-chartjs'

type Dataset = { label: string; data: number[]; color?: string }

const props = withDefaults(defineProps<{
  title: string
  subtitle?: string
  type?: 'bar' | 'line'
  labels: string[]
  datasets: Dataset[]
  horizontal?: boolean
  height?: number
  labelHeader?: string
}>(), {
  type: 'bar',
  horizontal: false,
  height: 240,
  labelHeader: 'Fecha',
})

// Categorical slots (dark-mode steps), assigned in fixed order, validated
// against the #111827 card surface: blue, orange.
const SERIES = ['#3987e5', '#d95926']

const showTable = ref(false)
const isEmpty = computed(() => !props.labels.length || props.datasets.every((d) => d.data.every((v) => !v)))

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map((ds, i) => {
    const color = ds.color || SERIES[i % SERIES.length]
    return props.type === 'bar'
      ? {
          label: ds.label,
          data: ds.data,
          backgroundColor: color,
          hoverBackgroundColor: color,
          borderRadius: 4,
          borderSkipped: 'start' as const,
          maxBarThickness: 26,
          // 2px surface gap between adjacent bars
          borderColor: '#111827',
          borderWidth: { top: 0, bottom: 0, left: 1, right: 1 },
        }
      : {
          label: ds.label,
          data: ds.data,
          borderColor: color,
          backgroundColor: color,
          borderWidth: 2,
          tension: 0.25,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBorderWidth: 2,
          pointHoverBorderColor: '#111827',
          fill: false,
        }
  }),
}))

const chartOptions = computed(() => {
  const valueAxis = {
    beginAtZero: true,
    ticks: { precision: 0 },
    grid: { color: 'rgba(75, 85, 99, 0.25)' },
    border: { display: false },
  }
  const categoryAxis = {
    grid: { display: false },
    border: { color: 'rgba(75, 85, 99, 0.5)' },
    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: props.horizontal ? 10 : 8 },
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: props.horizontal ? ('y' as const) : ('x' as const),
    // line charts: crosshair-style tooltip for every series at the hovered x
    interaction: props.type === 'line' ? { mode: 'index' as const, intersect: false } : { mode: 'nearest' as const, intersect: true },
    plugins: {
      // one series: the title names it, no legend box
      legend: {
        display: props.datasets.length > 1,
        position: 'top' as const,
        align: 'end' as const,
        labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 8, boxHeight: 8, color: '#d1d5db' },
      },
      tooltip: {
        backgroundColor: '#030712',
        borderColor: '#374151',
        borderWidth: 1,
        titleColor: '#f3f4f6',
        bodyColor: '#d1d5db',
        padding: 10,
        cornerRadius: 8,
        usePointStyle: true,
      },
    },
    scales: props.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
  }
})
</script>
