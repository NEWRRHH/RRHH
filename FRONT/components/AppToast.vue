<template>
  <transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-2"
  >
    <div
      v-if="show && message"
      class="fixed top-20 right-6 z-[90] min-w-[260px] max-w-[360px] rounded-xl border px-4 py-3 shadow-lg"
      :class="type === 'success'
        ? 'bg-green-900/90 border-green-500/50 text-green-100'
        : 'bg-red-900/90 border-red-500/50 text-red-100'"
    >
      <div class="flex items-start gap-3">
        <div class="shrink-0 mt-0.5">
          <svg v-if="type === 'success'" class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          <svg v-else class="w-4 h-4 text-red-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <p class="text-sm leading-snug flex-1">{{ message }}</p>
        <button class="text-xs opacity-80 hover:opacity-100" @click="$emit('close')">X</button>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
defineProps<{
  show: boolean
  message: string
  type: 'success' | 'error'
}>()

defineEmits<{
  close: []
}>()
</script>

