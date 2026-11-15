<template>
  <div class="relative inline-block">
    <button
      ref="trigger"
      type="button"
      class="p-2 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition"
      :aria-label="label"
      :title="label"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click.stop="toggle"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6h.01M12 12h.01M12 18h.01" />
      </svg>
    </button>

    <!-- Teleported + fixed so table / card overflow containers don't clip it -->
    <Teleport to="body">
      <div
        v-if="open"
        ref="menu"
        role="menu"
        class="fixed z-[70] min-w-[10rem] overflow-hidden rounded-lg bg-white text-gray-800 shadow-xl divide-y divide-gray-200"
        :style="style"
        @click="close"
      >
        <slot />
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue'

// Menu items go in the default slot, e.g.
// <button class="row-action" @click="edit(t)">Editar</button>
withDefaults(defineProps<{ label?: string }>(), { label: 'Acciones' })

const open = ref(false)
const trigger = ref<HTMLElement | null>(null)
const menu = ref<HTMLElement | null>(null)
const style = ref<Record<string, string>>({})

const place = () => {
  const r = trigger.value?.getBoundingClientRect()
  if (!r) return
  const width = menu.value?.offsetWidth || 160
  const height = menu.value?.offsetHeight || 0
  const left = Math.max(8, Math.min(r.right - width, window.innerWidth - width - 8))
  // open upwards when there is no room below
  const below = r.bottom + 4
  const top = height && below + height > window.innerHeight - 8 ? r.top - height - 4 : below
  style.value = { top: `${Math.max(8, top)}px`, left: `${left}px` }
}

const close = () => { open.value = false; detach() }
const onOutside = (e: Event) => {
  const t = e.target as Node
  if (menu.value?.contains(t) || trigger.value?.contains(t)) return
  close()
}
const attach = () => {
  document.addEventListener('click', onOutside, true)
  window.addEventListener('scroll', close, true)
  window.addEventListener('resize', close)
}
function detach() {
  document.removeEventListener('click', onOutside, true)
  window.removeEventListener('scroll', close, true)
  window.removeEventListener('resize', close)
}

const toggle = async () => {
  if (open.value) return close()
  open.value = true
  place()
  await nextTick()
  place() // with the real menu size
  attach()
}

onBeforeUnmount(detach)
</script>

<style>
/* shared look of the items placed in the slot */
.row-action {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  text-align: left;
  font-size: 0.875rem;
}
.row-action:hover { background: #f3f4f6; }
.row-action:disabled { opacity: 0.5; cursor: not-allowed; }
.row-action--danger { color: #dc2626; }
</style>
