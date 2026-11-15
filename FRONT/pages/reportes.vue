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
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <h1 class="text-base font-semibold text-white hidden sm:block">Fichajes</h1>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" @changed="handleAttendanceChanged" />
          <UserMenu />
        </div>
      </header>

      <main class="relative z-10 flex-1 min-h-0 p-3 sm:p-4 lg:p-6 overflow-auto">
        <!-- fixed-height panels with inner scroll only on desktop; phones scroll the page -->
        <div class="xl:h-full flex flex-col gap-4">
          <div class="max-w-full">
            <div class="flex flex-wrap rounded-xl border border-gray-700 p-1 bg-gray-900 gap-1">
              <button
                class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                :class="activeTab === 'monthly' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="activeTab = 'monthly'"
              >
                Mensual
              </button>
              <button
                class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                :class="activeTab === 'who_is_in' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="activeTab = 'who_is_in'"
              >
                Who's in
              </button>
              <button
                class="px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                :class="activeTab === 'absences' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                @click="activeTab = 'absences'"
              >
                Ausencias
              </button>
            </div>
          </div>

          <div
            v-if="requestNotice.text"
            class="rounded-xl border px-3 py-2 text-sm"
            :class="requestNotice.type === 'error'
              ? 'border-red-500/30 bg-red-500/10 text-red-200'
              : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'"
          >
            {{ requestNotice.text }}
          </div>

          <div v-if="activeTab === 'monthly'" class="xl:h-full xl:min-h-0 grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-4">
            <aside
              class="bg-gray-900 border border-gray-800 rounded-3xl p-4 flex-col min-h-0 shadow-sm"
              :class="canViewAll ? 'flex' : 'hidden xl:flex'"
            >
              <h2 class="text-sm font-semibold text-white mb-3">
                {{ canViewAll ? 'Usuarios' : 'Mis fichajes' }}
              </h2>

              <div v-if="canViewAll" class="mb-3">
                <label class="block text-xs text-gray-400 mb-1">Equipo</label>
                <select
                  v-model="teamFilter"
                  class="w-full bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-xl px-2.5 py-2 focus:outline-none"
                >
                  <option value="">Todos los equipos</option>
                  <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
              </div>

              <!-- phones/tablets: a dropdown instead of the long list of people -->
              <div v-if="canViewAll" class="xl:hidden">
                <label class="block text-xs text-gray-400 mb-1">Usuario</label>
                <select
                  :value="selectedUserId ?? ''"
                  class="w-full bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-xl px-2.5 py-2 focus:outline-none"
                  @change="selectUser(Number(($event.target as HTMLSelectElement).value))"
                >
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.full_name }}{{ u.team_name ? ` · ${u.team_name}` : '' }}</option>
                </select>
              </div>

              <div class="hidden xl:block flex-1 overflow-y-auto space-y-2 pr-1">
                <button
                  v-for="u in users"
                  :key="u.id"
                  @click="selectUser(u.id)"
                  :class="[
                    'w-full text-left p-2.5 rounded-xl border transition',
                    selectedUserId === u.id
                      ? 'bg-blue-600/20 border-blue-500 text-white'
                      : 'bg-gray-800 border-gray-700 text-gray-200 hover:bg-gray-700'
                  ]"
                >
                  <div class="flex items-center gap-2.5">
                    <div class="relative shrink-0">
                      <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs font-semibold">
                        <img v-if="u.photo" :src="u.photo" class="w-full h-full object-cover" />
                        <span v-else>{{ (u.full_name || '?').charAt(0).toUpperCase() }}</span>
                      </div>
                      <PresenceBadge :user-id="u.id" size="xs" />
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm truncate">{{ u.full_name }}</div>
                      <div class="text-[11px] text-gray-400 truncate">{{ u.team_name || 'Sin equipo' }}</div>
                    </div>
                  </div>
                </button>
              </div>
            </aside>

            <section class="min-w-0 bg-gray-900 border border-gray-800 rounded-3xl p-3 sm:p-5 flex flex-col xl:min-h-0 xl:overflow-hidden shadow-sm">
              <div class="flex flex-col gap-3 mb-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    @click="changeMonth(-1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes anterior"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div class="h-10 sm:h-11 min-w-[140px] sm:min-w-[180px] px-3 sm:px-4 rounded-xl border border-gray-700 bg-gray-800 text-white text-sm sm:text-base font-medium flex items-center justify-center">
                    {{ monthLabel }}
                  </div>
                  <button
                    @click="changeMonth(1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes siguiente"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                  <div class="hidden sm:flex h-11 px-4 rounded-xl border border-gray-700 bg-gray-800 text-gray-300 text-sm items-center">
                    Mensual
                  </div>
                </div>
              </div>

              <div class="text-xs text-gray-400 mb-3">
                Usuario seleccionado:
                <span class="text-gray-200 font-medium">{{ selectedUserName }}</span>
              </div>
              <div class="mb-4 flex flex-wrap gap-2">
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Horas trabajadas:
                  <span class="text-white font-semibold">{{ summary.worked_hhmm }}</span>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Horas a trabajar:
                  <span class="text-white font-semibold">{{ summary.target_hhmm }}</span>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                  Cumplimiento:
                  <span class="text-white font-semibold">{{ summaryPercent }}%</span>
                </div>
                <label class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 inline-flex items-center gap-2 text-xs text-gray-300 cursor-pointer select-none">
                  <span>No laborables</span>
                  <button
                    type="button"
                    @click="includeNonWorkingDays = !includeNonWorkingDays"
                    :class="[
                      'relative inline-flex h-5 w-10 items-center rounded-full transition',
                      includeNonWorkingDays ? 'bg-blue-600' : 'bg-gray-600'
                    ]"
                    aria-label="Mostrar no laborables"
                  >
                    <span
                      :class="[
                        'inline-block h-4 w-4 transform rounded-full bg-white transition',
                        includeNonWorkingDays ? 'translate-x-5' : 'translate-x-1'
                      ]"
                    />
                  </button>
                </label>
              </div>

              <!-- fits the screen: narrower date/hours columns and fewer hour labels on phones -->
              <div class="xl:flex-1 xl:min-h-0 overflow-y-auto rounded-2xl border border-gray-800">
                <div class="lg:min-w-[760px]">
                  <div class="grid grid-cols-[68px_48px_minmax(0,1fr)] sm:grid-cols-[150px_110px_minmax(0,1fr)] gap-x-2 px-2 sm:px-3 py-2 text-gray-400 text-xs sm:text-sm border-b border-gray-800 bg-gray-800/50 sticky top-0 z-10">
                    <div>Fecha</div>
                    <div>Horas</div>
                    <div class="relative h-4 sm:h-5">
                      <span
                        v-for="(h, idx) in hourMarks"
                        :key="h"
                        class="absolute top-0 text-[10px] sm:text-xs tabular-nums whitespace-nowrap"
                        :class="[
                          idx === 0 ? '' : idx === hourMarks.length - 1 ? '-translate-x-full' : '-translate-x-1/2',
                          h % 3 === 0 ? '' : 'hidden md:inline'
                        ]"
                        :style="{ left: `${hourTicks[idx]}%` }"
                      >{{ h }}:00</span>
                    </div>
                  </div>

                  <div
                    v-for="row in monthRows"
                    :key="row.date"
                    class="grid grid-cols-[68px_48px_minmax(0,1fr)] sm:grid-cols-[150px_110px_minmax(0,1fr)] gap-x-2 px-2 sm:px-3 py-3 border-b border-gray-800 items-center"
                  >
                    <div class="min-w-0 text-gray-200 text-xs sm:text-sm">
                      <div class="font-medium">{{ shortDate(row.date) }}</div>
                      <div class="text-xs text-gray-500 uppercase">{{ row.weekday }}</div>
                      <div v-if="row.holiday" class="mt-0.5 text-[10px] truncate" :class="row.holiday_working ? 'text-amber-300' : 'text-blue-300'" :title="row.holiday">{{ row.holiday_working ? 'Festivo laborable' : 'Festivo' }}: {{ row.holiday }}</div>
                    </div>
                    <div class="text-gray-200 text-xs sm:text-sm font-medium">{{ workedLabel(row) }}</div>
                    <div class="relative h-5 rounded-full bg-gray-800 overflow-hidden">
                      <div
                        v-for="(tick, idx) in hourTicks"
                        :key="idx"
                        class="absolute top-0 bottom-0 w-px bg-gray-700"
                        :style="{ left: `${tick}%` }"
                      />
                      <div
                        v-for="(seg, idx) in timelineSegments(row)"
                        :key="idx"
                        class="absolute top-0 h-5"
                        :class="seg.className"
                        :style="{ left: `${seg.left}%`, width: `${seg.width}%`, ...(seg.style || {}) }"
                        :title="seg.title || ''"
                      />
                    </div>
                  </div>

                  <div v-if="!monthRows.length" class="px-3 py-10 text-center text-gray-500 text-sm">
                    No hay datos para este mes.
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div v-else-if="activeTab === 'absences'" class="xl:h-full xl:min-h-0 grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-4">
            <aside
              class="bg-gray-900 border border-gray-800 rounded-3xl p-4 flex-col min-h-0 shadow-sm"
              :class="canViewAll ? 'flex' : 'hidden xl:flex'"
            >
              <h2 class="text-sm font-semibold text-white mb-3">
                {{ canViewAll ? 'Usuarios' : 'Mis ausencias' }}
              </h2>

              <div v-if="canViewAll" class="mb-3">
                <label class="block text-xs text-gray-400 mb-1">Equipo</label>
                <select
                  v-model="teamFilter"
                  class="w-full bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-xl px-2.5 py-2 focus:outline-none"
                >
                  <option value="">Todos los equipos</option>
                  <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
              </div>

              <!-- phones/tablets: a dropdown instead of the long list of people -->
              <div v-if="canViewAll" class="xl:hidden">
                <label class="block text-xs text-gray-400 mb-1">Usuario</label>
                <select
                  :value="selectedUserId ?? ''"
                  class="w-full bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-xl px-2.5 py-2 focus:outline-none"
                  @change="selectUser(Number(($event.target as HTMLSelectElement).value))"
                >
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.full_name }}{{ u.team_name ? ` · ${u.team_name}` : '' }}</option>
                </select>
              </div>

              <div class="hidden xl:block flex-1 overflow-y-auto space-y-2 pr-1">
                <button
                  v-for="u in users"
                  :key="u.id"
                  @click="selectUser(u.id)"
                  :class="[
                    'w-full text-left p-2.5 rounded-xl border transition',
                    selectedUserId === u.id
                      ? 'bg-blue-600/20 border-blue-500 text-white'
                      : 'bg-gray-800 border-gray-700 text-gray-200 hover:bg-gray-700'
                  ]"
                >
                  <div class="flex items-center gap-2.5">
                    <div class="relative shrink-0">
                      <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-700 flex items-center justify-center text-xs font-semibold">
                        <img v-if="u.photo" :src="u.photo" class="w-full h-full object-cover" />
                        <span v-else>{{ (u.full_name || '?').charAt(0).toUpperCase() }}</span>
                      </div>
                      <PresenceBadge :user-id="u.id" size="xs" />
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm truncate">{{ u.full_name }}</div>
                      <div class="text-[11px] text-gray-400 truncate">{{ u.team_name || 'Sin equipo' }}</div>
                    </div>
                  </div>
                </button>
              </div>
            </aside>

            <section class="min-w-0 bg-gray-900 border border-gray-800 rounded-3xl p-3 sm:p-5 flex flex-col xl:min-h-0 xl:overflow-hidden shadow-sm">
              <div class="flex flex-col gap-3 mb-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    @click="changeMonth(-1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes anterior"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div class="h-10 sm:h-11 min-w-[140px] sm:min-w-[180px] px-3 sm:px-4 rounded-xl border border-gray-700 bg-gray-800 text-white text-sm sm:text-base font-medium flex items-center justify-center">
                    {{ monthLabel }}
                  </div>
                  <button
                    @click="changeMonth(1)"
                    class="h-10 w-10 sm:h-11 sm:w-11 rounded-xl border border-gray-700 text-gray-300 hover:bg-gray-800"
                    aria-label="Mes siguiente"
                  >
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>

                <div class="px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-200">
                  Ausencias del mes:
                  <span class="font-semibold">{{ absencesCount }}</span>
                </div>
              </div>

              <div class="text-xs text-gray-400 mb-3">
                Usuario seleccionado:
                <span class="text-gray-200 font-medium">{{ selectedUserName }}</span>
              </div>
              <p class="text-xs text-gray-500 mb-3">
                Se listan solo días laborables sin fichaje, excluyendo vacaciones y días con comprobante cargado.
              </p>

              <!-- own absences: pick the days and ask RRHH to regularise them in one request -->
              <div v-if="isOwnSelectedUser && requestableAbsences.length" class="mb-3 flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  class="h-10 px-4 rounded-xl text-white text-sm font-medium bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="requestingAttendance || !selectedAbsenceDates.length"
                  @click="requestSelectedAbsences"
                >
                  {{ requestingAttendance ? 'Enviando...' : `Solicitar fichaje (${selectedAbsenceDates.length} ${selectedAbsenceDates.length === 1 ? 'día' : 'días'})` }}
                </button>
                <button
                  type="button"
                  class="h-10 px-3 rounded-xl border border-gray-700 text-gray-300 text-sm hover:bg-gray-800"
                  @click="toggleAllAbsences"
                >
                  {{ allAbsencesSelected ? 'Quitar todos' : 'Seleccionar todos' }}
                </button>
                <span class="text-[11px] text-gray-500">Se usará el horario de tu jornada; RRHH aprueba cada día.</span>
              </div>

              <div class="xl:flex-1 xl:min-h-0 overflow-y-auto rounded-2xl border border-gray-800">
                <div class="grid grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_auto] sm:grid-cols-[auto_160px_120px_minmax(0,1fr)] gap-x-3 px-3 sm:px-4 py-3 text-gray-400 text-xs sm:text-sm border-b border-gray-800 bg-gray-800/50 sticky top-0 z-10">
                  <div class="w-4"></div>
                  <div>Fecha</div>
                  <div>Día</div>
                  <div>Estado</div>
                </div>

                <label
                  v-for="row in absencesRows"
                  :key="`absence-${row.date}`"
                  class="!mb-0 !font-normal grid grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_auto] sm:grid-cols-[auto_160px_120px_minmax(0,1fr)] gap-x-3 px-3 sm:px-4 py-3 border-b border-gray-800 items-center"
                  :class="canSelectAbsence(row) ? 'cursor-pointer hover:bg-gray-800/40' : ''"
                >
                  <div class="w-4">
                    <input
                      v-if="canSelectAbsence(row)"
                      v-model="selectedAbsenceDates"
                      type="checkbox"
                      :value="row.date"
                      class="accent-blue-500 w-4 h-4"
                      :aria-label="`Seleccionar ${shortDate(row.date)}`"
                    />
                  </div>
                  <div class="text-gray-200 text-sm font-medium">{{ shortDate(row.date) }}</div>
                  <div class="text-gray-400 text-sm">{{ row.weekday }}</div>
                  <div>
                    <span
                      v-if="row.attendance_request_pending"
                      class="whitespace-nowrap px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-xs border border-amber-500/40 bg-amber-500/15 text-amber-200 uppercase tracking-wide"
                    >Solicitado</span>
                    <span v-else class="whitespace-nowrap px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-xs border border-red-500/40 bg-red-500/15 text-red-200 uppercase tracking-wide">
                      Sin fichaje
                    </span>
                  </div>
                </label>

                <div v-if="!absencesRows.length" class="px-4 py-12 text-center text-gray-500 text-sm">
                  No hay ausencias para los filtros seleccionados.
                </div>
              </div>
            </section>
          </div>

          <div v-else class="xl:h-full xl:min-h-0 grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4">
            <section class="min-w-0 bg-gray-900 border border-gray-800 rounded-3xl p-3 sm:p-5 flex flex-col xl:min-h-0 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                  <h2 class="text-white font-semibold text-lg">Trabajando ahora</h2>
                  <p class="text-xs text-gray-400 mt-1">Personas con jornada activa hoy</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <div class="whitespace-nowrap px-3 py-1 rounded-full border border-emerald-500/40 bg-emerald-500/15 text-emerald-100 text-xs font-medium">
                    {{ whoIsInWorkingCount }} trabajando
                  </div>
                  <select
                    v-if="canViewAllWhoIsIn"
                    v-model="whoIsInTeamFilter"
                    class="h-9 min-w-0 max-w-[180px] rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm px-2.5"
                  >
                    <option value="">Todos los equipos</option>
                    <option v-for="t in whoIsInTeams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                  </select>
                </div>
              </div>

              <div v-if="whoIsInLoading" class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-3">
                <div v-for="i in 16" :key="i" class="h-16 rounded-xl bg-gray-800 animate-pulse"></div>
              </div>

              <div v-else class="flex-1 min-h-0 overflow-y-auto">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-4">
                  <div v-for="p in whoIsInRows" :key="p.attendance_id" class="flex flex-col items-center gap-1">
                    <div class="relative">
                      <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-emerald-400/70 bg-gray-700 flex items-center justify-center">
                        <img v-if="p.photo" :src="p.photo" class="w-full h-full object-cover" />
                        <span v-else class="text-xs text-gray-200 font-semibold">{{ (p.full_name || '?').charAt(0) }}</span>
                      </div>
                      <PresenceBadge :user-id="p.user_id" size="md" />
                    </div>
                    <p class="text-[11px] text-gray-200 text-center leading-tight line-clamp-2">{{ p.full_name }}</p>
                    <p class="text-[10px] text-gray-500 text-center">{{ p.team_name || 'Sin equipo' }}</p>
                  </div>
                </div>

                <div v-if="!whoIsInRows.length" class="text-center text-sm text-gray-500 py-12">
                  No hay personas trabajando en este momento.
                </div>

                <!-- approved remote work / vacation today -->
                <div v-for="group in presenceGroups" :key="group.id" class="mt-6 pt-4 border-t border-gray-800">
                  <div class="flex items-center gap-2 mb-3">
                    <span class="w-6 h-6 rounded-md flex items-center justify-center" :class="group.tone">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="group.icon" />
                      </svg>
                    </span>
                    <h3 class="text-sm font-semibold text-white">{{ group.title }}</h3>
                    <span class="text-xs text-gray-500">({{ group.rows.length }})</span>
                  </div>
                  <div v-if="group.rows.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-4">
                    <div v-for="p in group.rows" :key="`${group.id}-${p.user_id}`" class="flex flex-col items-center gap-1">
                      <div class="relative">
                        <div class="w-12 h-12 rounded-full overflow-hidden border-2 bg-gray-700 flex items-center justify-center" :class="group.ring">
                          <img v-if="p.photo" :src="p.photo" class="w-full h-full object-cover" />
                          <span v-else class="text-xs text-gray-200 font-semibold">{{ (p.full_name || '?').charAt(0) }}</span>
                        </div>
                        <PresenceBadge :user-id="p.user_id" />
                      </div>
                      <p class="text-[11px] text-gray-200 text-center leading-tight line-clamp-2">{{ p.full_name }}</p>
                      <p class="text-[10px] text-gray-500 text-center">
                        {{ p.team_name || 'Sin equipo' }}<template v-if="group.id === 'remote'"> · {{ p.working ? 'fichó' : 'sin fichar' }}</template>
                      </p>
                    </div>
                  </div>
                  <p v-else class="text-xs text-gray-500">{{ group.empty }}</p>
                </div>
              </div>
            </section>

            <aside class="min-w-0 bg-gray-900 border border-gray-800 rounded-3xl p-3 sm:p-5 flex flex-col xl:min-h-0 shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-white">Detalle de fichajes</h3>
                <button class="text-xs text-gray-300 border border-gray-700 rounded-lg px-2.5 py-1.5 hover:bg-gray-800" @click="loadWhoIsInData">
                  Actualizar
                </button>
              </div>

              <div class="flex-1 overflow-y-auto space-y-2">
                <div
                  v-for="p in whoIsInRows"
                  :key="`line-${p.attendance_id}`"
                  class="rounded-xl border border-gray-800 bg-gray-800/70 px-3 py-2"
                >
                  <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-sm text-white truncate">{{ p.full_name }}</p>
                      <p class="text-[11px] text-gray-400">Fichaje de entrada: {{ p.start_time || '--:--' }}</p>
                    </div>
                    <div class="text-sm font-semibold tabular-nums" :class="workedColorClass(p.worked_seconds)">
                      +{{ p.worked_hhmm || '00:00' }}
                    </div>
                  </div>
                </div>

                <div v-if="!whoIsInRows.length" class="text-sm text-gray-500 text-center py-8">
                  Sin movimientos activos.
                </div>
              </div>
            </aside>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeMount, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import AppSidebar from '../components/AppSidebar.vue'
import AttendanceButton from '../components/AttendanceButton.vue'
import UserMenu from '../components/UserMenu.vue'

definePageMeta({ auth: true })

declare const process: any
declare const $fetch: any

const { authenticated, fetchUser, logout, apiBase, user } = useAuth()
const router = useRouter()
const sidebar = ref<{ open: boolean } | null>(null)

const activeTab = ref<'monthly' | 'who_is_in' | 'absences'>('monthly')

const canViewAll = ref(false)
const users = ref<any[]>([])
const teams = ref<any[]>([])
const monthRows = ref<any[]>([])
const absencesRows = ref<any[]>([])
const absencesCount = ref(0)
const summary = ref<{ worked_hhmm: string; target_hhmm: string; worked_minutes: number; target_minutes: number }>({
  worked_hhmm: '00:00',
  target_hhmm: '00:00',
  worked_minutes: 0,
  target_minutes: 0,
})
const selectedUserId = ref<number | null>(null)
const teamFilter = ref('')
const selectedMonth = ref(localIsoDate().slice(0, 7))
const includeNonWorkingDays = ref(false)

const canViewAllWhoIsIn = ref(false)
const whoIsInRows = ref<any[]>([])
// approved remote work / vacation today (who's in)
const remoteRows = ref<any[]>([])
const vacationRows = ref<any[]>([])
const presenceGroups = computed(() => [
  {
    id: 'remote',
    title: 'Teletrabajo hoy',
    rows: remoteRows.value,
    empty: 'Nadie tiene teletrabajo hoy.',
    tone: eventTypeVisual('remote_work').tone,
    icon: eventTypeVisual('remote_work').icon,
    ring: 'border-sky-400/70',
  },
  {
    id: 'vacation',
    title: 'De vacaciones hoy',
    rows: vacationRows.value,
    empty: 'Nadie está de vacaciones hoy.',
    tone: eventTypeVisual('vacation').tone,
    icon: eventTypeVisual('vacation').icon,
    ring: 'border-teal-400/70',
  },
])
const whoIsInLoading = ref(false)
const whoIsInTeamFilter = ref('')
const whoIsInTeams = ref<any[]>([])
const whoIsInWorkingCount = ref(0)
let whoIsInInterval: any = null
const requestingAttendance = ref(false)
const requestNotice = ref<{ type: 'success' | 'error'; text: string }>({ type: 'success', text: '' })

const dayStart = 6 * 60
const dayEnd = 19 * 60

const hourMarks = computed(() => Array.from({ length: 14 }, (_, i) => 6 + i))
const hourTicks = computed(() => hourMarks.value.map((h) => ((h * 60 - dayStart) / (dayEnd - dayStart)) * 100))

const selectedUserName = computed(() => {
  const u = users.value.find((x) => x.id === selectedUserId.value)
  return u?.full_name || 'Sin usuario'
})
const summaryPercent = computed(() => {
  if (!summary.value.target_minutes) return 0
  return Math.round((summary.value.worked_minutes / summary.value.target_minutes) * 100)
})

const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1, 1)
  return d.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})
const currentUserId = computed(() => Number((user.value as any)?.id || 0))
const todayIso = computed(() => localIsoDate())
const isOwnSelectedUser = computed(() => Number(selectedUserId.value || 0) === currentUserId.value)
function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function shortDate(dateIso: string) {
  const d = parseDate(dateIso)
  return d ? d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : dateIso
}

function toMinutes(v?: string | null) {
  if (!v) return null
  const p = String(v).split(':').map((x) => Number(x))
  if (p.length < 2 || Number.isNaN(p[0]) || Number.isNaN(p[1])) return null
  return p[0] * 60 + p[1] + ((p[2] || 0) / 60)
}

function pct(mins: number) {
  const clamped = Math.max(dayStart, Math.min(dayEnd, mins))
  return ((clamped - dayStart) / (dayEnd - dayStart)) * 100
}

function workedMinutes(row: any) {
  if (row.hours_worked) {
    const p = String(row.hours_worked).split(':').map((x) => Number(x))
    if (p.length >= 2 && !Number.isNaN(p[0]) && !Number.isNaN(p[1])) {
      return p[0] * 60 + p[1]
    }
  }
  const start = toMinutes(row.start_time)
  const end = toMinutes(row.end_time)
  if (start === null || end === null || end <= start) return 0
  let total = end - start
  const pause = toMinutes(row.pause_time)
  const resume = toMinutes(row.resume_time)
  if (pause !== null && pause > start && pause < end) {
    const pauseEnd = resume !== null && resume > pause && resume < end ? resume : end
    total -= (pauseEnd - pause)
  }
  return Math.max(0, Math.round(total))
}

function workedLabel(row: any) {
  const mins = workedMinutes(row)
  if (!mins) return '--:-- h'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${h}:${String(m).padStart(2, '0')} h`
}

function timelineSegments(row: any) {
  const start = toMinutes(row.start_time)
  const pause = toMinutes(row.pause_time)
  const resume = toMinutes(row.resume_time)
  const end = toMinutes(row.end_time)
  const segs: Array<{ left: number; width: number; className: string; style?: Record<string, string>; title?: string }> = []

  if (start !== null && end !== null && end > start) {
    const work1End = pause && pause > start && pause < end ? pause : end
    const l1 = pct(start)
    const w1 = Math.max(0, pct(work1End) - l1)
    if (w1 > 0) segs.push({ left: l1, width: w1, className: 'bg-green-500/90', title: 'Trabajo' })

    if (pause && pause < end) {
      const pauseEnd = resume && resume > pause && resume < end ? resume : end
      const lp = pct(pause)
      const wp = Math.max(0, pct(pauseEnd) - lp)
      if (wp > 0) segs.push({ left: lp, width: wp, className: 'bg-yellow-400/90', title: 'Pausa' })
    }

    if (resume && resume < end) {
      const l2 = pct(resume)
      const w2 = Math.max(0, pct(end) - l2)
      if (w2 > 0) segs.push({ left: l2, width: w2, className: 'bg-green-500/90', title: 'Trabajo' })
    }
  }

  for (const ev of row.events || []) {
    const color = ev?.color || '#60A5FA'
    const title = ev?.title ? `${ev.title} (${ev.event_type_name || 'Evento'})` : (ev?.event_type_name || 'Evento')
    if (ev?.all_day) {
      segs.push({
        left: 0,
        width: 100,
        className: 'border',
        style: { backgroundColor: `${color}55`, borderColor: color, zIndex: '3' },
        title,
      })
      continue
    }
    const evStart = toMinutes(ev?.start_time)
    const evEnd = toMinutes(ev?.end_time)
    if (evStart === null || evEnd === null || evEnd <= evStart) continue
    const left = pct(evStart)
    const width = Math.max(0, pct(evEnd) - left)
    if (width <= 0) continue
    segs.push({
      left,
      width,
      className: '',
      style: { backgroundColor: color, opacity: '0.85', zIndex: '3' },
      title,
    })
  }

  return segs
}

function changeMonth(delta: number) {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1 + delta, 1)
  const yy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  selectedMonth.value = `${yy}-${mm}`
}

function workedColorClass(seconds?: number) {
  const s = Number(seconds || 0)
  if (s >= 8 * 3600) return 'text-emerald-300'
  if (s >= 4 * 3600) return 'text-cyan-300'
  return 'text-amber-300'
}

// ── Ausencias: ask RRHH to regularise several days in one request ──
const selectedAbsenceDates = ref<string[]>([])
const canSelectAbsence = (row: any) => isOwnSelectedUser.value && !row?.attendance_request_pending
const requestableAbsences = computed(() => absencesRows.value.filter(canSelectAbsence))
const allAbsencesSelected = computed(() =>
  requestableAbsences.value.length > 0 && selectedAbsenceDates.value.length === requestableAbsences.value.length,
)

function toggleAllAbsences() {
  selectedAbsenceDates.value = allAbsencesSelected.value ? [] : requestableAbsences.value.map((r: any) => r.date)
}

async function requestSelectedAbsences() {
  if (!selectedAbsenceDates.value.length || requestingAttendance.value) return
  requestingAttendance.value = true
  requestNotice.value = { type: 'success', text: '' }
  try {
    const res: any = await $fetch('/api/attendance/requests/days', {
      method: 'POST',
      body: { dates: [...selectedAbsenceDates.value].sort() },
    })
    const n = (res?.days || []).length
    const skipped = (res?.skipped || []).length
    requestNotice.value = {
      type: 'success',
      text: `Solicitud de fichaje enviada a RRHH por ${n} ${n === 1 ? 'día' : 'días'}.${skipped ? ` ${skipped} ${skipped === 1 ? 'día no necesitaba' : 'días no necesitaban'} solicitud.` : ''}`,
    }
    selectedAbsenceDates.value = []
    await loadMonthData()
  } catch (e: any) {
    const errors = e?.data?.errors
    requestNotice.value = { type: 'error', text: errors ? String(Object.values(errors).flat()[0]) : (e?.data?.message || 'No se pudo enviar la solicitud de fichaje.') }
  } finally {
    requestingAttendance.value = false
  }
}

async function loadMonthData() {
  if (!authenticated.value) return
  const params = new URLSearchParams()
  params.set('month', selectedMonth.value)
  if (teamFilter.value) params.set('team_id', teamFilter.value)
  if (selectedUserId.value) params.set('user_id', String(selectedUserId.value))
  params.set('include_non_working', includeNonWorkingDays.value ? '1' : '0')

  const res: any = await $fetch(`/api/attendance/month?${params.toString()}`, {
  })

  canViewAll.value = !!res?.can_view_all
  users.value = res?.users || []
  teams.value = res?.teams || []
  monthRows.value = res?.rows || []
  absencesRows.value = res?.absences?.rows || []
  selectedAbsenceDates.value = []
  absencesCount.value = Number(res?.absences?.count || absencesRows.value.length || 0)
  summary.value = res?.summary || summary.value
  includeNonWorkingDays.value = !!res?.include_non_working
  selectedUserId.value = res?.selected_user_id || null
  selectedMonth.value = res?.month || selectedMonth.value
}

async function loadWhoIsInData() {
  if (!authenticated.value) return
  whoIsInLoading.value = true
  try {
    const params = new URLSearchParams()
    if (whoIsInTeamFilter.value) params.set('team_id', whoIsInTeamFilter.value)
    const qs = params.toString()
    const res: any = await $fetch(`/api/attendance/who-is-in${qs ? `?${qs}` : ''}`, {
    })

    canViewAllWhoIsIn.value = !!res?.can_view_all
    whoIsInRows.value = res?.rows || []
    remoteRows.value = res?.remote_rows || []
    vacationRows.value = res?.vacation_rows || []
    whoIsInTeams.value = res?.teams || []
    whoIsInWorkingCount.value = Number(res?.working_count || 0)
  } catch (e) {
    console.error('who-is-in load failed', e)
    whoIsInRows.value = []
    whoIsInWorkingCount.value = 0
  } finally {
    whoIsInLoading.value = false
  }
}

function selectUser(id: number) {
  if (selectedUserId.value === id) return
  selectedUserId.value = id
  loadMonthData()
}

async function handleAttendanceChanged() {
  if (activeTab.value === 'who_is_in') {
    await loadWhoIsInData()
  } else {
    await loadMonthData()
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

watch([selectedMonth, teamFilter, includeNonWorkingDays], () => {
  if (activeTab.value === 'who_is_in') return
  loadMonthData()
})

watch(whoIsInTeamFilter, () => {
  if (activeTab.value !== 'who_is_in') return
  loadWhoIsInData()
})

watch(activeTab, async (tab) => {
  if (tab === 'who_is_in') {
    await loadWhoIsInData()
    return
  }
  await loadMonthData()
})

onBeforeMount(async () => {
  await fetchUser()

  const currentUser: any = user.value || {}
  const canView = Boolean(currentUser?.can_view_reports) || (Array.isArray(currentUser?.permissions) && currentUser.permissions.includes('reports.view'))
  if (!canView) {
    return router.push('/dashboard')
  }

  await loadMonthData()

  whoIsInInterval = setInterval(() => {
    if (activeTab.value !== 'who_is_in') return
    loadWhoIsInData()
  }, 30000)
})

onBeforeUnmount(() => {
  if (whoIsInInterval) clearInterval(whoIsInInterval)
})
</script>
