<template>
  <div ref="root" class="space-y-4">
    <!-- Positions, laid out exactly like the dashboard: 3 columns, filled top to bottom -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div v-for="(column, c) in DASHBOARD_COLUMNS" :key="c" class="space-y-3">
        <div
          v-for="pos in column"
          :key="pos"
          :data-dash-drop="pos"
          data-dash-list
          class="relative rounded-xl border-2 border-dashed transition min-h-[76px] p-1.5"
          :class="dragging ? 'border-blue-500/40 bg-blue-500/5' : 'border-gray-800 bg-gray-950/40'"
        >
          <span class="pointer-events-none absolute top-1 right-2 text-[10px] text-gray-600 tabular-nums" aria-hidden="true">{{ pos + 1 }}</span>
          <div
            v-if="modelValue[pos]"
            :data-dash-key="modelValue[pos]"
            class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-900 px-3 py-2.5 cursor-grab active:cursor-grabbing hover:border-gray-600"
            :title="`Arrastra para mover ${meta(modelValue[pos]!).name}`"
          >
            <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center" :class="meta(modelValue[pos]!).tone">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="meta(modelValue[pos]!).icon" />
              </svg>
            </span>
            <span class="min-w-0">
              <span class="block text-sm font-medium text-gray-100 truncate">{{ meta(modelValue[pos]!).name }}</span>
              <span class="block text-[11px] text-gray-500 truncate">{{ meta(modelValue[pos]!).description }}</span>
            </span>
            <svg class="ml-auto w-4 h-4 shrink-0 text-gray-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M7 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm6 0a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM7 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm6 0a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM7 13a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm6 0a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" />
            </svg>
          </div>
          <div v-else class="pointer-events-none h-full min-h-[56px] flex items-center justify-center text-[11px] text-gray-600">
            {{ dragging ? 'Soltar aquí' : 'Posición libre' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden cards tray -->
    <div data-dash-drop="hidden" class="rounded-xl border-2 border-dashed p-3 transition" :class="dragging ? 'border-red-400/40 bg-red-500/5' : 'border-gray-800'">
      <div class="text-xs text-gray-400 mb-2">
        Tarjetas ocultas
        <span class="text-gray-600">— arrastra aquí una tarjeta para ocultarla, o desde aquí a una posición para mostrarla. En el celular, mantén pulsada la tarjeta para arrastrarla.</span>
      </div>
      <div data-dash-list class="flex flex-wrap gap-2 min-h-[36px]">
        <div
          v-for="key in hidden"
          :key="key"
          :data-dash-key="key"
          class="flex items-center gap-2 rounded-lg border border-gray-700 bg-gray-900 px-2.5 py-1.5 cursor-grab active:cursor-grabbing hover:border-gray-600"
          :title="`Arrastra a una posición para mostrar ${meta(key).name}`"
        >
          <span class="w-6 h-6 rounded-md flex items-center justify-center" :class="meta(key).tone">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" :d="meta(key).icon" />
            </svg>
          </span>
          <span class="text-xs text-gray-200">{{ meta(key).name }}</span>
        </div>
        <p v-if="!hidden.length" class="pointer-events-none text-xs text-gray-600 self-center">Todas las tarjetas están visibles.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DASHBOARD_COLUMNS, DASHBOARD_WIDGETS, DASHBOARD_WIDGET_KEYS, moveDashboardWidget, type DashboardSlots, type DashboardWidgetKey } from '~/utils/dashboardWidgets'
import { useDashboardDrag } from '~/composables/useDashboardDrag'

const props = defineProps<{ modelValue: DashboardSlots }>()
const emit = defineEmits<{ 'update:modelValue': [slots: DashboardSlots] }>()

const root = ref<HTMLElement | null>(null)
const meta = (key: DashboardWidgetKey) => DASHBOARD_WIDGETS[key]
const hidden = computed(() => DASHBOARD_WIDGET_KEYS.filter((k) => !props.modelValue.includes(k)))

const { dragging } = useDashboardDrag(root, (key, target) => {
  emit('update:modelValue', moveDashboardWidget(props.modelValue, key, target))
})
</script>
