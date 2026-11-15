<template>
  <!--
    Small icon on an avatar: house = remote work today, building = at the
    office (clocked in), sun = on vacation. Put it inside a "relative" wrapper
    around the avatar. Renders nothing when there is no presence today.
  -->
  <span
    v-if="kind"
    class="absolute z-10 flex items-center justify-center rounded-full ring-2 ring-gray-900 pointer-events-auto"
    :class="[cornerClass, sizeClass, meta.bg]"
    :title="meta.label"
    role="img"
    :aria-label="meta.label"
  >
    <svg :class="iconClass" class="text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" :d="meta.icon" />
    </svg>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  userId?: number | string | null
  size?: 'xs' | 'sm' | 'md'
  corner?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
}>(), { size: 'sm', corner: 'bottom-left' })

const { kindOf } = usePresence()
const kind = computed(() => kindOf(props.userId))

const META = {
  remote: {
    label: 'Teletrabajo hoy',
    bg: 'bg-sky-500',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  },
  office: {
    label: 'En la oficina',
    bg: 'bg-emerald-600',
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  },
  vacation: {
    label: 'De vacaciones',
    bg: 'bg-teal-500',
    icon: 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z',
  },
} as const

const meta = computed(() => META[kind.value as keyof typeof META] ?? META.office)

const cornerClass = computed(() => ({
  'bottom-right': '-bottom-0.5 -right-0.5',
  'bottom-left': '-bottom-0.5 -left-0.5',
  'top-right': '-top-0.5 -right-0.5',
  'top-left': '-top-0.5 -left-0.5',
}[props.corner]))

const sizeClass = computed(() => ({ xs: 'w-3.5 h-3.5', sm: 'w-4 h-4', md: 'w-5 h-5' }[props.size]))
const iconClass = computed(() => ({ xs: 'w-2 h-2', sm: 'w-2.5 h-2.5', md: 'w-3 h-3' }[props.size]))
</script>
