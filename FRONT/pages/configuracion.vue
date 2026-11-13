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
        <h1 class="text-base font-semibold text-white hidden sm:block">Configuración</h1>
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
          <!-- Plan & limits (company admins) -->
          <div v-if="planUsage" class="rounded-2xl border border-gray-800 bg-gray-900 px-5 py-4 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
            <div>
              <span class="text-gray-400">Plan</span>
              <span class="ml-2 font-semibold text-white">{{ planUsage.plan?.name || 'Sin plan' }}</span>
            </div>
            <div class="text-gray-300">
              Empleados:
              <span class="tabular-nums text-white">{{ planUsage.employees.used }}</span>
              <span class="text-gray-500"> / {{ planUsage.employees.max ?? 'ilimitados' }}</span>
            </div>
            <div class="text-gray-300">
              Almacenamiento:
              <span class="tabular-nums text-white">{{ planUsage.storage_mb.used }} MB</span>
              <span class="text-gray-500"> / {{ planUsage.storage_mb.max ? `${planUsage.storage_mb.max} MB` : 'ilimitado' }}</span>
            </div>
            <span class="text-xs text-gray-500">Para ampliar el plan contacta con el soporte.</span>
          </div>
          <div class="inline-flex max-w-full overflow-x-auto rounded-xl border border-gray-700 p-1 bg-gray-900">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="mx-0.5 px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
              :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
              @click="selectTab(tab.id)"
            >
              {{ tab.label }}
            </button>
          </div>

          <section v-if="activeTab === 'dashboard'" class="space-y-6">
            <div>
              <h2 class="text-white font-semibold">Dashboard de la empresa</h2>
              <p class="text-sm text-gray-400 mt-1">
                Es el diseño que ven todos los empleados por defecto. Cada persona puede reacomodar el suyo desde su dashboard y volver a este cuando quiera.
              </p>
            </div>

            <div v-if="layoutLoading" class="space-y-4">
              <div class="h-40 rounded-2xl bg-gray-900 border border-gray-800 animate-pulse"></div>
              <div class="h-64 rounded-2xl bg-gray-900 border border-gray-800 animate-pulse"></div>
            </div>

            <template v-else>
              <!-- Welcome message + live preview -->
              <div class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
                <h3 class="text-sm font-semibold text-white mb-4">Mensaje de bienvenida</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                  <div class="space-y-4">
                    <div>
                      <div class="flex items-baseline justify-between mb-1">
                        <label for="welcome-title" class="!mb-0 text-xs !font-medium text-gray-400">Título</label>
                        <span class="text-[11px] tabular-nums" :class="welcome.title.length >= 80 ? 'text-amber-300' : 'text-gray-500'">{{ welcome.title.length }}/80</span>
                      </div>
                      <input id="welcome-title" v-model="welcome.title" maxlength="80" placeholder="Bienvenido" class="!mb-0 w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                      <p class="mt-1 text-[11px] text-gray-500">Se completa con el nombre de cada empleado: "{{ welcome.title || 'Bienvenido' }}, Ana".</p>
                    </div>
                    <div>
                      <div class="flex items-baseline justify-between mb-1">
                        <label for="welcome-subtitle" class="!mb-0 text-xs !font-medium text-gray-400">Descripción</label>
                        <span class="text-[11px] tabular-nums" :class="welcome.subtitle.length >= 180 ? 'text-amber-300' : 'text-gray-500'">{{ welcome.subtitle.length }}/180</span>
                      </div>
                      <textarea id="welcome-subtitle" v-model="welcome.subtitle" rows="3" maxlength="180" class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer">
                      <input v-model="welcome.show_date" type="checkbox" class="accent-blue-500 w-4 h-4" />
                      Mostrar la fecha de hoy
                    </label>
                  </div>

                  <!-- Live preview: same look as the dashboard card -->
                  <div>
                    <div class="text-[11px] text-gray-500 mb-1.5">Vista previa</div>
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 min-h-44 shadow-xl shadow-blue-900/40">
                      <div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                      <div class="relative">
                        <p class="text-blue-200 text-xs font-medium mb-1">Panel de control</p>
                        <h4 class="text-xl font-bold text-white leading-tight break-words">{{ welcome.title || 'Bienvenido' }}, {{ previewName }}</h4>
                        <p v-if="welcome.show_date" class="mt-1 text-blue-100/90 text-xs">{{ todayLabel }}</p>
                        <p class="mt-2 text-blue-200 text-sm break-words">{{ welcome.subtitle || 'Aquí tienes un resumen de la actividad de la empresa.' }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cards -->
              <div class="rounded-2xl border border-gray-800 bg-gray-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                  <div>
                    <h3 class="text-sm font-semibold text-white">Tarjetas</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Arrastra las tarjetas para cambiarlas de lugar. Se ordenan igual que en el dashboard: por columnas, de arriba hacia abajo.</p>
                  </div>
                  <button class="text-xs px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" @click="restoreLayoutDefaults">
                    Restablecer diseño original
                  </button>
                </div>
                <DashboardLayoutEditor v-model="companySlots" />
              </div>

              <p v-if="layoutUpdatedAt" class="text-xs text-gray-600">Última modificación: {{ new Date(layoutUpdatedAt).toLocaleString('es-ES') }}</p>
            </template>

            <!-- Sticky save bar: only when there are unsaved changes -->
            <div
              v-if="layoutDirty"
              class="sticky bottom-0 z-20 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-500/30 bg-gray-900/95 backdrop-blur px-4 py-3 shadow-2xl shadow-black/50"
              role="status"
            >
              <span class="text-sm text-gray-200 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                Tienes cambios sin guardar
              </span>
              <div class="flex gap-2">
                <button class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm" :disabled="layoutSaving" @click="discardLayoutChanges">Descartar</button>
                <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium disabled:opacity-50" :disabled="layoutSaving" @click="saveCompanyLayout">
                  {{ layoutSaving ? 'Guardando...' : 'Guardar para toda la empresa' }}
                </button>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'permissions'" class="rounded-2xl border border-gray-800 bg-gray-900 p-5 space-y-4">
            <h2 class="text-white font-semibold">Roles y permisos</h2>

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
              <p class="text-sm text-gray-400">
                Cada usuario tiene un unico rol. Asigna permisos a cada rol: los cambios se aplican a todos los usuarios con ese rol.
                El rol de administrador tiene siempre todos los permisos.
              </p>

              <!-- Create / rename role -->
              <div class="flex flex-col sm:flex-row gap-2">
                <input
                  v-model="roleForm.name"
                  type="text"
                  maxlength="100"
                  :placeholder="editingRoleId ? 'Nuevo nombre del rol' : 'Nombre del nuevo rol (ej: IT, Ventas)'"
                  class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @keyup.enter="saveRole"
                />
                <div class="flex gap-2">
                  <button
                    v-if="editingRoleId"
                    class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm"
                    @click="cancelEditRole"
                  >
                    Cancelar
                  </button>
                  <button
                    class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm disabled:opacity-50"
                    :disabled="roleSaving || !roleForm.name.trim()"
                    @click="saveRole"
                  >
                    {{ roleSaving ? 'Guardando...' : (editingRoleId ? 'Renombrar rol' : 'Crear rol') }}
                  </button>
                </div>
              </div>

              <div v-if="!filteredPermissionRoles.length" class="text-sm text-gray-500">No hay roles que coincidan.</div>

              <template v-else>
                <div v-for="role in filteredPermissionRoles" :key="role.id" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
                  <!-- Accordion header -->
                  <div class="w-full flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-800/40 transition">
                    <button class="flex-1 flex items-center gap-3 text-left min-w-0" @click="toggleRoleExpanded(role.id)">
                      <span class="font-medium text-white truncate">{{ role.name }}</span>
                      <span v-if="role.is_admin" class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-medium">Administrador</span>
                      <span v-else class="text-xs px-2 py-0.5 rounded-full bg-blue-600/20 text-blue-400 font-medium relative">
                        {{ (localRolePermissions[role.id] || []).length }} / {{ permissionsCatalog.length }}
                        <span
                          v-if="hasChangedRole(role.id)"
                          class="absolute -top-1 -right-1 w-2 h-2 bg-yellow-400 rounded-full"
                        ></span>
                      </span>
                      <span class="text-xs text-gray-500 whitespace-nowrap">{{ role.users_count }} usuario(s)</span>
                    </button>
                    <div class="flex items-center gap-1 shrink-0">
                      <template v-if="!role.is_admin">
                        <button
                          class="px-2 py-1 rounded-lg text-xs text-gray-300 hover:bg-gray-800"
                          title="Renombrar"
                          @click="editRole(role)"
                        >
                          Renombrar
                        </button>
                        <button
                          class="px-2 py-1 rounded-lg text-xs text-red-300 hover:bg-red-900/30 disabled:opacity-50"
                          :disabled="roleDeletingId === role.id"
                          title="Eliminar"
                          @click="deleteRole(role)"
                        >
                          {{ roleDeletingId === role.id ? '...' : 'Eliminar' }}
                        </button>
                      </template>
                      <button class="p-1" @click="toggleRoleExpanded(role.id)">
                        <svg
                          class="w-4 h-4 text-gray-400 transition-transform duration-200"
                          :class="expandedRoles.includes(role.id) ? 'rotate-180' : ''"
                          fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    </div>
                  </div>

                  <!-- Accordion body -->
                  <div v-if="expandedRoles.includes(role.id)" class="border-t border-gray-800 px-5 py-4 space-y-3">
                    <p v-if="role.is_admin" class="text-sm text-gray-400">
                      Este rol tiene acceso total y todos los permisos. No se puede editar ni eliminar.
                    </p>

                    <template v-else>
                      <!-- Search -->
                      <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                          :value="permissionSearchQuery[role.id] || ''"
                          @input="onPermissionSearch(role.id, ($event.target as HTMLInputElement).value)"
                          type="search"
                          placeholder="Buscar permiso por nombre o código..."
                          class="w-full pl-10 pr-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                          v-if="permissionSearchQuery[role.id]"
                          class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white"
                          @click="clearPermissionSearch(role.id)"
                        >
                          ✕
                        </button>
                      </div>

                      <!-- Bulk actions -->
                      <div class="flex items-center gap-2">
                        <button
                          class="px-3 py-1.5 rounded-lg bg-green-600/20 border border-green-700/30 text-green-300 text-xs font-medium hover:bg-green-600/30 transition"
                          @click="selectAllPermissions(role.id)"
                        >
                          ✓ Seleccionar todos
                        </button>
                        <button
                          class="px-3 py-1.5 rounded-lg bg-red-600/20 border border-red-700/30 text-red-300 text-xs font-medium hover:bg-red-600/30 transition"
                          @click="deselectAllPermissions(role.id)"
                        >
                          ✕ Quitar todos
                        </button>
                      </div>

                      <div v-for="group in getFilteredGroups(role.id)" :key="group.prefix" class="rounded-xl border border-gray-800 bg-gray-950 overflow-hidden">
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
                                :checked="localRolePermissions[role.id]?.includes(p.code)"
                                class="sr-only peer"
                                @change="toggleRolePermission(role.id, p.code, $event)"
                              />
                              <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>
                        </div>
                      </div>
                    </template>
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
import { computed, onBeforeMount, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { DASHBOARD_POSITIONS, normalizeDashboardSlots, type DashboardSlots } from '~/utils/dashboardWidgets'
import DashboardLayoutEditor from '../components/DashboardLayoutEditor.vue'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })
declare const process: any
declare const $fetch: any

const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)
const { authenticated, fetchUser, logout, apiBase, user } = useAuth()

const planUsage = computed(() => (user.value as any)?.plan_usage || null)

const isAdminUser = computed(() => {
  const currentUser: any = user.value || {}
  return Boolean(currentUser?.is_admin)
})

const canManageSchedules = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_manage_schedules) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('schedules.manage')
})

const canManageTeams = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_manage_teams) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('teams.manage')
})

const canManageDashboard = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_manage_dashboard) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('dashboard.manage')
})

const tabs = computed(() => {
  if (isAdminUser.value) {
    return [
      { id: 'dashboard', label: 'Dashboard' },
      { id: 'permissions', label: 'Roles y permisos' },
      { id: 'schedules', label: 'Jornadas laborales' },
      { id: 'equipos', label: 'Equipos' },
    ]
  }

  const list: Array<{ id: string; label: string }> = []
  if (canManageDashboard.value) list.push({ id: 'dashboard', label: 'Dashboard' })
  if (canManageSchedules.value) list.push({ id: 'schedules', label: 'Jornadas laborales' })
  if (canManageTeams.value) list.push({ id: 'equipos', label: 'Equipos' })
  return list
})

const activeTab = ref('dashboard')
const canManagePermissions = ref(false)
const permissionsLoading = ref(false)
const permissionSaving = ref(false)
const permissionsCatalog = ref<Array<{ id: number; code: string; name: string; description: string | null }>>([])
const permissionRoles = ref<PermissionRole[]>([])
const searchQuery = ref('')
const localRolePermissions = ref<Record<number, string[]>>({})
const savedRolePermissionsSnapshot = ref<Record<number, string[]>>({})
const expandedRoles = ref<number[]>([])

const hasPermissionChanges = computed(() => {
  const current = localRolePermissions.value
  const saved = savedRolePermissionsSnapshot.value
  const roleIds = new Set([...Object.keys(current), ...Object.keys(saved)])
  for (const idStr of roleIds) {
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
  attendance: 'Fichajes',
  requests: 'Solicitudes',
  vacations: 'Vacaciones',
  documents: 'Documentos',
  reports: 'Reportes',
  schedules: 'Jornadas',
  teams: 'Equipos',
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

function toggleRoleExpanded(roleId: number) {
  const idx = expandedRoles.value.indexOf(roleId)
  if (idx >= 0) expandedRoles.value.splice(idx, 1)
  else expandedRoles.value.push(roleId)
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

// ── Company dashboard (default layout + welcome message) ──
type WelcomeSettings = { title: string; subtitle: string; show_date: boolean }

const layoutLoading = ref(false)
const layoutSaving = ref(false)
const companySlots = ref<DashboardSlots>(Array(DASHBOARD_POSITIONS).fill(null))
const welcome = ref<WelcomeSettings>({ title: '', subtitle: '', show_date: true })
const defaultSlots = ref<DashboardSlots>([])
const defaultWelcome = ref<WelcomeSettings>({ title: 'Bienvenido', subtitle: '', show_date: true })
const layoutUpdatedAt = ref<string | null>(null)
const savedLayoutSnapshot = ref('')

const layoutSnapshot = () => JSON.stringify({ slots: companySlots.value, welcome: welcome.value })
const layoutDirty = computed(() => !layoutLoading.value && savedLayoutSnapshot.value !== '' && layoutSnapshot() !== savedLayoutSnapshot.value)

const previewName = computed(() => String((user.value as any)?.first_name || (user.value as any)?.name || 'Ana').split(' ')[0])
const todayLabel = new Date().toLocaleDateString('es-ES', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })

function applyCompanyLayout(res: any) {
  companySlots.value = normalizeDashboardSlots(res?.slots)
  const w = res?.welcome || {}
  welcome.value = { title: String(w.title || ''), subtitle: String(w.subtitle || ''), show_date: w.show_date !== false }
  layoutUpdatedAt.value = res?.updated_at || null
  savedLayoutSnapshot.value = layoutSnapshot()
}

async function loadCompanyLayout() {
  if (!canManageDashboard.value) return
  layoutLoading.value = true
  try {
    const res: any = await $fetch('/api/settings/dashboard')
    defaultSlots.value = normalizeDashboardSlots(res?.default_slots)
    if (res?.default_welcome) defaultWelcome.value = { ...res.default_welcome }
    applyCompanyLayout(res)
  } catch (e) {
    $swal.toast('error', 'No se pudo cargar el dashboard de la empresa')
  } finally {
    layoutLoading.value = false
  }
}

async function saveCompanyLayout() {
  layoutSaving.value = true
  try {
    const res: any = await $fetch('/api/settings/dashboard', {
      method: 'PUT',
      body: { slots: companySlots.value, welcome: welcome.value },
    })
    applyCompanyLayout(res?.layout)
    $swal.toast('success', 'Dashboard de la empresa guardado')
  } catch (e: any) {
    const errors = e?.data?.errors
    $swal.toast('error', errors ? String(Object.values(errors).flat()[0]) : 'No se pudo guardar el dashboard')
  } finally {
    layoutSaving.value = false
  }
}

function discardLayoutChanges() {
  if (!savedLayoutSnapshot.value) return
  const saved = JSON.parse(savedLayoutSnapshot.value)
  companySlots.value = saved.slots
  welcome.value = saved.welcome
}

async function restoreLayoutDefaults() {
  const ok = await $swal.confirm(
    'Restablecer diseño original',
    'Se volverá a la disposición de fábrica de las tarjetas y al mensaje de bienvenida por defecto. No se guarda hasta que pulses "Guardar".',
    'Restablecer',
    'Cancelar',
  )
  if (!ok) return
  companySlots.value = [...defaultSlots.value]
  welcome.value = { ...defaultWelcome.value }
}

async function confirmLeaveDashboardTab(): Promise<boolean> {
  if (!layoutDirty.value) return true
  return await $swal.confirm('Cambios sin guardar', 'Tienes cambios en el dashboard de la empresa que se perderán. ¿Salir igualmente?', 'Salir sin guardar', 'Seguir editando')
}

async function selectTab(id: string) {
  if (id === activeTab.value) return
  if (activeTab.value === 'dashboard' && !(await confirmLeaveDashboardTab())) return
  if (activeTab.value === 'dashboard') discardLayoutChanges()
  activeTab.value = id
}

onBeforeRouteLeave(async () => {
  if (activeTab.value === 'dashboard' && !(await confirmLeaveDashboardTab())) return false
})

function warnBeforeUnload(event: BeforeUnloadEvent) {
  if (layoutDirty.value) {
    event.preventDefault()
    event.returnValue = ''
  }
}
onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload))
// ── End company dashboard ──

// ── Roles & permissions ──
type PermissionRole = { id: number; name: string; description: string | null; is_admin: boolean; users_count: number; permission_codes: string[] }

const roleForm = ref<{ name: string }>({ name: '' })
const editingRoleId = ref<number | null>(null)
const roleSaving = ref(false)
const roleDeletingId = ref<number | null>(null)

const filteredPermissionRoles = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return permissionRoles.value
  return permissionRoles.value.filter((r) => r.name.toLowerCase().includes(q))
})

async function loadPermissions() {
  if (!isAdminUser.value) {
    canManagePermissions.value = false
    permissionsCatalog.value = []
    permissionRoles.value = []
    return
  }
  if (!authenticated.value) return
  permissionsLoading.value = true
  try {
    const res: any = await $fetch(`/api/permissions/catalog`, {
    })
    canManagePermissions.value = true
    permissionsCatalog.value = Array.isArray(res?.permissions) ? res.permissions : []
    permissionRoles.value = Array.isArray(res?.roles) ? res.roles : []
    const map: Record<number, string[]> = {}
    for (const r of permissionRoles.value) {
      map[r.id] = Array.isArray(r.permission_codes) ? [...r.permission_codes] : []
    }
    localRolePermissions.value = map
    savedRolePermissionsSnapshot.value = JSON.parse(JSON.stringify(map))
  } catch (e: any) {
    if (e?.status === 403 || e?.data?.message === 'Forbidden') {
      canManagePermissions.value = false
    }
    permissionsCatalog.value = []
    permissionRoles.value = []
  } finally {
    permissionsLoading.value = false
  }
}

function toggleRolePermission(roleId: number, code: string, e: Event) {
  const checked = (e.target as HTMLInputElement).checked
  const current = localRolePermissions.value[roleId] ? [...localRolePermissions.value[roleId]] : []
  const set = new Set(current)
  if (checked) set.add(code)
  else set.delete(code)
  localRolePermissions.value[roleId] = Array.from(set)
}

function hasChangedRole(roleId: number): boolean {
  const curr = (localRolePermissions.value[roleId] || []).slice().sort()
  const svd = (savedRolePermissionsSnapshot.value[roleId] || []).slice().sort()
  return curr.length !== svd.length || curr.some((c, i) => c !== svd[i])
}

function selectAllPermissions(roleId: number) {
  const allCodes = permissionsCatalog.value.map((p) => p.code)
  localRolePermissions.value[roleId] = [...allCodes]
}

async function deselectAllPermissions(roleId: number) {
  const ok = await $swal.confirm('Quitar todos los permisos', '¿Estás seguro de quitar todos los permisos de este rol? Los cambios son locales hasta que guardes.', 'Sí, quitar todos', 'Cancelar')
  if (!ok) return
  localRolePermissions.value[roleId] = []
}

async function saveAllPermissions() {
  if (!authenticated.value || !canManagePermissions.value) return
  permissionSaving.value = true
  try {
    for (const role of permissionRoles.value) {
      // the admin role always has every permission; only send roles that changed
      if (role.is_admin || !hasChangedRole(role.id)) continue
      const codes = localRolePermissions.value[role.id] || []
      await $fetch(`/api/roles/${role.id}/permissions`, {
        method: 'PUT',
        body: { permission_codes: codes },
      })
      role.permission_codes = [...codes]
    }
    savedRolePermissionsSnapshot.value = JSON.parse(JSON.stringify(localRolePermissions.value))
    $swal.toast('success', 'Permisos actualizados')
  } catch (e) {
    $swal.toast('error', 'No se pudieron guardar los permisos')
  } finally {
    permissionSaving.value = false
  }
}

function editRole(role: PermissionRole) {
  editingRoleId.value = role.id
  roleForm.value = { name: role.name }
}

function cancelEditRole() {
  editingRoleId.value = null
  roleForm.value = { name: '' }
}

async function saveRole() {
  const name = roleForm.value.name.trim()
  if (!name || !authenticated.value) return
  if (hasPermissionChanges.value) {
    const ok = await $swal.confirm('Cambios sin guardar', 'Tienes cambios de permisos sin guardar que se perderán al recargar los roles. ¿Continuar?', 'Continuar', 'Cancelar')
    if (!ok) return
  }
  roleSaving.value = true
  try {
    if (editingRoleId.value) {
      await $fetch(`/api/roles/${editingRoleId.value}`, { method: 'PUT', body: { name } })
      $swal.toast('success', 'Rol renombrado')
    } else {
      await $fetch(`/api/roles`, { method: 'POST', body: { name, permission_codes: [] } })
      $swal.toast('success', 'Rol creado')
    }
    cancelEditRole()
    await loadPermissions()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo guardar el rol')
  } finally {
    roleSaving.value = false
  }
}

async function deleteRole(role: PermissionRole) {
  const ok = await $swal.confirm('Eliminar rol', `¿Eliminar el rol "${role.name}"? Solo se pueden eliminar roles sin usuarios.`, 'Eliminar', 'Cancelar')
  if (!ok) return
  roleDeletingId.value = role.id
  try {
    await $fetch(`/api/roles/${role.id}`, { method: 'DELETE' })
    if (editingRoleId.value === role.id) cancelEditRole()
    $swal.toast('success', 'Rol eliminado')
    await loadPermissions()
  } catch (e: any) {
    $swal.toast('error', e?.data?.message || 'No se pudo eliminar el rol')
  } finally {
    roleDeletingId.value = null
  }
}
// ── End roles & permissions ──

async function loadScheduleTemplates() {
  if (!authenticated.value) return
  schedulesLoading.value = true
  try {
    const res: any = await $fetch(`/api/settings/schedules`, {
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
  if (!authenticated.value) return
  scheduleSaving.value = true
  try {
    const method = editingScheduleId.value ? 'PUT' : 'POST'
    const endpoint = editingScheduleId.value
      ? `/api/settings/schedules/${editingScheduleId.value}`
      : `/api/settings/schedules`

    await $fetch(endpoint, {
      method,
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
    await $fetch(`/api/settings/schedules/${id}`, {
      method: 'DELETE',
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
  if (!authenticated.value) return
  teamsLoading.value = true
  try {
    const res: any = await $fetch(`/api/settings/teams`, {
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
  if (!authenticated.value || !teamForm.value.name.trim()) return
  teamSaving.value = true
  try {
    const method = editingTeamId.value ? 'PUT' : 'POST'
    const endpoint = editingTeamId.value
      ? `/api/settings/teams/${editingTeamId.value}`
      : `/api/settings/teams`

    await $fetch(endpoint, {
      method,
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
    await $fetch(`/api/settings/teams/${id}`, {
      method: 'DELETE',
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
  await fetchUser()

  const canAccessSettings = Boolean((user.value as any)?.can_access_settings)
  if (!canAccessSettings) {
    return router.push('/dashboard')
  }

  await loadCompanyLayout()
  await loadPermissions()
  await loadScheduleTemplates()
})


</script>
