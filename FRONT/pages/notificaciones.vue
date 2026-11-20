<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
    <!-- sidebar y top bar reuse existing layout structure -->
    <AppSidebar ref="sidebar" @logout="onLogout" />
    <div class="flex-1 h-screen flex flex-col min-w-0 relative z-10 overflow-hidden">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white hidden sm:block">Chat</h1>
        <button
          class="sm:hidden px-2 py-1.5 rounded-lg border border-gray-700 text-[10px] text-gray-200 hover:bg-gray-800 transition shrink-0"
          @click="showUserPanel = !showUserPanel"
        >
          {{ showUserPanel ? 'Cerrar' : 'Usuarios' }}
        </button>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-0 overflow-hidden flex">
        <div
          v-if="showUserPanel && isMobile"
          class="absolute inset-0 z-20 bg-black/50"
          @click="showUserPanel = false"
        />
        <!-- conversation list + users -->
        <aside
          :class="[
            'border-r border-gray-800 bg-gray-900 flex-shrink-0 overflow-y-auto',
            isMobile
              ? (showUserPanel ? 'absolute left-0 top-0 bottom-0 z-30 w-72 max-w-[85vw]' : 'hidden')
              : 'w-64'
          ]"
        >
          <div class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wide">Usuarios</div>
          <ul>
            <li
              v-for="u in allUsers"
              :key="u.id"
              @click="selectConversation(u)"
              class="px-4 py-2 cursor-pointer hover:bg-gray-700 text-white flex items-center gap-2"
              :class="{ 'bg-gray-800': currentPartner && currentPartner.id === u.id }"
            >
              <span class="relative shrink-0">
                <img v-if="u.photo" :src="u.photo" class="w-6 h-6 rounded-full object-cover" alt="" />
                <span v-else class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center text-[10px] font-semibold text-gray-200">{{ (u.name || '?').charAt(0).toUpperCase() }}</span>
                <PresenceBadge :user-id="u.id" size="xs" corner="bottom-right" />
              </span>
              <span>{{ u.name }}</span>
            </li>
            <li v-if="allUsers.length === 0" class="p-4 text-gray-400">No hay usuarios</li>
          </ul>

          <div class="border-t border-gray-700 mt-2"></div>
          <div class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wide">Conversaciones</div>
          <ul>
            <li
              v-for="conv in conversations"
              :key="conv.user.id"
              @click="selectConversation(conv.user)"
              class="px-4 py-3 cursor-pointer flex items-center justify-between hover:bg-gray-700 text-white gap-2"
              :class="{ 'bg-gray-800': currentPartner && currentPartner.id === conv.user.id }"
            >
              <div class="flex items-center gap-2 truncate">
                <span class="relative shrink-0">
                <img v-if="conv.user.photo" :src="conv.user.photo" class="w-6 h-6 rounded-full object-cover" alt="" />
                <span v-else class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center text-[10px] font-semibold text-gray-200">{{ (conv.user.name || '?').charAt(0).toUpperCase() }}</span>
                <PresenceBadge :user-id="conv.user.id" size="xs" corner="bottom-right" />
              </span>
                <span class="truncate">{{ conv.user.name }}</span>
              </div>
              <span
                v-if="conv.unread_count > 0"
                class="ml-2 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-600 text-white"
              >
                {{ conv.unread_count }}
              </span>
            </li>
            <li v-if="conversations.length === 0" class="p-4 text-gray-400">Sin conversaciones</li>
          </ul>
        </aside>

        <!-- message area -->
        <div class="flex-1 min-h-0 flex flex-col">
          <div class="px-4 py-2 border-b border-gray-800 bg-gray-900 flex items-center justify-between">
            <span class="font-semibold text-white">
              {{ currentPartner ? currentPartner.name : 'Sin conversación seleccionada' }}
            </span>
          </div>
          <div ref="messagesContainer" class="flex-1 min-h-0 overflow-y-auto p-4">
            <div v-for="(msg, i) in messages" :key="msg.id ?? i" class="mb-2 flex" :class="msg.sender_id === user?.id ? 'justify-end' : 'justify-start'">
              <div :class="[msg.sender_id === user?.id ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-100', 'rounded-lg px-3 py-2 max-w-[85%] sm:max-w-xs min-w-0']">
                <!-- attachment: images inline, other files as a download chip -->
                <template v-if="msg.attachment">
                  <a
                    v-if="msg.attachment.kind === 'image'"
                    :href="attachmentUrl(msg.attachment.id, true)"
                    target="_blank"
                    rel="noopener"
                    class="block -mx-1 mb-1"
                    :title="msg.attachment.name"
                  >
                    <img
                      :src="attachmentUrl(msg.attachment.id, true)"
                      :alt="msg.attachment.name"
                      loading="lazy"
                      class="max-h-60 w-auto max-w-full rounded-md object-contain bg-black/20"
                    />
                  </a>
                  <a
                    v-else
                    :href="attachmentUrl(msg.attachment.id)"
                    :download="msg.attachment.name"
                    class="flex items-center gap-2 rounded-md px-2 py-2 mb-1 min-w-0"
                    :class="msg.sender_id === user?.id ? 'bg-blue-700/60 hover:bg-blue-700' : 'bg-gray-800/70 hover:bg-gray-800'"
                  >
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5" />
                    </svg>
                    <span class="min-w-0">
                      <span class="block text-sm truncate">{{ msg.attachment.name }}</span>
                      <span class="block text-[11px] opacity-75">{{ formatSize(msg.attachment.size) }} · Descargar</span>
                    </span>
                  </a>
                </template>
                <div v-if="msg.content" class="text-sm whitespace-pre-wrap break-words">{{ msg.content }}</div>
                <div class="text-xs text-gray-400 mt-1 text-right flex items-center justify-end gap-1">
                  <span>{{ formatDate(msg.created_at) }}</span>
                  <span v-if="msg.sender_id === user?.id" :class="msg.read ? 'text-blue-200' : 'text-gray-300'">
                    {{ msg.read ? '✓✓' : '✓' }}
                  </span>
                </div>
              </div>
            </div>
            <div v-if="messages.length === 0" class="text-gray-400 text-center mt-6">Selecciona una conversación para comenzar</div>
          </div>

          <!-- composer: text + attach a file or an image (10 MB max) -->
          <form @submit.prevent="sendMessage" class="border-t border-gray-800 p-3 sm:p-4 space-y-2">
            <div v-if="pendingFile" class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-900 p-2">
              <img v-if="pendingPreview" :src="pendingPreview" alt="" class="w-12 h-12 rounded object-cover shrink-0" />
              <svg v-else class="w-8 h-8 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5" />
              </svg>
              <div class="min-w-0 flex-1">
                <div class="text-sm text-gray-100 truncate">{{ pendingFile.name }}</div>
                <div class="text-[11px] text-gray-500">{{ formatSize(pendingFile.size) }}</div>
              </div>
              <button type="button" class="shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800" aria-label="Quitar archivo" @click="clearPendingFile">✕</button>
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
              <input ref="fileInput" type="file" class="hidden" :accept="FILE_ACCEPT" @change="onPickFile" />
              <input ref="imageInput" type="file" class="hidden" accept="image/*" @change="onPickFile" />
              <button
                type="button"
                class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-40"
                :disabled="!currentPartner || sending"
                title="Adjuntar archivo (PDF, Word, Excel, texto)"
                aria-label="Adjuntar archivo"
                @click="fileInput?.click()"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
              </button>
              <button
                type="button"
                class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-40"
                :disabled="!currentPartner || sending"
                title="Enviar imagen"
                aria-label="Enviar imagen"
                @click="imageInput?.click()"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </button>
              <input
                v-model="newMessage"
                type="text"
                maxlength="500"
                :placeholder="pendingFile ? 'Agregar un mensaje (opcional)...' : 'Escribir un mensaje...'"
                class="!mb-0 flex-1 min-w-0 bg-gray-800 text-white rounded-lg px-3 py-2 focus:outline-none"
                :disabled="!currentPartner || sending"
              />
              <button
                class="shrink-0 px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50"
                :disabled="!canSend"
              >{{ sending ? 'Enviando...' : 'Enviar' }}</button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

definePageMeta({ auth: true })

declare const $fetch: any
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppSidebar from '../components/AppSidebar.vue'

const { user, authenticated, apiBase, fetchUser, fetchUnread, logout, lastReceivedMessage, lastReadReceipt } = useAuth()
const router = useRouter()
const route = useRoute()
const sidebar = ref<{ open: boolean } | null>(null)

const onLogout = async () => {
  await logout()
  router.push('/login')
}


const conversations = ref<any[]>([])
const allUsers = ref<any[]>([])
const currentPartner = ref<any | null>(null)
const messages = ref<any[]>([])
const newMessage = ref('')

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

async function loadConversations() {
  try {
    await fetchUser()
    const res: any = await $fetch(`/api/notifications/conversations`, {
    })
    conversations.value = res.conversations || []
    // exclude those already conversados
    const known = new Set((conversations.value || []).map(c => c.user.id))
    allUsers.value = (res.users || []).filter((u: any) => !known.has(u.id))
  } catch (e) {
    console.error('failed to load conversations', e)
  }
}

async function loadConversation(userId: number) {
  try {
    const res: any = await $fetch(`/api/notifications/conversation/${userId}`, {
    })
    messages.value = res
    // scroll to bottom
    nextTick(() => {
      const container = messagesContainer.value as HTMLElement
      container.scrollTop = container.scrollHeight
    })
    // update unread count in conversations list and global badge
    const conv = conversations.value.find(c => c.user.id === userId)
    if (conv) conv.unread_count = 0
    // refresh global counter
    await fetchUnread()
  } catch (e) {
    console.error('failed to load conversation', e)
  }
}

function selectConversation(user: any) {
  currentPartner.value = user
  // si no existía en conversaciones, agrégalo con datos vacíos
  const exists = conversations.value.find((c) => c.user.id === user.id)
  if (!exists) {
    conversations.value.unshift({
      user,
      last_message: null,
      last_at: null,
      unread_count: 0,
    })
  }
  if (isMobile.value) {
    showUserPanel.value = false
  }
}

// ── Attachments (same limits as the API: 10 MB, images / PDF / Word / Excel / text) ──
const MAX_FILE_BYTES = 10 * 1024 * 1024
const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']
const FILE_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/*'
const { $swal } = useNuxtApp()
const fileInput = ref<HTMLInputElement | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const pendingFile = ref<File | null>(null)
const pendingPreview = ref<string | null>(null)
const sending = ref(false)
const canSend = computed(() =>
  !!currentPartner.value && !sending.value && (newMessage.value.trim() !== '' || !!pendingFile.value),
)

function clearPendingFile() {
  if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value)
  pendingFile.value = null
  pendingPreview.value = null
  if (fileInput.value) fileInput.value.value = ''
  if (imageInput.value) imageInput.value.value = ''
}

function onPickFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const ext = file.name.split('.').pop()?.toLowerCase() || ''
  if (!FILE_EXTENSIONS.includes(ext)) {
    $swal.toast('error', 'Tipo de archivo no permitido. Se aceptan imágenes, PDF, Word, Excel y texto.')
    clearPendingFile()
    return
  }
  if (file.size > MAX_FILE_BYTES) {
    $swal.toast('error', 'El archivo no puede superar los 10 MB.')
    clearPendingFile()
    return
  }
  clearPendingFile()
  pendingFile.value = file
  pendingPreview.value = file.type.startsWith('image/') ? URL.createObjectURL(file) : null
}

/** Served through the Nuxt proxy (cookie auth); only the participants get it. */
function attachmentUrl(id: number, inline = false) {
  return `/api/notifications/attachments/${id}${inline ? '?inline=1' : ''}`
}

function formatSize(bytes?: number) {
  const n = Number(bytes || 0)
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`
  return `${(n / 1024 / 1024).toFixed(1)} MB`
}

async function sendMessage() {
  if (!canSend.value || !currentPartner.value) return
  sending.value = true
  try {
    let body: any = { receiver_id: currentPartner.value.id, content: newMessage.value.trim() }
    if (pendingFile.value) {
      const fd = new FormData()
      fd.append('receiver_id', String(currentPartner.value.id))
      if (newMessage.value.trim()) fd.append('content', newMessage.value.trim())
      fd.append('file', pendingFile.value)
      body = fd
    }
    const res: any = await $fetch(`/api/notifications/send`, { method: 'POST', body })
    // backend now returns whole conversation row; take last message
    if (res.conversation && Array.isArray(res.conversation)) {
      const last = res.conversation[res.conversation.length - 1]
      messages.value.push(last)
    }
    newMessage.value = ''
    clearPendingFile()
    nextTick(() => {
      const container = messagesContainer.value as HTMLElement
      container.scrollTop = container.scrollHeight
    })
    // actualizar contador global, aunque al enviar no cambia para este usuario
    await fetchUnread()
  } catch (e: any) {
    console.error('failed to send message', e)
    const errors = e?.data?.errors
    $swal.toast('error', errors ? String(Object.values(errors).flat()[0]) : (e?.data?.message || 'No se pudo enviar el mensaje'))
  } finally {
    sending.value = false
  }
}

// helper for date formatting
function formatDate(dt: string) {
  const d = parseDate(dt)
  return d ? d.toLocaleString() : ''
}

onMounted(() => {
  isMobile.value = window.innerWidth < 1024
  showUserPanel.value = !isMobile.value
  resizeHandler = () => {
    isMobile.value = window.innerWidth < 1024
    if (isMobile.value) showUserPanel.value = false
    else showUserPanel.value = true
  }
  window.addEventListener('resize', resizeHandler)

  // ?user=ID (from the bell dropdown) opens that conversation
  loadConversations().then(() => {
    const uid = Number(route.query.user)
    if (!uid) return
    const conv = conversations.value.find((c) => c.user.id === uid)
    if (conv) selectConversation(conv.user)
  })

  // Watch the shared reactive state set by useAuth's single Echo listener.
  // This avoids subscribing to Echo here (which caused stale closures and
  // duplicate listeners on every mount/navigation).
  stopRealtimeWatch = watch(lastReceivedMessage, (e) => {
    if (e) handleRealtime(e)
  })
  stopReadReceiptWatch = watch(lastReadReceipt, (e) => {
    if (e) handleReadReceipt(e)
  })
})

onBeforeUnmount(() => {
  if (resizeHandler) {
    window.removeEventListener('resize', resizeHandler)
    resizeHandler = null
  }
  if (stopRealtimeWatch) {
    stopRealtimeWatch()
    stopRealtimeWatch = null
  }
  if (stopReadReceiptWatch) {
    stopReadReceiptWatch()
    stopReadReceiptWatch = null
  }
})

// bell dropdown while already on this page: only the query changes
watch(() => route.query.user, (val) => {
  const uid = Number(val)
  const conv = uid ? conversations.value.find((c) => c.user.id === uid) : null
  if (conv) selectConversation(conv.user)
})

let stopRealtimeWatch: null | (() => void) = null
let stopReadReceiptWatch: null | (() => void) = null
const processedRealtimeKeys = new Set<string>()

function handleRealtime(e: any) {
  // e.conversation holds the entire conversation row
  const conv = e.conversation
  const last = Array.isArray(conv?.conversation) ? conv.conversation[conv.conversation.length - 1] : null
  if (!last) return
  const me = user.value?.id
  const isOwnMessage = last.sender_id === me

  // update conversations list: ensure partner exists and adjust unread_count
  const partnerId = isOwnMessage ? conv.receiver_id : last.sender_id
  const isActiveConversation = !!currentPartner.value && currentPartner.value.id === partnerId
  let item = conversations.value.find(c => c.user.id === partnerId)
  if (!item) {
    // insert at top if new
    item = { user: {id: partnerId, name: 'Usuario'}, last_message: null, last_at: null, unread_count: 0 }
    conversations.value.unshift(item)
  }
  item.unread_count = (isActiveConversation || isOwnMessage) ? 0 : ((item.unread_count || 0) + 1)

  // if the conversation currently open, append the message and scroll
  if (isActiveConversation) {
    const dedupeKey = `${last.id || ''}|${last.sender_id}|${last.created_at}|${last.content}`
    if (processedRealtimeKeys.has(dedupeKey)) return
    processedRealtimeKeys.add(dedupeKey)

    const alreadyExists = messages.value.some((m) =>
      m.sender_id === last.sender_id &&
      m.created_at === last.created_at &&
      m.content === last.content
    )
    if (alreadyExists) return

    messages.value.push(last)
    nextTick(() => {
      const container = messagesContainer.value as HTMLElement
      container.scrollTop = container.scrollHeight
    })

    // If user is focused on this chat, mark incoming message as read immediately
    // so global badges (sidebar/dashboard) stay in sync.
    if (!isOwnMessage) {
      void markConversationReadRealtime(partnerId)
    }
  }
}

watch(currentPartner, (val) => {
  if (val) {
    loadConversation(val.id)
  } else {
    messages.value = []
  }
})

const messagesContainer = ref<HTMLElement | null>(null)
const markingReadFor = new Set<number>()
const isMobile = ref(false)
const showUserPanel = ref(false)
let resizeHandler: (() => void) | null = null

async function markConversationReadRealtime(userId: number) {
  if (!authenticated.value || markingReadFor.has(userId)) return
  markingReadFor.add(userId)
  try {
    await $fetch(`/api/notifications/conversation/${userId}`, {
    })
    await fetchUnread()
  } catch (e) {
    console.error('failed to mark conversation read in realtime', e)
  } finally {
    markingReadFor.delete(userId)
  }
}

function handleReadReceipt(e: any) {
  const readerId = Number(e?.reader_id || 0)
  if (!readerId) return
  if (!currentPartner.value || currentPartner.value.id !== readerId) return

  messages.value = messages.value.map((m: any) => {
    if (m.sender_id === user.value?.id) {
      return { ...m, read: true }
    }
    return m
  })
}
</script>
