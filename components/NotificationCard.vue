<template>
  <!-- root element provides same fixed width as other cards -->
  <div class="w-full max-w-[230px] h-72 relative">
    <!-- modal overlay sits above everything -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 w-96 max-w-full">
        <div class="border border-gray-600 px-3 py-1 rounded mb-3 inline-block text-white font-semibold">
          {{ modalTitle }}
        </div>
        <textarea
          readonly
          class="w-full h-40 bg-gray-800 text-gray-200 p-2 rounded resize-none focus:outline-none"
        >{{ modalContent }}</textarea>
        <button @click="closeModal" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">Cerrar</button>
      </div>
    </div>

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
          <div v-if="unreadNotes && unreadNotes.length">
            <ul class="space-y-2">
              <li v-for="note in unreadNotes" :key="note.id">
                <div
                  @click="openModal(note)"
                  class="cursor-pointer p-2 rounded-lg border transition bg-gray-800 border-gray-700 text-white font-medium hover:bg-gray-800"
                >
                  <h4 class="text-sm font-semibold leading-tight mb-1 truncate">{{ note.sender_name || 'Desconocido' }}</h4>
                  <p class="text-xs leading-snug truncate">{{ note.content }}</p>
                </div>
              </li>
            </ul>
          </div>
          <div v-else class="text-gray-400 text-center mt-6">No hay notificaciones</div>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { PropType } from 'vue'
import { useAuth } from '../composables/useAuth'

const props = defineProps({
  notifications: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
  unreadCount: { type: Number as PropType<number>, default: 0 },
})

// auth helpers used for API calls
const { token, apiBase } = useAuth()

// only unread notes are shown in the card
const unreadNotes = computed(() => props.notifications.filter((n: any) => n.read === 0))

const emit = defineEmits(['notification-read'])

const showModal = ref(false)
const modalTitle = ref('Notificación')
const modalContent = ref('')

async function openModal(note: any) {
  modalTitle.value = note.sender_name || 'Notificación'
  modalContent.value = note.content || ''
  showModal.value = true

  // mark as read via conversation endpoint
  try {
    await $fetch(`${apiBase}/api/notifications/conversation/${note.sender_id}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    // update local state: remove this message
    note.read = true
    emit('notification-read', note.conversation_id || note.id)
  } catch (e) {
    console.error('failed to mark notification read', e)
  }
}

function closeModal() {
  showModal.value = false
  modalContent.value = ''
  modalTitle.value = 'Notificación'
}
</script>
