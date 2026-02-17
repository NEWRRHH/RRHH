<template>
  <!-- Overlay (mobile) -->
  <div
    v-if="open && isMobile"
    class="fixed inset-0 z-30 bg-black/60 lg:hidden"
    @click="open = false"
  />

  <!-- Sidebar -->
  <aside
    :class="[
      'fixed lg:relative inset-y-0 left-0 z-40 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 ease-in-out',
      collapsed ? 'w-16' : 'w-64',
      open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]"
  >
    <!-- Header -->
    <div class="h-16 shrink-0 flex items-center gap-2 px-4 border-b border-gray-800 overflow-hidden">
      <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <span v-if="!collapsed" class="text-white font-bold text-base tracking-tight truncate">RRHH</span>
    </div>

    <!-- Collapse toggle (floating tab on right edge, desktop only) -->
    <button
      class="hidden lg:flex absolute -right-3 top-[52px] z-50 w-6 h-6 rounded-full bg-gray-800 border border-gray-700 items-center justify-center text-gray-400 hover:text-white hover:bg-blue-600 hover:border-blue-600 shadow-lg transition-all duration-200"
      @click="collapsed = !collapsed"
    >
      <svg class="w-3 h-3 transition-transform duration-300" :class="collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
    </button>

    <!-- Body / Nav -->
    <nav class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 gap-1">
      <template v-for="item in navItems" :key="item.label">
        <NuxtLink
          :to="item.to"
          :class="[
            'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium transition group',
            item.active
              ? 'bg-blue-600/15 text-blue-400'
              : 'text-gray-400 hover:text-white hover:bg-gray-800'
          ]"
        >
          <span class="shrink-0 inline-flex items-center" v-html="item.icon" />
          <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
        </NuxtLink>
      </template>
    </nav>

    <!-- Footer -->
    <div class="shrink-0 px-2 py-3 border-t border-gray-800 overflow-hidden">
      <button
        @click="$emit('logout')"
        :class="[
          'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-gray-400 bg-red-500/15 hover:bg-red-500/25 hover:text-white transition-all duration-200',
          collapsed ? 'justify-center' : ''
        ]"
      >
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        <span v-if="!collapsed">Cerrar sesión</span>
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
defineEmits<{ logout: [] }>()

const collapsed = ref(false)
const open = ref(false)
const isMobile = ref(false)

// expose open so parent can toggle on mobile
defineExpose({ open })

onMounted(() => {
  isMobile.value = window.innerWidth < 1024
  window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth < 1024
  })
})

const navItems = [
  {
    label: 'Dashboard',
    to: '/dashboard',
    active: true,
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
    </svg>`
  },
  {
    label: 'Empleados',
    to: '/empleados',
    active: false,
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>`
  },
  {
    label: 'Reportes',
    to: '/reportes',
    active: false,
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>`
  },
  {
    label: 'Configuración',
    to: '/configuracion',
    active: false,
    icon: `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>`
  },
]
</script>
