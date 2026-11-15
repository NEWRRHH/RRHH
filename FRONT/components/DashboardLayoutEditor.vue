<template>
  <div ref="root" class="space-y-4">
    <!-- Positions, laid out exactly like the dashboard: 3 columns filled top to
         bottom; a row with a wide card is drawn as a band, like on the dashboard -->
    <div class="space-y-3">
      <div v-for="seg in segments" :key="seg.key" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div
          v-for="cell in seg.cells"
          :key="`${seg.key}-${cell.col}`"
          class="space-y-3 min-w-0"
          :class="[DASHBOARD_COL_START_SM[cell.col], DASHBOARD_COL_SPAN_SM[cell.span]]"
        >
          <div
            v-for="pos in cell.positions"
            :key="pos"
            :data-dash-drop="pos"
            data-dash-list
            class="relative rounded-xl border-2 border-dashed transition min-h-[76px] p-1.5"
            :class="dragging ? 'border-blue-500/40 bg-blue-500/5' : 'border-gray-800 bg-gray-950/40'"
          >
            <span class="pointer-events-none absolute top-1 right-2 text-[10px] text-gray-600 tabular-nums" aria-hidden="true">{{ pos + 1 }}</span>
            <div v-if="modelValue[pos]" :data-dash-key="modelValue[pos]" class="rounded-lg border border-gray-700 bg-gray-900 hover:border-gray-600">
              <div
                class="flex items-center gap-3 px-3 py-2.5 cursor-grab active:cursor-grabbing"
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

              <!-- width: how many columns the card spans on desktop -->
              <div data-dash-nodrag class="flex flex-wrap items-center gap-2 border-t border-gray-800 px-3 py-1.5 cursor-default">
                <span class="text-[11px] text-gray-500">Ancho</span>
                <div class="inline-flex rounded-md border border-gray-700 p-0.5" role="group" :aria-label="`Ancho de ${meta(modelValue[pos]!).name}`">
                  <button
                    v-for="w in DASHBOARD_MAX_WIDTH"
                    :key="w"
                    type="button"
                    class="min-w-[1.75rem] px-1.5 py-0.5 rounded text-[11px] tabular-nums transition"
                    :class="chosenWidth(modelValue[pos]!) === w ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-800'"
                    :aria-pressed="chosenWidth(modelValue[pos]!) === w"
                    :title="w === 1 ? '1 columna' : `${w} columnas`"
                    @click="setWidth(modelValue[pos]!, w)"
                  >{{ w }}</button>
                </div>
                <span
                  v-if="effectiveDashboardWidth(modelValue, widths, pos) < chosenWidth(modelValue[pos]!)"
                  class="text-[11px] text-amber-300/90"
                >
                  {{ narrowReason(pos) }}
                </span>
              </div>
            </div>
            <div v-else class="pointer-events-none h-full min-h-[56px] flex items-center justify-center text-[11px] text-gray-600">
              {{ dragging ? 'Soltar aquí' : 'Posición libre' }}
            </div>
          </div>
        </div>
      </div>
    </div>
    <p class="text-[11px] text-gray-500">
      El ancho se aplica en pantallas grandes. Una tarjeta ancha ocupa las posiciones libres a su derecha en la misma fila; en el celular todas las tarjetas se ven una debajo de la otra.
    </p>

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
import { computed, ref, watch } from 'vue'
import {
  DASHBOARD_COL_SPAN_SM, DASHBOARD_COL_START_SM, DASHBOARD_MAX_WIDTH, DASHBOARD_WIDGETS, DASHBOARD_WIDGET_KEYS,
  dashboardSegments, effectiveDashboardWidth, moveDashboardWidget,
  type DashboardSlots, type DashboardWidgetKey, type DashboardWidths,
} from '~/utils/dashboardWidgets'
import { useDashboardDrag } from '~/composables/useDashboardDrag'

const props = withDefaults(defineProps<{ modelValue: DashboardSlots; widths?: DashboardWidths }>(), {
  widths: () => ({}),
})
const emit = defineEmits<{
  'update:modelValue': [slots: DashboardSlots]
  'update:widths': [widths: DashboardWidths]
}>()

const root = ref<HTMLElement | null>(null)
const meta = (key: DashboardWidgetKey) => DASHBOARD_WIDGETS[key]
const hidden = computed(() => DASHBOARD_WIDGET_KEYS.filter((k) => !props.modelValue.includes(k)))
const segments = computed(() => dashboardSegments(props.modelValue, props.widths))

const chosenWidth = (key: DashboardWidgetKey) => props.widths[key] ?? 1
const setWidth = (key: DashboardWidgetKey, w: number) => emit('update:widths', { ...props.widths, [key]: w })

// why a card is narrower than chosen: right edge or a taken position
function narrowReason(pos: number) {
  const col = Math.floor(pos / 3)
  const wanted = chosenWidth(props.modelValue[pos]!)
  if (col + wanted > 3) return 'No entra: está muy a la derecha'
  return 'Se achica: hay una tarjeta a su derecha'
}

const { dragging, refresh } = useDashboardDrag(root, (key, target) => {
  emit('update:modelValue', moveDashboardWidget(props.modelValue, key, target))
})

// bands come and go with the widths: re-attach drag & drop to the new lists
watch(() => segments.value.map((s) => s.key).join('|'), () => refresh())
</script>
