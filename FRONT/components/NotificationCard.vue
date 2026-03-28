<template>
  <div class="w-full relative">
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

    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-6 -right-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

      <h3 class="text-xs text-gray-300 uppercase tracking-wide mb-3 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4a2 2 0 01-.6-1.4V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
        </span>
        Notificaciones
        <span v-if="unreadCount > 0" class="inline-block bg-red-600 text-white text-[10px] px-1 rounded-full">{{ unreadCount }}</span>
      </h3>

      <div v-if="loading" class="space-y-4 overflow-hidden">
        <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
        <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
        <div class="h-6 bg-gray-800 animate-pulse rounded"></div>
      </div>

      <div v-else class="max-h-44 overflow-y-auto pr-1 notification-scroll">
        <div v-if="unreadNotes && unreadNotes.length">
          <ul class="space-y-2">
            <li v-for="note in unreadNotes" :key="note.message_id || note.id">
              <div
                @click="openModal(note)"
                class="cursor-pointer p-2 rounded-lg border transition bg-gray-800 border-gray-700 text-white font-medium hover:bg-gray-700"
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
declare const $fetch: any
import type { PropType } from 'vue'
import { useAuth } from '../composables/useAuth'

const props = defineProps({
  notifications: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false },
  unreadCount: { type: Number as PropType<number>, default: 0 },
})

const { token, apiBase } = useAuth()

const unreadNotes = computed(() =>
  (props.notifications || []).filter((n: any) => n.read === 0 || n.read === false || n.read === null)
)

const emit = defineEmits(['notification-read'])

const showModal = ref(false)
const modalTitle = ref('Notificacion')
const modalContent = ref('')

async function openModal(note: any) {
  modalTitle.value = note.sender_name || 'Notificacion'
  modalContent.value = note.content || ''
  showModal.value = true

  try {
    await $fetch(`${apiBase}/api/notifications/conversation/${note.sender_id}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    note.read = true
    emit('notification-read', note.conversation_id || note.id)
  } catch (e) {
    console.error('failed to mark notification read', e)
  }
}

function closeModal() {
  showModal.value = false
  modalContent.value = ''
  modalTitle.value = 'Notificacion'
}
</script>

<style scoped>
.notification-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(245, 158, 11, 0.55) rgba(31, 41, 55, 0.4);
}

.notification-scroll::-webkit-scrollbar {
  width: 8px;
}

.notification-scroll::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.4);
  border-radius: 9999px;
}

.notification-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, rgba(251, 191, 36, 0.85), rgba(245, 158, 11, 0.85));
  border-radius: 9999px;
}
</style>
