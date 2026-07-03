<template>
  <div class="flex h-screen overflow-hidden bg-gray-950">
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
        <h1 class="text-base font-semibold text-white hidden sm:block">{{ tabs.find(t => t.id === activeTab)?.label || 'Configuración' }}</h1>
        <div v-if="activeTab === 'permissions'" class="relative flex items-center ml-2 sm:ml-8 max-w-[120px] sm:max-w-none">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar rol..."
            class="px-4 pl-10 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <svg class="absolute left-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-6 overflow-auto">
        <div class="max-w-5xl mx-auto space-y-4">
          <div class="inline-flex max-w-full overflow-x-auto rounded-xl border border-gray-700 p-1 bg-gray-900">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="mx-0.5 px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
              :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>

          <section v-if="activeTab === 'dashboard'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <div>
                <h2 class="text-white font-semibold">{{ isAdminUser ? 'Personalizar Dashboard' : 'Mensaje de bienvenida' }}</h2>
                <p class="text-sm text-gray-400 mt-1">{{ isAdminUser ? 'Activa, desactiva y reordena las cards del dashboard.' : 'Actualiza el mensaje de bienvenida.' }}</p>
              </div>
              <div class="flex gap-2">
                <button v-if="isAdminUser" class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-xs transition" @click="resetDefaults">
                  Restablecer
                </button>
                <button
                  class="px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-medium disabled:opacity-50 hover:bg-blue-500 transition"
                  :disabled="saving"
                  @click="saveLayout"
                >
                  {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                </button>
              </div>
            </div>

            <!-- Welcome message card -->
            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-cyan-500/5 to-sky-500/5 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                  <svg class="w-4 h-4 text-cyan-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                  </svg>
                </div>
                <h3 class="text-sm font-semibold text-white">Mensaje de bienvenida</h3>
              </div>
              <div class="flex flex-col gap-3">
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Título</label>
                  <input v-model="welcomeSettings.title" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500" maxlength="80" />
                </div>
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Descripción</label>
                  <textarea v-model="welcomeSettings.subtitle" rows="3" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 resize-none" maxlength="180" />
                </div>
              </div>
              <label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer hover:text-white transition">
                <input v-model="welcomeSettings.show_date" type="checkbox" class="accent-cyan-500 w-4 h-4" />
                Mostrar fecha en bienvenida
              </label>
            </div>

            <div v-if="loading" class="space-y-3">
              <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-cyan-500/5 to-sky-500/5 p-4 space-y-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-gray-800 animate-pulse"></div>
                  <div class="h-4 w-40 rounded bg-gray-800 animate-pulse"></div>
                </div>
                <div class="flex flex-col gap-3">
                  <div class="space-y-2">
                    <div class="h-3 w-12 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-10 w-full rounded-lg bg-gray-800 animate-pulse"></div>
                  </div>
                  <div class="space-y-2">
                    <div class="h-3 w-16 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-20 w-full rounded-lg bg-gray-800 animate-pulse"></div>
                  </div>
                </div>
              </div>
              <div class="rounded-xl border border-gray-800 bg-gray-950/60 p-4 space-y-3">
                <div class="h-4 w-36 rounded bg-gray-800 animate-pulse"></div>
                <div class="grid grid-cols-3 gap-2">
                  <div v-for="i in 9" :key="'sk-slot-' + i" class="h-16 rounded-lg border-2 border-dashed border-gray-700/50 bg-gray-900/40 animate-pulse"></div>
                </div>
              </div>
              <div class="space-y-2">
                <div class="h-4 w-32 rounded bg-gray-800 animate-pulse"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                  <div v-for="i in 9" :key="'sk-wid-' + i" class="h-24 rounded-xl border border-gray-800 bg-gray-950/60 animate-pulse"></div>
                </div>
              </div>
            </div>

            <!-- Visual slots grid (only for admin) -->
            <div v-else-if="isAdminUser" class="space-y-4">
              <div class="rounded-xl border border-gray-800 bg-gray-950/60 p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                  <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                  </svg>
                  Vista previa del layout
                  <span class="text-xs text-gray-500 font-normal">(3 columnas × 3 filas)</span>
                </h3>

                <div class="grid grid-cols-3 gap-2 mb-4">
                  <div
                    v-for="(slotKey, idx) in slots"
                    :key="idx"
                    class="min-w-0 min-h-[70px] rounded-lg border-2 border-dashed p-2 text-xs flex flex-col items-center justify-center gap-1 transition-all duration-200"
                    :class="slotKey
                      ? 'border-blue-500/40 bg-blue-500/10'
                      : 'border-gray-700/50 bg-gray-900/40 text-gray-500 hover:border-gray-600'
                    "
                  >
                    <template v-if="slotKey">
                      <div class="w-5 h-5 rounded bg-blue-500/30 flex items-center justify-center">
                        <svg class="w-3 h-3 text-blue-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                      </div>
                      <span class="text-[10px] text-blue-300 font-medium truncate w-full text-center px-1">{{ getWidgetName(slotKey) }}</span>
                    </template>
                    <template v-else>
                      <span class="text-[10px]">Slot {{ idx + 1 }}</span>
                      <span class="text-[9px] text-gray-600">Vacío</span>
                    </template>
                  </div>
                </div>
              </div>

              <!-- Widget list -->
              <div class="space-y-2">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                  <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                  Widgets disponibles
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                  <div
                    v-for="w in widgets"
                    :key="w.key"
                    class="rounded-xl border p-3 transition-all duration-200"
                    :class="visibility[w.key]
                      ? 'border-blue-500/30 bg-blue-500/5'
                      : 'border-gray-800 bg-gray-950/60 opacity-60 hover:opacity-80'
                    "
                  >
                    <div class="flex items-center justify-between gap-2 mb-2">
                      <label class="flex items-center gap-2 cursor-pointer min-w-0">
                        <input v-model="visibility[w.key]" type="checkbox" class="accent-blue-500 w-4 h-4 shrink-0" />
                        <span class="text-sm font-medium text-gray-200 truncate">{{ w.name }}</span>
                      </label>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-[10px] text-gray-500 bg-gray-800 px-1.5 py-0.5 rounded font-mono shrink-0">{{ w.key }}</span>
                      <select
                        :value="widgetSlot(w.key)"
                        class="flex-1 min-w-0 rounded-lg bg-gray-800 border border-gray-700 px-2 py-1.5 text-xs text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :disabled="!visibility[w.key]"
                        @change="onSlotChange(w.key, $event)"
                      >
                        <option :value="-1">Sin slot</option>
                        <option v-for="i in 9" :key="i" :value="i - 1">Slot {{ i }}</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Non-admin sees only welcome message -->
            <p v-else class="text-sm text-gray-400 text-center py-4">
              Solo los administradores pueden gestionar los widgets del dashboard.
            </p>
          </section>

          <section v-else-if="activeTab === 'permissions'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <h2 class="text-white font-semibold">Permisos por Equipo</h2>

            <div v-if="permissionsLoading" class="space-y-3">
              <div v-for="i in 4" :key="'sk-perm-' + i" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="h-5 w-32 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-5 w-20 rounded-full bg-gray-800 animate-pulse"></div>
                  </div>
                  <div class="w-4 h-4 rounded bg-gray-800 animate-pulse"></div>
                </div>
              </div>
            </div>

            <div v-else-if="!canManagePermissions" class="text-sm text-gray-400">No tienes permisos para gestionar esta seccion.</div>

            <div v-else class="space-y-3">
              <p class="text-sm text-gray-400">Asigna permisos a cada equipo. Los cambios se aplican a todos los usuarios del equipo.</p>

              <div v-if="!permissionTeams.length" class="text-sm text-gray-500">No hay equipos registrados.</div>

              <template v-else>
                <div v-for="team in permissionTeams" :key="team.id" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
                  <!-- Accordion header -->
                  <button
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-800/40 transition"
                    @click="toggleTeamExpanded(team.id)"
                  >
                    <div class="flex items-center gap-3">
                      <span class="font-medium text-white">{{ team.name }}</span>
                      <span class="text-xs px-2 py-0.5 rounded-full bg-blue-600/20 text-blue-400 font-medium relative">
                        {{ (localTeamPermissions[team.id] || []).length }} / {{ permissionsCatalog.length }}
                        <span
                          v-if="hasChangedTeam(team.id)"
                          class="absolute -top-1 -right-1 w-2 h-2 bg-yellow-400 rounded-full"
                        ></span>
                      </span>
                    </div>
                    <svg
                      class="w-4 h-4 text-gray-400 transition-transform duration-200"
                      :class="expandedTeams.includes(team.id) ? 'rotate-180' : ''"
                      fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>

                  <!-- Accordion body -->
                  <div v-if="expandedTeams.includes(team.id)" class="border-t border-gray-800 px-5 py-4 space-y-3">
                    <!-- Search -->
                    <div class="relative">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                      <input
                        :value="permissionSearchQuery[team.id] || ''"
                        @input="onPermissionSearch(team.id, ($event.target as HTMLInputElement).value)"
                        type="search"
                        placeholder="Buscar permiso por nombre o código..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <button
                        v-if="permissionSearchQuery[team.id]"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white"
                        @click="clearPermissionSearch(team.id)"
                      >
                        ✕
                      </button>
                    </div>

                    <!-- Bulk actions -->
                    <div class="flex items-center gap-2">
                      <button
                        class="px-3 py-1.5 rounded-lg bg-green-600/20 border border-green-700/30 text-green-300 text-xs font-medium hover:bg-green-600/30 transition"
                        @click="selectAllPermissions(team.id)"
                      >
                        ✓ Seleccionar todos
                      </button>
                      <button
                        class="px-3 py-1.5 rounded-lg bg-red-600/20 border border-red-700/30 text-red-300 text-xs font-medium hover:bg-red-600/30 transition"
                        @click="deselectAllPermissions(team.id)"
                      >
                        ✕ Quitar todos
                      </button>
                    </div>

                    <div v-for="group in getFilteredGroups(team.id)" :key="group.prefix" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
                      <!-- Group accordion header -->
                      <button
                        type="button"
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-800/40 transition"
                        @click="togglePermissionGroup(group.prefix)"
                      >
                        <div class="flex items-center gap-3">
                          <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ group.label }}</h4>
                          <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-400">
                            {{ group.permissions.length }} permiso(s)
                          </span>
                        </div>
                        <svg
                          class="w-4 h-4 text-gray-400 transition-transform duration-200"
                          :class="expandedPermissionGroups.includes(group.prefix) ? 'rotate-180' : ''"
                          fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>

                      <!-- Group accordion body (collapsed by default) -->
                      <div v-if="expandedPermissionGroups.includes(group.prefix)" class="border-t border-gray-800 px-4 py-3 space-y-1.5">
                        <div
                          v-for="p in group.permissions"
                          :key="p.code"
                          class="flex items-center justify-between gap-4 rounded-lg bg-gray-900 px-4 py-3"
                        >
                          <div class="min-w-0">
                            <div class="text-sm text-gray-200 font-medium">{{ p.name }}</div>
                            <div v-if="p.description" class="text-xs text-gray-500 mt-0.5">{{ p.description }}</div>
                          </div>
                          <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input
                              type="checkbox"
                              :checked="localTeamPermissions[team.id]?.includes(p.code)"
                              class="sr-only peer"
                              @change="toggleTeamPermission(team.id, p.code, $event)"
                            />
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <div class="flex justify-end pt-2">
                <button
                  class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50 relative"
                  :disabled="permissionSaving || !canManagePermissions || !hasPermissionChanges"
                  @click="saveAllPermissions"
                >
                  {{ permissionSaving ? 'Guardando...' : 'Guardar cambios' }}
                  <span
                    v-if="hasPermissionChanges && !permissionSaving"
                    class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-yellow-400 rounded-full border-2 border-gray-900"
                  ></span>
                </button>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'schedules'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">Jornadas laborales</h2>
              <div class="flex items-center gap-2">
                <button
                  v-if="editingScheduleId"
                  class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800"
                  @click="cancelEditScheduleTemplate"
                >
                  Cancelar edicion
                </button>
                <button
                  class="px-3 py-2 rounded-lg bg-blue-600 text-white disabled:opacity-50"
                  :disabled="scheduleSaving"
                  @click="saveScheduleTemplate"
                >
                  {{ scheduleSaving ? 'Guardando...' : (editingScheduleId ? 'Guardar cambios' : 'Agregar jornada') }}
                </button>
              </div>
            </div>

            <p class="text-sm text-gray-400">Define horarios y dias laborales en la tabla schedules para reutilizarlos al crear usuarios.</p>

            <div class="rounded-xl border border-gray-800 bg-gray-950 p-4 space-y-3">
              <h3 class="text-sm text-white font-semibold">Nueva jornada</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Hora de entrada</label>
                  <input v-model="scheduleForm.start_time" type="time" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" />
                </div>
                <div>
                  <label class="block text-xs text-gray-400 mb-1">Hora de salida</label>
                  <input v-model="scheduleForm.end_time" type="time" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white" />
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <label v-for="d in dayOptions" :key="`cfg-day-${d}`" class="inline-flex items-center gap-2 text-xs text-gray-300 bg-gray-800 px-2 py-1 rounded">
                  <input type="checkbox" :value="d" v-model="scheduleForm.days" class="accent-blue-500" />
                  <span>{{ d }}</span>
                </label>
              </div>
            </div>

            <div class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
              <div class="px-4 py-3 border-b border-gray-800 text-sm text-gray-200">Jornadas existentes</div>
              <div v-if="schedulesLoading" class="px-4 py-4 space-y-2">
                <div v-for="i in 4" :key="'sk-sched-' + i" class="grid grid-cols-[1fr_1fr_120px] gap-4 items-center">
                  <div class="h-4 w-24 rounded bg-gray-800 animate-pulse"></div>
                  <div class="h-4 w-40 rounded bg-gray-800 animate-pulse"></div>
                  <div class="flex gap-2 justify-end">
                    <div class="h-7 w-16 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-7 w-16 rounded bg-gray-800 animate-pulse"></div>
                  </div>
                </div>
              </div>
              <div v-else-if="!scheduleTemplates.length" class="px-4 py-4 text-sm text-gray-500">No hay jornadas cargadas.</div>
              <table v-else class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                  <tr>
                    <th class="text-left px-4 py-2">Horario</th>
                    <th class="text-left px-4 py-2">Dias</th>
                    <th class="text-right px-4 py-2">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in scheduleTemplates" :key="s.id" class="border-t border-gray-800 text-gray-200">
                    <td class="px-4 py-2">{{ s.start_time }} - {{ s.end_time }}</td>
                    <td class="px-4 py-2">{{ (s.days || []).join(', ') }}</td>
                    <td class="px-4 py-2 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button
                          class="px-2 py-1 rounded border border-gray-700 text-xs text-gray-200 hover:bg-gray-800"
                          @click="editScheduleTemplate(s)"
                        >
                          Editar
                        </button>
                        <button
                          class="px-2 py-1 rounded border border-red-700 text-xs text-red-300 hover:bg-red-900/20 disabled:opacity-50"
                          :disabled="scheduleDeletingId === s.id"
                          @click="deleteScheduleTemplate(s.id)"
                        >
                          {{ scheduleDeletingId === s.id ? 'Eliminando...' : 'Eliminar' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>


          <section v-else-if="activeTab === 'equipos'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-white font-semibold">Gestion de Equipos</h2>
              <div class="flex items-center gap-2">
                <button
                  v-if="editingTeamId"
                  class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-xs transition"
                  @click="cancelEditTeam"
                >
                  Cancelar edicion
                </button>
                <button
                  class="px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-medium disabled:opacity-50 hover:bg-blue-500 transition"
                  :disabled="teamSaving || !teamForm.name.trim()"
                  @click="saveTeam"
                >
                  {{ teamSaving ? 'Guardando...' : (editingTeamId ? 'Guardar cambios' : 'Agregar equipo') }}
                </button>
              </div>
            </div>

            <p class="text-sm text-gray-400">Crea y administra los equipos de trabajo. Los equipos se usan para agrupar empleados y asignar permisos.</p>

            <!-- Form -->
            <div class="rounded-xl border border-gray-800 bg-gray-950 p-4 space-y-3">
              <h3 class="text-sm text-white font-semibold">{{ editingTeamId ? 'Editar equipo' : 'Nuevo equipo' }}</h3>
              <div class="flex flex-col sm:flex-row gap-3">
                <input
                  v-model="teamForm.name"
                  type="text"
                  placeholder="Nombre del equipo"
                  maxlength="100"
                  class="flex-1 rounded-lg bg-gray-800 border border-gray-700 px-4 py-2.5 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @keyup.enter="saveTeam()"
                />
              </div>
            </div>

            <!-- Teams list -->
            <div class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
              <div class="px-4 py-3 border-b border-gray-800 text-sm text-gray-200 flex items-center gap-2">
                Equipos existentes
                <span class="text-xs text-gray-500">({{ teams.length }})</span>
              </div>

              <div v-if="teamsLoading" class="px-4 py-4 space-y-2">
                <div v-for="i in 4" :key="'sk-team-' + i" class="grid grid-cols-[1fr_80px_120px] gap-4 items-center">
                  <div class="h-4 w-32 rounded bg-gray-800 animate-pulse"></div>
                  <div class="h-4 w-12 rounded bg-gray-800 animate-pulse"></div>
                  <div class="flex gap-2 justify-end">
                    <div class="h-7 w-16 rounded bg-gray-800 animate-pulse"></div>
                    <div class="h-7 w-16 rounded bg-gray-800 animate-pulse"></div>
                  </div>
                </div>
              </div>

              <div v-else-if="!teams.length" class="px-4 py-6 text-sm text-gray-500 text-center">
                No hay equipos registrados. Crea el primero usando el formulario de arriba.
              </div>

              <table v-else class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                  <tr>
                    <th class="text-left px-4 py-2">Nombre</th>
                    <th class="text-center px-4 py-2">Miembros</th>
                    <th class="text-right px-4 py-2">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="t in teams" :key="t.id" class="border-t border-gray-800 text-gray-200 hover:bg-gray-800/30 transition">
                    <td class="px-4 py-3 font-medium">{{ t.name }}</td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs" :class="t.members_count > 0 ? 'bg-blue-600/20 text-blue-400' : 'bg-gray-800 text-gray-500'">
                        {{ t.members_count }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button
                          class="px-2 py-1 rounded border border-gray-700 text-xs text-gray-200 hover:bg-gray-800 transition"
                          @click="editTeam(t)"
                        >
                          Editar
                        </button>
                        <button
                          class="px-2 py-1 rounded border border-red-700 text-xs text-red-300 hover:bg-red-900/20 disabled:opacity-50 transition"
                          :disabled="teamDeletingId === t.id"
                          @click="deleteTeam(t.id)"
                        >
                          {{ teamDeletingId === t.id ? 'Eliminando...' : 'Eliminar' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>


        </div>
      </main>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })
declare const process: any
declare const $fetch: any

const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const { token, fetchUser, logout, apiBase, setToken, user } = useAuth()

const isAdminUser = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_admin) || Number(currentUser?.user_type_id || 0) === 1
})

const isHrTeamUser = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_hr_team)
})

const canManageSchedules = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_manage_schedules) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('schedules.manage')
})

const tabs = computed(() => {
  if (isAdminUser.value) {
    return [
      { id: 'dashboard', label: 'Dashboard' },
      { id: 'permissions', label: 'Permisos' },
      { id: 'schedules', label: 'Jornadas laborales' },
      { id: 'equipos', label: 'Equipos' },
    ]
  }

  if (isHrTeamUser.value || canManageSchedules.value) {
    return [
      { id: 'dashboard', label: 'Bienvenida' },
      { id: 'schedules', label: 'Jornadas laborales' },
    ]
  }

  return []
})

const activeTab = ref('dashboard')
const loading = ref(true)
const saving = ref(false)
const widgets = ref<Array<{ key: string; name: string }>>([])
const slots = ref<Array<string | null>>([null, null, null, null, null, null, null, null, null])
const visibility = ref<Record<string, boolean>>({})
const welcomeSettings = ref<{ title: string; subtitle: string; show_date: boolean }>({
  title: 'Bienvenido',
  subtitle: 'Aqui tenes un resumen de la actividad del sistema.',
  show_date: true,
})
const canManagePermissions = ref(false)
const permissionsLoading = ref(false)
const permissionSaving = ref(false)
const permissionsCatalog = ref<Array<{ id: number; code: string; name: string; description: string | null }>>([])
const permissionTeams = ref<Array<{ id: number; name: string; permission_codes: string[] }>>([])
const selectedPermissionTeamId = ref<number | null>(null)
const selectedPermissionCodes = ref<string[]>([])
const searchQuery = ref('')
const localTeamPermissions = ref<Record<number, string[]>>({})
const savedTeamPermissionsSnapshot = ref<Record<number, string[]>>({})
const expandedTeams = ref<number[]>([])

const hasPermissionChanges = computed(() => {
  const current = localTeamPermissions.value
  const saved = savedTeamPermissionsSnapshot.value
  const teamIds = new Set([...Object.keys(current), ...Object.keys(saved)])
  for (const idStr of teamIds) {
    const id = Number(idStr)
    const curr = (current[id] || []).slice().sort()
    const svd = (saved[id] || []).slice().sort()
    if (curr.length !== svd.length || curr.some((c, i) => c !== svd[i])) {
      return true
    }
  }
  return false
})
const permissionSearchQuery = ref<Record<number, string>>({})
const expandedPermissionGroups = ref<string[]>([])

const GROUP_LABELS: Record<string, string> = {
  employees: 'Usuarios',
  requests: 'Solicitudes',
  schedules: 'Jornadas',
  announcements: 'Comunicados',
}

const permissionGroups = computed(() => {
  const groups: Record<string, { prefix: string; label: string; permissions: typeof permissionsCatalog.value }> = {}
  for (const p of permissionsCatalog.value) {
    const prefix = p.code.split('.')[0]
    if (!groups[prefix]) {
      groups[prefix] = {
        prefix,
        label: GROUP_LABELS[prefix] ?? prefix,
        permissions: [],
      }
    }
    groups[prefix].permissions.push(p)
  }
  return Object.values(groups)
})

function togglePermissionGroup(prefix: string) {
  const idx = expandedPermissionGroups.value.indexOf(prefix)
  if (idx >= 0) expandedPermissionGroups.value.splice(idx, 1)
  else expandedPermissionGroups.value.push(prefix)
}

function toggleTeamExpanded(teamId: number) {
  const idx = expandedTeams.value.indexOf(teamId)
  if (idx >= 0) expandedTeams.value.splice(idx, 1)
  else expandedTeams.value.push(teamId)
}

function onPermissionSearch(teamId: number, value: string) {
  permissionSearchQuery.value = { ...permissionSearchQuery.value, [teamId]: value }
  // Auto-expand groups that match when searching
  const q = value.toLowerCase().trim()
  if (q) {
    for (const group of permissionGroups.value) {
      const hasMatch = group.permissions.some(
        (p) => p.code.toLowerCase().includes(q) || p.name.toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q)
      )
      if (hasMatch && !expandedPermissionGroups.value.includes(group.prefix)) {
        expandedPermissionGroups.value.push(group.prefix)
      }
    }
  }
}

function clearPermissionSearch(teamId: number) {
  const next = { ...permissionSearchQuery.value }
  delete next[teamId]
  permissionSearchQuery.value = next
}

function getFilteredGroups(teamId: number) {
  const q = (permissionSearchQuery.value[teamId] || '').toLowerCase().trim()
  if (!q) return permissionGroups.value

  return permissionGroups.value
    .map((group) => ({
      ...group,
      permissions: group.permissions.filter(
        (p) =>
          p.code.toLowerCase().includes(q) ||
          p.name.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
      ),
    }))
    .filter((group) => group.permissions.length > 0)
}

const dayOptions = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const schedulesLoading = ref(false)
const scheduleSaving = ref(false)
const scheduleDeletingId = ref<number | null>(null)
const editingScheduleId = ref<number | null>(null)
const scheduleTemplates = ref<Array<{ id: number; start_time: string; end_time: string; days: string[]; label: string }>>([])
const scheduleForm = ref<{ start_time: string; end_time: string; days: string[] }>({
  start_time: '09:00',
  end_time: '18:00',
  days: ['L', 'M', 'X', 'J', 'V'],
})

// Teams management
const teams = ref<Array<{ id: number; name: string; members_count: number; created_at: string }>>([])
const teamsLoading = ref(false)
const teamSaving = ref(false)
const teamDeletingId = ref<number | null>(null)
const editingTeamId = ref<number | null>(null)
const teamForm = ref<{ name: string }>({ name: '' })

const { $swal } = useNuxtApp()

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

function getWidgetName(key: string): string {
  const w = widgets.value.find((w) => w.key === key)
  return w?.name || key
}

function widgetSlot(key: string): number {
  return slots.value.findIndex((k) => k === key)
}

function onSlotChange(key: string, e: Event) {
  const target = e.target as HTMLSelectElement
  const next = Number(target.value)

  const current = widgetSlot(key)
  if (current >= 0) slots.value[current] = null
  if (next >= 0) {
    const occupying = slots.value[next]
    if (occupying && occupying !== key) {
      slots.value[next] = key
      if (current >= 0) {
        slots.value[current] = occupying
      }
      return
    }
    slots.value[next] = key
  }
}

function resetDefaults() {
  if (!isAdminUser.value) return
  slots.value = [null, null, null, null, null, null, null, null, null]
  const order = ['welcome', 'birthdays', 'team', 'vacation', 'notifications', 'worked_hours', 'holidays', 'announcements', 'documents']
  order.forEach((k, idx) => {
    slots.value[idx] = k
    visibility.value[k] = true
  })
  welcomeSettings.value = {
    title: 'Bienvenido',
    subtitle: 'Aqui tenes un resumen de la actividad del sistema.',
    show_date: true,
  }
}

async function loadLayout() {
  if (!token.value) return
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    widgets.value = (res?.widgets || []).map((w: any) => ({ key: String(w.key), name: String(w.name) }))
    slots.value = Array.isArray(res?.slots) ? res.slots.slice(0, 9) : [null, null, null, null, null, null, null, null, null]
    while (slots.value.length < 9) slots.value.push(null)

    const vis: Record<string, boolean> = {}
    for (const w of widgets.value) {
      vis[w.key] = !!(res?.visibility?.[w.key] ?? true)
    }
    visibility.value = vis

    const ws = res?.settings?.welcome || {}
    welcomeSettings.value = {
      title: typeof ws?.title === 'string' && ws.title.trim() ? ws.title.trim() : 'Bienvenido',
      subtitle: typeof ws?.subtitle === 'string' && ws.subtitle.trim() ? ws.subtitle.trim() : 'Aqui tenes un resumen de la actividad del sistema.',
      show_date: ws?.show_date !== false,
    }
  } catch (e) {
    console.error('layout load failed', e)
  } finally {
    loading.value = false
  }
}

async function saveLayout() {
  if (!token.value) return
  saving.value = true
  try {
    const normalizedSlots = slots.value.map((k) => {
      if (!k) return null
      return visibility.value[k] ? k : null
    })

    await $fetch(`${apiBase || 'http://localhost:8000'}/api/dashboard/layout`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        slots: normalizedSlots,
        visibility: visibility.value,
        settings: {
          welcome: welcomeSettings.value,
        },
      },
    })
    $swal.toast('success', 'Configuracion guardada correctamente')
  } catch (e) {
    console.error('layout save failed', e)
    $swal.toast('error', 'No se pudo guardar la configuracion')
  } finally {
    saving.value = false
  }
}

const selectedPermissionTeamName = computed(() => {
  const t = permissionTeams.value.find((x) => x.id === selectedPermissionTeamId.value)
  return t?.name || ''
})

function selectPermissionTeam(id: number) {
  selectedPermissionTeamId.value = id
  const t = permissionTeams.value.find((x) => x.id === id)
  selectedPermissionCodes.value = Array.isArray(t?.permission_codes) ? [...t!.permission_codes] : []
}

function togglePermissionCode(code: string, e: Event) {
  const checked = (e.target as HTMLInputElement).checked
  const set = new Set(selectedPermissionCodes.value)
  if (checked) set.add(code)
  else set.delete(code)
  selectedPermissionCodes.value = Array.from(set.values())
}

async function loadPermissions() {
  if (!isAdminUser.value) {
    canManagePermissions.value = false
    permissionsCatalog.value = []
    permissionTeams.value = []
    return
  }
  if (!token.value) return
  permissionsLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/permissions/catalog`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    canManagePermissions.value = true
    permissionsCatalog.value = Array.isArray(res?.permissions) ? res.permissions : []
    permissionTeams.value = Array.isArray(res?.teams) ? res.teams : []
    const map: Record<number, string[]> = {}
    for (const t of permissionTeams.value) {
      map[t.id] = Array.isArray(t.permission_codes) ? [...t.permission_codes] : []
    }
    localTeamPermissions.value = map
    savedTeamPermissionsSnapshot.value = JSON.parse(JSON.stringify(map))
    if (!selectedPermissionTeamId.value && permissionTeams.value.length) {
      selectPermissionTeam(permissionTeams.value[0].id)
    }
  } catch (e: any) {
    if (e?.status === 403 || e?.data?.message === 'Forbidden') {
      canManagePermissions.value = false
    }
    permissionsCatalog.value = []
    permissionTeams.value = []
  } finally {
    permissionsLoading.value = false
  }
}

function toggleTeamPermission(teamId: number, code: string, e: Event) {
  const checked = (e.target as HTMLInputElement).checked
  const current = localTeamPermissions.value[teamId] ? [...localTeamPermissions.value[teamId]] : []
  const set = new Set(current)
  if (checked) set.add(code)
  else set.delete(code)
  localTeamPermissions.value[teamId] = Array.from(set)
}

function hasChangedTeam(teamId: number): boolean {
  const curr = (localTeamPermissions.value[teamId] || []).slice().sort()
  const svd = (savedTeamPermissionsSnapshot.value[teamId] || []).slice().sort()
  return curr.length !== svd.length || curr.some((c, i) => c !== svd[i])
}

function selectAllPermissions(teamId: number) {
  const allCodes = permissionsCatalog.value.map((p) => p.code)
  localTeamPermissions.value[teamId] = [...allCodes]
}

async function deselectAllPermissions(teamId: number) {
  const ok = await $swal.confirm('Quitar todos los permisos', '¿Estás seguro de quitar todos los permisos de este equipo? Los cambios son locales hasta que guardes.', 'Sí, quitar todos', 'Cancelar')
  if (!ok) return
  localTeamPermissions.value[teamId] = []
}

async function saveAllPermissions() {
  if (!token.value || !canManagePermissions.value) return
  permissionSaving.value = true
  try {
    for (const team of permissionTeams.value) {
      const codes = localTeamPermissions.value[team.id] || []
      await $fetch(`${apiBase || 'http://localhost:8000'}/api/permissions/teams/${team.id}`, {
        method: 'PUT',
        headers: { Authorization: `Bearer ${token.value}` },
        body: { permission_codes: codes },
      })
      team.permission_codes = [...codes]
    }
    savedTeamPermissionsSnapshot.value = JSON.parse(JSON.stringify(localTeamPermissions.value))
    $swal.toast('success', 'Permisos actualizados')
  } catch (e) {
    $swal.toast('error', 'No se pudieron guardar los permisos')
  } finally {
    permissionSaving.value = false
  }
}

async function loadScheduleTemplates() {
  if (!token.value) return
  schedulesLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/schedules`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    scheduleTemplates.value = Array.isArray(res?.schedules) ? res.schedules : []
  } catch (e: any) {
    if (!(e?.status === 403 || e?.data?.message === 'Forbidden')) {
      $swal.toast('error', 'No se pudieron cargar las jornadas')
    }
    scheduleTemplates.value = []
  } finally {
    schedulesLoading.value = false
  }
}

async function saveScheduleTemplate() {
  if (!token.value) return
  scheduleSaving.value = true
  try {
    const method = editingScheduleId.value ? 'PUT' : 'POST'
    const endpoint = editingScheduleId.value
      ? `${apiBase || 'http://localhost:8000'}/api/settings/schedules/${editingScheduleId.value}`
      : `${apiBase || 'http://localhost:8000'}/api/settings/schedules`

    await $fetch(endpoint, {
      method,
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        start_time: scheduleForm.value.start_time,
        end_time: scheduleForm.value.end_time,
        days: scheduleForm.value.days,
      },
    })
    $swal.toast('success', editingScheduleId.value ? 'Jornada actualizada' : 'Jornada guardada')
    editingScheduleId.value = null
    scheduleForm.value = { start_time: '09:00', end_time: '18:00', days: ['L', 'M', 'X', 'J', 'V'] }
    await loadScheduleTemplates()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo guardar la jornada')
  } finally {
    scheduleSaving.value = false
  }
}

function editScheduleTemplate(schedule: { id: number; start_time: string; end_time: string; days: string[] }) {
  editingScheduleId.value = Number(schedule.id)
  scheduleForm.value = {
    start_time: schedule.start_time || '09:00',
    end_time: schedule.end_time || '18:00',
    days: Array.isArray(schedule.days) && schedule.days.length ? [...schedule.days] : ['L', 'M', 'X', 'J', 'V'],
  }
}

function cancelEditScheduleTemplate() {
  editingScheduleId.value = null
  scheduleForm.value = { start_time: '09:00', end_time: '18:00', days: ['L', 'M', 'X', 'J', 'V'] }
}

async function deleteScheduleTemplate(id: number) {
  const ok = await $swal.confirm('Confirmar eliminación', '¿Estás seguro de eliminar esta jornada laboral? Esta acción no se puede deshacer.', 'Eliminar', 'Cancelar')
  if (!ok) return
  scheduleDeletingId.value = id
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/schedules/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (editingScheduleId.value === id) {
      cancelEditScheduleTemplate()
    }
    $swal.toast('success', 'Jornada eliminada')
    await loadScheduleTemplates()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo eliminar la jornada')
  } finally {
    scheduleDeletingId.value = null
  }
}

// ── Teams management ──

async function loadTeams() {
  if (!token.value) return
  teamsLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/teams`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    teams.value = Array.isArray(res?.teams) ? res.teams : []
  } catch (e: any) {
    $swal.toast('error', 'No se pudieron cargar los equipos')
    teams.value = []
  } finally {
    teamsLoading.value = false
  }
}

async function saveTeam() {
  if (!token.value || !teamForm.value.name.trim()) return
  teamSaving.value = true
  try {
    const method = editingTeamId.value ? 'PUT' : 'POST'
    const endpoint = editingTeamId.value
      ? `${apiBase || 'http://localhost:8000'}/api/settings/teams/${editingTeamId.value}`
      : `${apiBase || 'http://localhost:8000'}/api/settings/teams`

    await $fetch(endpoint, {
      method,
      headers: { Authorization: `Bearer ${token.value}` },
      body: { name: teamForm.value.name.trim() },
    })
    $swal.toast('success', editingTeamId.value ? 'Equipo actualizado' : 'Equipo creado')
    editingTeamId.value = null
    teamForm.value = { name: '' }
    await loadTeams()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo guardar el equipo')
  } finally {
    teamSaving.value = false
  }
}

function editTeam(team: { id: number; name: string }) {
  editingTeamId.value = team.id
  teamForm.value = { name: team.name }
}

function cancelEditTeam() {
  editingTeamId.value = null
  teamForm.value = { name: '' }
}

async function deleteTeam(id: number) {
  const ok = await $swal.confirm('Confirmar eliminación', '¿Estás seguro de eliminar este equipo? Solo se pueden eliminar equipos sin miembros.', 'Eliminar', 'Cancelar')
  if (!ok) return
  teamDeletingId.value = id
  try {
    await $fetch(`${apiBase || 'http://localhost:8000'}/api/settings/teams/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` },
    })
    if (editingTeamId.value === id) {
      cancelEditTeam()
    }
    $swal.toast('success', 'Equipo eliminado')
    await loadTeams()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo eliminar el equipo')
  } finally {
    teamDeletingId.value = null
  }
}

// ── End teams management ──

watch(activeTab, async (tab) => {
  if (tab === 'schedules' && !scheduleTemplates.value.length && !schedulesLoading.value) {
    await loadScheduleTemplates()
  }
  if (tab === 'equipos' && !teams.value.length && !teamsLoading.value) {
    await loadTeams()
  }
})

watch(tabs, (nextTabs) => {
  const ids = nextTabs.map((t) => t.id)
  if (!ids.includes(activeTab.value)) {
    activeTab.value = ids[0] || 'dashboard'
  }
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

  const canAccessSettings = Boolean((user.value as any)?.can_access_settings)
  if (!canAccessSettings) {
    return router.push('/dashboard')
  }

  await loadLayout()
  await loadPermissions()
  await loadScheduleTemplates()
})


</script>
