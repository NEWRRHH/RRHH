<template>
  <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-3 pt-3 text-xs text-gray-400">
    <span>{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
    <div class="flex items-center gap-1">
      <button
        class="px-3 py-1.5 rounded-lg border border-gray-700 hover:bg-gray-800 disabled:opacity-40"
        :disabled="meta.current_page <= 1"
        @click="$emit('change', meta.current_page - 1)"
      >
        Anterior
      </button>
      <span class="px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button
        class="px-3 py-1.5 rounded-lg border border-gray-700 hover:bg-gray-800 disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page"
        @click="$emit('change', meta.current_page + 1)"
      >
        Siguiente
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  meta: { current_page: number; last_page: number; from: number | null; to: number | null; total: number } | null
}>()
defineEmits<{ change: [page: number] }>()
</script>
