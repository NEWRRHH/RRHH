<template>
  <button
    class="hidden lg:flex absolute -right-3 top-[52px] z-50 w-6 h-6 rounded-full bg-gray-800 border border-gray-700 items-center justify-center text-gray-400 hover:text-white hover:bg-blue-600 hover:border-blue-600 shadow-lg transition-all duration-200"
    title="Colapsar sidebar"
    aria-label="Colapsar sidebar"
    @click="$emit('toggle')"
  >
    <svg
      class="w-4 h-4 text-gray-300 transition-transform duration-300"
      :class="collapsed ? 'rotate-180' : ''"
      viewBox="0 0 16 16"
      fill="currentColor"
      role="img"
      aria-hidden="false"
    >
      <!-- filled chevron (more visible) -->
      <path d="M6.146 4.146a.5.5 0 0 1 .708 0L10.207 8l-3.353 3.854a.5.5 0 1 1-.742-.672L8.793 8 6.112 4.818a.5.5 0 0 1 .034-.672z"/>
    </svg>
    <span class="sr-only">Toggle sidebar</span>
  </button>
</template>

<script setup lang="ts">
defineProps<{ collapsed?: boolean }>()
defineEmits<{ toggle: [] }>()
</script>
