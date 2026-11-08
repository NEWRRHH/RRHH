<template>
  <div class="space-y-4">
    <!-- Positions, laid out exactly like the dashboard: 3 columns, filled top to bottom -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div v-for="(column, c) in DASHBOARD_COLUMNS" :key="c" class="space-y-3">
        <div
          v-for="pos in column"
          :key="pos"
          class="relative rounded-xl border-2 border-dashed transition min-h-[76px]"
          :class="overTarget === pos ? 'border-blue-500 bg-blue-500/10' : 'border-gray-800 bg-gray-950/40'"
          @dragover.prevent="overTarget = pos"
          @dragleave="leave(pos)"
          @drop.prevent="drop(pos)"
        >
          <span class="absolute top-1.5 right-2 text-[10px] text-gray-600 tabular-nums" aria-hidden="true">{{ pos + 1 }}</span>
          <div
            v-if="modelValue[pos]"
            draggable="true"
            class="m-1.5 flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-900 px-3 py-2.5 cursor-grab active:cursor-grabbing select-none hover:border-gray-600"
            :class="dragging === modelValue[pos] ? 'opacity-40' : ''"
            :title="`Arrastra para mover ${meta(modelValue[pos]!).name}`"
            @dragstart="start($event, modelValue[pos]!)"
            @dragend="end"
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
          <div v-else class="h-full min-h-[64px] flex items-center justify-center text-[11px] text-gray-600">
            {{ dragging ? 'Soltar aquí' : 'Posición libre' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden cards tray -->
    <div
      class="rounded-xl border-2 border-dashed p-3 transition"
      :class="overTarget === 'hidden' ? 'border-red-400/60 bg-red-500/5' : 'border-gray-800'"
      @dragover.prevent="overTarget = 'hidden'"
      @dragleave="leave('hidden')"
      @drop.prevent="drop('hidden')"
    >
      <div class="text-xs text-gray-400 mb-2">
        Tarjetas ocultas
        <span class="text-gray-600">— arrastra aquí una tarjeta para ocultarla, o desde aquí a una posición para mostrarla</span>
      </div>
      <div v-if="hidden.length" class="flex flex-wrap gap-2">
        <div
          v-for="key in hidden"
          :key="key"
          draggable="true"
          class="flex items-center gap-2 rounded-lg border border-gray-700 bg-gray-900 px-2.5 py-1.5 cursor-grab active:cursor-grabbing select-none hover:border-gray-600"
          :class="dragging === key ? 'opacity-40' : ''"
          :title="`Arrastra a una posición para mostrar ${meta(key).name}`"
          @dragstart="start($event, key)"
          @dragend="end"
        >
          <span class="w-6 h-6 rounded-md flex items-center justify-center" :class="meta(key).tone">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" :d="meta(key).icon" />
            </svg>
          </span>
          <span class="text-xs text-gray-200">{{ meta(key).name }}</span>
        </div>
      </div>
      <p v-else class="text-xs text-gray-600">Todas las tarjetas están visibles.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DASHBOARD_COLUMNS, DASHBOARD_WIDGETS, DASHBOARD_WIDGET_KEYS, moveDashboardWidget, type DashboardSlots, type DashboardWidgetKey } from '~/utils/dashboardWidgets'

const props = defineProps<{ modelValue: DashboardSlots }>()
const emit = defineEmits<{ 'update:modelValue': [slots: DashboardSlots] }>()

const dragging = ref<DashboardWidgetKey | null>(null)
const overTarget = ref<number | 'hidden' | null>(null)

const meta = (key: DashboardWidgetKey) => DASHBOARD_WIDGETS[key]
const hidden = computed(() => DASHBOARD_WIDGET_KEYS.filter((k) => !props.modelValue.includes(k)))

function start(event: DragEvent, key: DashboardWidgetKey) {
  dragging.value = key
  event.dataTransfer?.setData('text/plain', key) // required by Firefox
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function end() {
  dragging.value = null
  overTarget.value = null
}

function leave(target: number | 'hidden') {
  if (overTarget.value === target) overTarget.value = null
}

function drop(target: number | 'hidden') {
  const key = dragging.value
  end()
  if (!key) return
  emit('update:modelValue', moveDashboardWidget(props.modelValue, key, target))
}
</script>
