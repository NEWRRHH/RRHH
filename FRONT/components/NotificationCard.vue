<template>
  <div>
    <!-- modal overlay -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 w-80 max-w-full">
        <h3 class="text-lg text-white mb-4">Notificación</h3>
        <p class="text-gray-200 text-sm whitespace-pre-wrap">{{ modalContent }}</p>
        <button @click="closeModal" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">Cerrar</button>
      </div>
    </div>

    <div class="w-full max-w-[230px] h-72">
      <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex flex-col h-full shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
        <h3 class="text-xs text-gray-300 uppercase tracking-wide mb-3 flex items-center gap-2">
          Notificaciones
          <span v-if="unreadCount > 0" class="inline-block bg-red-600 text-white text-[10px] px-1 rounded-full">{{ unreadCount }}</span>
        </h3>

        <div v-if="loading" class="space-y-4 overflow-hidden">
          <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
          <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
          <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
        </div>

        <div v-else class="flex-1 overflow-y-auto">
          <div v-if="notifications && notifications.length">
            <ul class="space-y-2">
              <li
                v-for="note in notifications"
                :key="note.id"
                :class="['text-xs leading-snug cursor-pointer', note.read === 0 ? 'text-white font-medium' : 'text-gray-400']"
                @click="openModal(note)"
              >
                {{ note.content }}
              </li>
            </ul>
          </div>
          <div v-else class="text-gray-400 text-center mt-6">No hay notificaciones</div>
        </div>
      </div>
    </div>
  </div></template>

<script setup lang="ts">
import { ref } from 'vue'
import type { PropType } from 'vue'

const props = defineProps({
  notifications: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
  unreadCount: { type: Number as PropType<number>, default: 0 },
})

const showModal = ref(false)
const modalContent = ref('')

function openModal(note: any) {
  modalContent.value = note.content || ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  modalContent.value = ''
}
</script>
