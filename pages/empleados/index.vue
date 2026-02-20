<template>
  <div class="flex min-h-screen bg-gray-950">
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

        <h1 class="text-base font-semibold text-white">Empleados</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 p-6 overflow-auto">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-800">
            <h2 class="text-white font-semibold">Listado de empleados</h2>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-800/60 text-gray-300">
                <tr>
                  <th class="text-left px-4 py-3">Empleado</th>
                  <th class="text-left px-4 py-3">Email</th>
                  <th class="text-left px-4 py-3">Equipo</th>
                  <th class="text-right px-4 py-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="employee in employees" :key="employee.id" class="border-t border-gray-800 text-gray-200">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-full overflow-hidden bg-blue-600 flex items-center justify-center font-semibold">
                        <img v-if="employee.photo || employee.profile_photo_path" :src="employee.photo || employee.profile_photo_path" class="w-full h-full object-cover" />
                        <span v-else>{{ (employee.name || '?').charAt(0).toUpperCase() }}</span>
                      </div>
                      <span>{{ employee.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-300">{{ employee.email }}</td>
                  <td class="px-4 py-3 text-gray-300">{{ employee.team_name || 'Sin equipo' }}</td>
                  <td class="px-4 py-3 text-right">
                    <div class="relative inline-block action-menu">
                      <button @click.stop="toggleMenu(employee.id)" class="p-2 rounded hover:bg-gray-800">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6h.01M12 12h.01M12 18h.01"/>
                        </svg>
                      </button>

                      <div
                        v-if="openMenuId === employee.id"
                        class="absolute right-0 mt-1 w-40 bg-white text-gray-800 rounded-lg shadow-xl overflow-hidden divide-y divide-gray-200 z-[70]"
                      >
                        <button @click="goToEdit(employee)" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5h2m-1-1v2m-7 8l9-9 3 3-9 9H5v-3z"/>
                          </svg>
                          Editar
                        </button>
                        <button @click="removeEmployee(employee)" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2 text-red-600">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/>
                          </svg>
                          Eliminar
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <tr v-if="!employees.length">
                  <td colspan="4" class="px-4 py-6 text-center text-gray-400">No hay empleados disponibles</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onBeforeMount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import AppSidebar from '../../components/AppSidebar.vue'
import AttendanceButton from '../../components/AttendanceButton.vue'
import UserMenu from '../../components/UserMenu.vue'

declare const process: any
declare const $fetch: any

const { token, fetchUser, logout, apiBase, setToken } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const employees = ref<any[]>([])
const openMenuId = ref<number | null>(null)

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function toggleMenu(id: number) {
  openMenuId.value = openMenuId.value === id ? null : id
}

function goToEdit(employee: any) {
  openMenuId.value = null
  console.log('navigating to edit', employee.id)
  router.push(`/empleados/${employee.id}`)
}

async function removeEmployee(employee: any) {
  openMenuId.value = null
  const ok = confirm(`¿Eliminar a ${employee.name}?`)
  if (!ok) return
  await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees/${employee.id}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token.value}` },
  })
  await loadEmployees()
}

async function loadEmployees() {
  const res = await $fetch(`${apiBase || 'http://localhost:8000'}/api/employees`, {
    headers: { Authorization: `Bearer ${token.value}` },
  })
  employees.value = res?.employees || []
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

onMounted(() => {
  document.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.action-menu')) {
      openMenuId.value = null
    }
  })
})

onBeforeMount(async () => {
  if (!token.value) {
    if (process.client) {
      const saved = localStorage.getItem('rrhh_token')
      if (saved) {
        setToken(saved)
        await fetchUser()
      } else {
        return router.push('/login')
      }
    } else {
      return
    }
  } else {
    await fetchUser()
  }

  await loadEmployees()
})
</script>
