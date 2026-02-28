<template>
  <div class="flex min-h-screen bg-gray-950">
    <!-- sidebar y top bar reuse existing layout structure -->
    <AppSidebar ref="sidebar" @logout="onLogout" />
    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-4 px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <h1 class="text-base font-semibold text-white">Notificaciones</h1>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 p-0 overflow-hidden flex">
        <!-- conversation list + users -->
        <aside class="w-64 border-r border-gray-800 bg-gray-900 flex-shrink-0 overflow-y-auto">
          <div class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wide">Usuarios</div>
          <ul>
            <li
              v-for="u in allUsers"
              :key="u.id"
              @click="selectConversation(u)"
              class="px-4 py-2 cursor-pointer hover:bg-gray-700 text-white flex items-center gap-2"
              :class="{ 'bg-gray-800': currentPartner && currentPartner.id === u.id }"
            >
              <img
                v-if="u.photo"
                :src="u.photo"
                class="w-6 h-6 rounded-full object-cover"
                alt="avatar"
              />
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
                <img
                  v-if="conv.user.photo"
                  :src="conv.user.photo"
                  class="w-6 h-6 rounded-full object-cover"
                  alt="avatar"
                />
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
        <div class="flex-1 flex flex-col">
          <div class="px-4 py-2 border-b border-gray-800 bg-gray-900 flex items-center justify-between">
            <span class="font-semibold text-white">
              {{ currentPartner ? currentPartner.name : 'Sin conversación seleccionada' }}
            </span>
          </div>
          <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4">
            <div v-for="msg in messages" :key="msg.id" class="mb-2 flex" :class="msg.sender_id === user?.id ? 'justify-end' : 'justify-start'">
              <div :class="[msg.sender_id === user?.id ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-100', 'rounded-lg px-3 py-2 max-w-xs']">
                <div class="text-sm">{{ msg.content }}</div>
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

          <form @submit.prevent="sendMessage" class="flex items-center border-t border-gray-800 p-4">
            <input
              v-model="newMessage"
              type="text"
              placeholder="Escribir un mensaje..."
              class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2 focus:outline-none"
              :disabled="!currentPartner"
            />
            <button
              class="ml-2 px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50"
              :disabled="!currentPartner || newMessage.trim() === ''"
            >Enviar</button>
          </form>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

definePageMeta({ auth: true })

declare const $fetch: any
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'
import AppSidebar from '../components/AppSidebar.vue'

const { user, token, apiBase, fetchUser, fetchUnread, logout, lastReceivedMessage, lastReadReceipt } = useAuth()
const router = useRouter()
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
    const res: any = await $fetch(`${apiBase}/api/notifications/conversations`, {
      headers: { Authorization: `Bearer ${token.value}` }
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
    const res: any = await $fetch(`${apiBase}/api/notifications/conversation/${userId}`, {
      headers: { Authorization: `Bearer ${token.value}` }
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
}

async function sendMessage() {
  if (!currentPartner.value || newMessage.value.trim() === '') return
  try {
    const res: any = await $fetch(`${apiBase}/api/notifications/send`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        receiver_id: currentPartner.value.id,
        content: newMessage.value.trim(),
      }
    })
    // backend now returns whole conversation row; take last message
    if (res.conversation && Array.isArray(res.conversation)) {
      const last = res.conversation[res.conversation.length - 1]
      messages.value.push(last)
    }
    newMessage.value = ''
    nextTick(() => {
      const container = messagesContainer.value as HTMLElement
      container.scrollTop = container.scrollHeight
    })
    // actualizar contador global, aunque al enviar no cambia para este usuario
    await fetchUnread()
  } catch (e) {
    console.error('failed to send message', e)
  }
}

// helper for date formatting
function formatDate(dt: string) {
  const d = new Date(dt)
  return d.toLocaleString()
}

onMounted(() => {
  loadConversations()

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
  if (stopRealtimeWatch) {
    stopRealtimeWatch()
    stopRealtimeWatch = null
  }
  if (stopReadReceiptWatch) {
    stopReadReceiptWatch()
    stopReadReceiptWatch = null
  }
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

async function markConversationReadRealtime(userId: number) {
  if (!token.value || markingReadFor.has(userId)) return
  markingReadFor.add(userId)
  try {
    await $fetch(`${apiBase}/api/notifications/conversation/${userId}`, {
      headers: { Authorization: `Bearer ${token.value}` }
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
