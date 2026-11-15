<template>
  <div class="flex min-h-screen bg-gray-950">
    <AppSidebar ref="sidebar" @logout="onLogout" />

    <div class="flex-1 flex flex-col min-w-0 relative z-10">
      <header class="relative z-40 h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-3 sm:px-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur">
        <button
          class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition"
          @click="openSidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <div class="hidden sm:block">
          <h1 class="text-base font-semibold text-white">Empleado</h1>
          <p class="text-[11px] text-gray-400">{{ form.name || 'Cargando...' }}</p>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <AttendanceButton class="!text-[11px]" />
          <UserMenu />
        </div>
      </header>

      <main class="flex-1 p-3 sm:p-4 lg:p-6 overflow-auto">
        <div class="max-w-6xl mx-auto space-y-4">
          <div class="flex items-center gap-2">
            <button @click="router.push('/empleados')" class="px-3 py-1.5 rounded-lg border border-gray-700 text-xs text-gray-300 hover:bg-gray-800">
              Volver
            </button>
          </div>

          <template v-if="loadingEmployee">
            <div class="animate-pulse space-y-4">
              <!-- Back button skeleton -->
              <div class="h-8 w-20 rounded-lg bg-gray-800"></div>

              <!-- Tab bar skeleton -->
              <div class="inline-flex rounded-xl border border-gray-800 p-1 bg-gray-900 gap-1">
                <div v-for="i in 4" :key="'tab-' + i" class="h-9 w-28 rounded-lg bg-gray-800"></div>
              </div>

              <!-- Main content card skeleton -->
              <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3 sm:p-5">
                <div class="h-5 w-48 rounded bg-gray-800 mb-6"></div>

                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
                  <!-- Avatar skeleton -->
                  <div class="w-24 h-24 rounded-full bg-gray-800 shrink-0"></div>

                  <!-- Fields grid skeleton -->
                  <div class="w-full flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="i in 10" :key="'field-' + i" class="space-y-1.5">
                      <div class="h-3 w-16 rounded bg-gray-800"></div>
                      <div class="h-9 w-full rounded-lg bg-gray-800"></div>
                    </div>
                  </div>
                </div>

                <!-- Password section skeleton -->
                <div class="pt-4 mt-4 border-t border-gray-800 space-y-3">
                  <div class="h-4 w-40 rounded bg-gray-800"></div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="i in 2" :key="'pass-' + i" class="h-9 rounded-lg bg-gray-800"></div>
                  </div>
                </div>

                <!-- Save button skeleton -->
                <div class="flex justify-end pt-4">
                  <div class="h-10 w-36 rounded-lg bg-gray-800"></div>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="error">
            <div class="rounded-2xl border border-red-800 bg-red-900/20 p-8 text-center text-red-300">{{ error }}</div>
          </template>

          <template v-else>
            <div class="max-w-full overflow-x-auto">
              <div class="inline-flex rounded-xl border border-gray-700 p-1 bg-gray-900 w-max">
                <button
                  v-for="tab in tabs"
                  :key="tab.id"
                  class="px-3 sm:px-4 py-2 rounded-lg text-sm transition whitespace-nowrap"
                  :class="activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800'"
                  @click="activeTab = tab.id"
                >
                  {{ tab.label }}
                </button>
              </div>
            </div>

            <section v-if="activeTab === 'fichajes'" class="rounded-2xl border border-gray-800 bg-gray-900 p-3 sm:p-5 space-y-4">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-white font-semibold">Fichajes mensuales</h2>
                <div class="flex items-center gap-2 flex-wrap">
                  <button @click="changeMonth(-1)" class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">&#8249;</button>
                  <div class="h-9 min-w-[140px] sm:min-w-[180px] px-3 rounded-lg border border-gray-700 bg-gray-800 text-sm text-white flex items-center justify-center">{{ monthLabel }}</div>
                  <button @click="changeMonth(1)" class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">&#8250;</button>
                </div>
              </div>

              <div class="overflow-x-auto rounded-xl border border-gray-800">
                <!-- phones: date / in / out / hours; pause columns from md, status from sm -->
                <table class="w-full text-xs sm:text-sm">
                  <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                      <th class="text-left px-2 sm:px-3 py-2">Fecha</th>
                      <th class="text-left px-2 sm:px-3 py-2">Entrada</th>
                      <th class="hidden md:table-cell text-left px-3 py-2">Pausa</th>
                      <th class="hidden md:table-cell text-left px-3 py-2">Reanuda</th>
                      <th class="text-left px-2 sm:px-3 py-2">Salida</th>
                      <th class="text-left px-2 sm:px-3 py-2">Horas</th>
                      <th class="hidden sm:table-cell text-left px-3 py-2">Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in attendanceRows" :key="r.date" class="border-t border-gray-800 text-gray-200">
                      <td class="px-2 sm:px-3 py-2">{{ formatDate(r.date) }}<span v-if="r.holiday" class="block text-[10px]" :class="r.holiday_working ? 'text-amber-300' : 'text-blue-300'">{{ r.holiday_working ? 'Festivo laborable' : 'Festivo' }}: {{ r.holiday }}</span></td>
                      <td class="px-2 sm:px-3 py-2 tabular-nums">{{ r.start_time || '--:--' }}</td>
                      <td class="hidden md:table-cell px-3 py-2 tabular-nums">{{ r.pause_time || '--:--' }}</td>
                      <td class="hidden md:table-cell px-3 py-2 tabular-nums">{{ r.resume_time || '--:--' }}</td>
                      <td class="px-2 sm:px-3 py-2 tabular-nums">{{ r.end_time || '--:--' }}</td>
                      <td class="px-2 sm:px-3 py-2 font-semibold tabular-nums">{{ r.hours_worked || '--:--' }}</td>
                      <td class="hidden sm:table-cell px-3 py-2">
                        <span class="text-xs px-2 py-1 rounded-full border" :class="r.status === 'en_trabajo' ? 'border-emerald-500/50 text-emerald-300 bg-emerald-500/10' : 'border-gray-700 text-gray-300 bg-gray-800'">
                          {{ r.status || '-' }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="!attendanceRows.length">
                      <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay fichajes en este mes.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section v-else-if="activeTab === 'estadisticas'" class="space-y-4">
              <!-- header: month -->
              <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-white font-semibold">Estadísticas de <span class="capitalize">{{ monthLabel }}</span></h2>
                <div class="flex items-center gap-2">
                  <button class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" aria-label="Mes anterior" @click="changeMonth(-1)">&#8249;</button>
                  <div class="h-9 min-w-[150px] px-3 rounded-lg border border-gray-700 bg-gray-800 text-sm text-white flex items-center justify-center capitalize">{{ monthLabel }}</div>
                  <button class="h-9 w-9 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800" aria-label="Mes siguiente" @click="changeMonth(1)">&#8250;</button>
                </div>
              </div>

              <!-- KPI tiles -->
              <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                  <div class="text-xs text-gray-400">Horas trabajadas</div>
                  <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold text-white tabular-nums">{{ attendanceSummary.worked_hhmm || '00:00' }}</span>
                    <span class="text-xs text-gray-500 tabular-nums">/ {{ attendanceSummary.target_hhmm || '00:00' }}</span>
                  </div>
                  <div class="mt-3 h-1.5 rounded-full bg-gray-800 overflow-hidden" role="img" :aria-label="`${workedVsTargetPercent}% del objetivo`">
                    <div class="h-full rounded-full" :style="{ width: `${workedVsTargetPercent}%`, backgroundColor: VIZ.series }"></div>
                  </div>
                  <div class="mt-1.5 text-[11px] text-gray-400">{{ attendanceSummary.compliance_percent || 0 }}% del objetivo</div>
                </div>

                <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                  <div class="text-xs text-gray-400">Asistencia</div>
                  <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold text-white tabular-nums">{{ workedDaysPercent }}%</span>
                    <span class="text-xs text-gray-500 tabular-nums">{{ attendanceSummary.worked_days || 0 }}/{{ attendanceSummary.working_days || 0 }} días</span>
                  </div>
                  <div class="mt-3 h-1.5 rounded-full bg-gray-800 overflow-hidden">
                    <div class="h-full rounded-full" :style="{ width: `${workedDaysPercent}%`, backgroundColor: VIZ.series }"></div>
                  </div>
                  <div class="mt-1.5 text-[11px]" :class="stats.absences ? 'text-red-300' : 'text-gray-400'">
                    {{ stats.absences }} ausencia(s) sin fichaje
                  </div>
                </div>

                <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                  <div class="text-xs text-gray-400">Puntualidad</div>
                  <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold text-white tabular-nums">{{ stats.punctualityPct === null ? '—' : `${stats.punctualityPct}%` }}</span>
                    <span class="text-xs text-gray-500">a tiempo</span>
                  </div>
                  <div class="mt-3 text-[11px] text-gray-400 space-y-0.5">
                    <div>Entrada media: <span class="text-gray-200 tabular-nums">{{ stats.avgEntry || '—' }}</span><template v-if="stats.scheduleStart"> · horario {{ stats.scheduleStart }}</template></div>
                    <div :class="stats.lateDays ? 'text-amber-300' : ''">{{ stats.lateDays }} llegada(s) tarde (+{{ LATE_TOLERANCE }} min)</div>
                  </div>
                </div>

                <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                  <div class="text-xs text-gray-400">Promedio por día</div>
                  <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold text-white tabular-nums">{{ stats.avgDay || '—' }}</span>
                    <span v-if="stats.dailyTarget" class="text-xs text-gray-500 tabular-nums">/ {{ stats.dailyTarget }}</span>
                  </div>
                  <div class="mt-3 text-[11px] text-gray-400 space-y-0.5">
                    <div>Horas extra: <span class="text-gray-200 tabular-nums">{{ stats.overtime }}</span></div>
                    <div>Horas de menos: <span class="text-gray-200 tabular-nums">{{ stats.shortfall }}</span></div>
                  </div>
                </div>
              </div>

              <!-- hours per day vs daily target -->
              <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                  <h3 class="text-sm font-semibold text-white">Horas por día</h3>
                  <div class="flex items-center gap-4 text-[11px] text-gray-400">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: VIZ.series }"></span> Trabajado</span>
                    <span v-if="stats.dailyTargetMin" class="inline-flex items-center gap-1.5"><span class="w-4 border-t-2 border-dashed border-gray-400"></span> Objetivo {{ stats.dailyTarget }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-1 rounded-sm" :style="{ backgroundColor: VIZ.critical }"></span> Ausencia</span>
                  </div>
                </div>
                <div v-if="!stats.days.length" class="py-10 text-center text-sm text-gray-500">No hay días laborables en este mes.</div>
                <div v-else class="relative">
                  <!-- y scale -->
                  <div class="absolute inset-y-0 left-0 w-8 flex flex-col justify-between text-[10px] text-gray-500 tabular-nums pb-5" aria-hidden="true">
                    <span>{{ Math.round(stats.maxMin / 60) }}h</span><span>{{ Math.round(stats.maxMin / 120) }}h</span><span>0h</span>
                  </div>
                  <div class="ml-8 relative h-48">
                    <!-- grid + daily target, same box as the bars (pb-5 = day labels) -->
                    <div class="absolute inset-0 pb-5 pointer-events-none" aria-hidden="true">
                      <div class="relative h-full">
                        <div class="absolute inset-x-0 top-0 border-t border-gray-800"></div>
                        <div class="absolute inset-x-0 top-1/2 border-t border-gray-800"></div>
                        <div class="absolute inset-x-0 bottom-0 border-t border-gray-700"></div>
                        <div
                          v-if="stats.dailyTargetMin"
                          class="absolute inset-x-0 z-10 border-t-2 border-dashed border-gray-400/70"
                          :style="{ bottom: `${(stats.dailyTargetMin / stats.maxMin) * 100}%` }"
                        ></div>
                      </div>
                    </div>
                    <div class="absolute inset-0 flex items-end gap-[2px] pb-5">
                      <div
                        v-for="d in stats.days"
                        :key="d.date"
                        class="group relative flex-1 h-full flex flex-col justify-end items-center"
                        tabindex="0"
                        :aria-label="`${d.label}: ${d.worked ? d.hhmm : d.future ? 'pendiente' : 'sin fichaje'}`"
                      >
                        <div
                          v-if="d.minutes > 0"
                          class="w-full max-w-[18px] rounded-t-[4px] transition-opacity group-hover:opacity-80"
                          :style="{ height: `${Math.max(2, (d.minutes / stats.maxMin) * 100)}%`, backgroundColor: VIZ.series }"
                        ></div>
                        <div v-else-if="d.absent" class="w-full max-w-[18px] h-1 rounded-sm" :style="{ backgroundColor: VIZ.critical }"></div>
                        <div v-else class="w-full max-w-[18px] h-px bg-gray-700"></div>
                        <span class="absolute -bottom-5 text-[9px] text-gray-500 tabular-nums">{{ d.day }}</span>
                        <!-- tooltip -->
                        <div class="pointer-events-none absolute bottom-full mb-1 hidden group-hover:block group-focus:block z-20 whitespace-nowrap rounded-lg border border-gray-700 bg-gray-950 px-2.5 py-1.5 text-[11px] text-gray-200 shadow-xl">
                          <div class="font-medium capitalize">{{ d.label }}</div>
                          <div class="tabular-nums">{{ d.worked ? `${d.hhmm} trabajadas` : d.future ? 'Pendiente' : d.holiday ? `Festivo: ${d.holiday}` : 'Sin fichaje' }}</div>
                          <div v-if="d.entry" class="text-gray-400 tabular-nums">Entrada {{ d.entry }}<template v-if="d.lateMin > LATE_TOLERANCE"> · {{ d.lateMin }} min tarde</template></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <!-- punctuality: minutes early/late vs scheduled start -->
                <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="text-sm font-semibold text-white">Puntualidad de entrada</h3>
                    <div class="flex items-center gap-4 text-[11px] text-gray-400">
                      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: VIZ.good }"></span> Antes / a tiempo</span>
                      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: VIZ.serious }"></span> Tarde</span>
                    </div>
                  </div>
                  <p v-if="!stats.scheduleStart" class="text-sm text-gray-500 py-6 text-center">El empleado no tiene horario asignado.</p>
                  <p v-else-if="!stats.entries.length" class="text-sm text-gray-500 py-6 text-center">Sin fichajes este mes.</p>
                  <div v-else class="space-y-1">
                    <div v-for="e in stats.entries" :key="e.date" class="grid grid-cols-[52px_1fr_1fr_64px] items-center gap-2 text-[11px]">
                      <span class="text-gray-400 tabular-nums">{{ e.short }}</span>
                      <!-- early side (right-aligned towards 0) -->
                      <div class="flex justify-end">
                        <div v-if="e.diff <= 0" class="h-3 rounded-l-[4px]" :style="{ width: `${Math.max(3, (Math.min(-e.diff, stats.maxDiff) / stats.maxDiff) * 100)}%`, backgroundColor: VIZ.good }"></div>
                      </div>
                      <div class="flex border-l border-gray-600">
                        <div v-if="e.diff > 0" class="h-3 rounded-r-[4px]" :style="{ width: `${Math.max(3, (Math.min(e.diff, stats.maxDiff) / stats.maxDiff) * 100)}%`, backgroundColor: e.diff > LATE_TOLERANCE ? VIZ.serious : VIZ.good }"></div>
                      </div>
                      <span class="text-right tabular-nums" :class="e.diff > LATE_TOLERANCE ? 'text-amber-300' : 'text-gray-300'">
                        {{ e.entry }} <span class="text-gray-500">({{ e.diff > 0 ? '+' : '' }}{{ e.diff }}′)</span>
                      </span>
                    </div>
                    <p class="pt-2 text-[10px] text-gray-500">Minutos respecto a la entrada del horario ({{ stats.scheduleStart }}). Tolerancia: {{ LATE_TOLERANCE }} min.</p>
                  </div>
                </div>

                <div class="space-y-4">
                  <!-- month distribution -->
                  <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                    <h3 class="text-sm font-semibold text-white mb-3">Días laborables del mes</h3>
                    <div class="flex h-4 w-full gap-[2px] overflow-hidden rounded-[4px]" role="img" :aria-label="stats.distribution.map(s => `${s.label}: ${s.value}`).join(', ')">
                      <div
                        v-for="s in stats.distribution.filter(s => s.value > 0)"
                        :key="s.key"
                        class="h-full first:rounded-l-[4px] last:rounded-r-[4px]"
                        :style="{ flexGrow: s.value, backgroundColor: s.color }"
                        :title="`${s.label}: ${s.value}`"
                      ></div>
                    </div>
                    <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                      <li v-for="s in stats.distribution" :key="s.key" class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ backgroundColor: s.color }"></span>
                        <span class="text-gray-400 flex-1">{{ s.label }}</span>
                        <span class="text-gray-100 tabular-nums font-medium">{{ s.value }}</span>
                      </li>
                    </ul>
                  </div>

                  <!-- requests by status -->
                  <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                    <div class="flex items-baseline justify-between mb-3">
                      <h3 class="text-sm font-semibold text-white">Solicitudes del mes</h3>
                      <span class="text-xs text-gray-400 tabular-nums">{{ attendanceSummary.requests_total || 0 }} en total</span>
                    </div>
                    <p v-if="!attendanceSummary.requests_total" class="text-sm text-gray-500">Sin solicitudes este mes.</p>
                    <template v-else>
                      <div class="flex h-4 w-full gap-[2px] overflow-hidden rounded-[4px]">
                        <div
                          v-for="s in requestSegments.filter(s => s.value > 0)"
                          :key="s.key"
                          class="h-full"
                          :style="{ flexGrow: s.value, backgroundColor: s.color }"
                          :title="`${s.label}: ${s.value}`"
                        ></div>
                      </div>
                      <ul class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <li v-for="s in requestSegments" :key="s.key" class="rounded-lg bg-gray-800/60 px-2.5 py-2">
                          <div class="flex items-center gap-1.5 text-gray-400">
                            <span aria-hidden="true">{{ s.icon }}</span>{{ s.label }}
                          </div>
                          <div class="text-lg font-bold text-white tabular-nums">{{ s.value }}</div>
                        </li>
                      </ul>
                    </template>
                  </div>
                </div>
              </div>
            </section>

            <section v-else-if="activeTab === 'perfil'" class="rounded-2xl border border-gray-800 bg-gray-900 p-3 sm:p-5">
              <h2 class="text-white font-semibold mb-4">Perfil del empleado</h2>
              <form class="space-y-5" @submit.prevent="saveProfile">
                <!-- phones: photo on top, fields below -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
                  <div class="flex-shrink-0">
                    <div class="relative">
                      <div class="w-24 h-24 rounded-full bg-gray-700 overflow-hidden">
                        <img v-if="preview" :src="preview" class="w-full h-full object-cover" />
                        <span v-else class="flex items-center justify-center h-full text-gray-400 text-2xl">{{ (form.name || '?').charAt(0).toUpperCase() }}</span>
                      </div>
                      <PresenceBadge :user-id="employeeId" size="md" corner="bottom-right" />
                    </div>
                    <label class="block text-xs text-gray-300 mt-2 text-center" for="photo">Cambiar foto</label>
                    <input id="photo" type="file" class="hidden" @change="onPhotoChange" />
                  </div>

                  <div class="w-full min-w-0 flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Nombre</label>
                      <input v-model="form.name" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Email</label>
                      <input v-model="form.email" type="email" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Teléfono</label>
                      <input v-model="form.phone" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Equipo</label>
                      <select v-model="form.team_id" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                        <option :value="null">Sin equipo</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Rol</label>
                      <select
                        v-model="form.role_id"
                        :disabled="!canChangeRole"
                        class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white disabled:opacity-60"
                      >
                        <option :value="null">Sin rol (sin permisos)</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        <option v-if="currentRoleHidden" :value="form.role_id">{{ currentRoleName }}</option>
                      </select>
                      <p v-if="!canChangeRole" class="text-[11px] text-gray-500 mt-1">No puedes cambiar el rol de este usuario.</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Fecha de ingreso</label>
                      <input v-model="form.hire_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Fecha de cumpleaños</label>
                      <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">DNI / NIE * <span class="text-gray-500 font-normal">(las nóminas se reparten por DNI)</span></label>
                      <input v-model="form.dni" required maxlength="30" autocapitalize="characters" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Nº de la Seguridad Social *</label>
                      <input v-model="form.social_security_number" required maxlength="50" inputmode="numeric" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Tipo de contrato</label>
                      <select v-model="form.contract_type" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                        <option value="">Sin definir</option>
                        <option value="indefinido">Indefinido</option>
                        <option value="temporal">Temporal</option>
                        <option value="practicas">Prácticas</option>
                        <option value="autonomo">Autónomo</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Modalidad de trabajo</label>
                      <select v-model="form.work_mode" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                        <option value="">Sin definir (oficina; teletrabajo a pedido)</option>
                        <option value="office">Solo oficina</option>
                        <option value="remote">Solo teletrabajo</option>
                      </select>
                      <p class="mt-1 text-[11px] text-gray-500">{{ workModeHint(form.work_mode) }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Inicio de contrato</label>
                      <input v-model="form.contract_start_date" type="date" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">Días de vacaciones</label>
                      <input v-model.number="form.vacation_days_total" type="number" min="0" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    </div>
                  </div>
                </div>

                <div class="pt-4 border-t border-gray-800">
                  <h3 class="text-sm text-gray-300 mb-2">Cambiar contraseña</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <PasswordInput v-model="form.password" placeholder="Nueva contraseña" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                    <PasswordInput v-model="form.password_confirmation" placeholder="Confirmar contraseña" class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white" />
                  </div>
                </div>

                <div v-if="canViewWorkDays" class="pt-4 border-t border-gray-800">
                  <h3 class="text-sm text-gray-200 mb-3">Jornadas asignadas</h3>
                  <p class="text-xs text-gray-400 mb-3">Selecciona una o varias jornadas sin solapar días.</p>
                  <div v-if="selectedTemplates.length" class="mb-3 flex flex-wrap gap-2">
                    <span
                      v-for="tpl in selectedTemplates"
                      :key="`selected-chip-${tpl.id}`"
                      class="inline-flex items-center gap-2 text-[11px] px-2 py-1 rounded-full border border-cyan-500/40 bg-cyan-500/10 text-cyan-200"
                    >
                      <span>{{ tpl.start_time }}-{{ tpl.end_time }} · {{ (tpl.days || []).join(', ') }}</span>
                      <button
                        type="button"
                        class="text-cyan-100 hover:text-white"
                        @click="toggleScheduleTemplate(tpl.id, false)"
                      >
                        Quitar
                      </button>
                    </span>
                  </div>
                  <div v-if="scheduleTemplates.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <button
                      v-for="tpl in scheduleTemplates"
                      :key="`emp-tpl-${tpl.id}`"
                      type="button"
                      @click="toggleScheduleTemplate(tpl.id, !selectedScheduleTemplateIds.includes(tpl.id))"
                      class="text-left rounded-xl border px-3 py-3 transition"
                      :class="selectedScheduleTemplateIds.includes(tpl.id)
                        ? 'border-cyan-400 bg-cyan-500/10 ring-1 ring-cyan-500/30'
                        : 'border-gray-700 bg-gray-800/60 hover:border-gray-500 hover:bg-gray-800'"
                    >
                      <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold" :class="selectedScheduleTemplateIds.includes(tpl.id) ? 'text-cyan-200' : 'text-gray-100'">
                          {{ tpl.start_time }} - {{ tpl.end_time }}
                        </span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full" :class="selectedScheduleTemplateIds.includes(tpl.id) ? 'bg-cyan-400/20 text-cyan-200' : 'bg-gray-700 text-gray-300'">
                          {{ selectedScheduleTemplateIds.includes(tpl.id) ? 'Seleccionada' : 'Disponible' }}
                        </span>
                      </div>
                      <div class="mt-2 flex flex-wrap gap-1.5">
                        <span v-for="day in tpl.days" :key="`emp-tpl-day-${tpl.id}-${day}`" class="text-[11px] px-2 py-0.5 rounded-full bg-gray-900/70 border border-gray-700 text-gray-300">
                          {{ day }}
                        </span>
                      </div>
                    </button>
                  </div>
                  <p v-else class="text-xs text-gray-500">No hay jornadas creadas.</p>
                  <p v-if="scheduleSelectionError" class="mt-3 text-xs text-red-400">{{ scheduleSelectionError }}</p>
                </div>              <div v-if="canEditEmployee" class="flex justify-end">
                <button :disabled="savingProfile" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-50">
                  {{ savingProfile ? 'Guardando...' : 'Guardar cambios' }}
                </button>
              </div>
              <div v-else class="flex justify-end">
                <span class="text-xs text-gray-400">No tienes permisos para editar este empleado.</span>
              </div>
            </form>
          </section>

            <section v-else class="rounded-2xl border border-gray-800 bg-gray-900 p-3 sm:p-5">
              <h2 class="text-white font-semibold mb-4">Documentos del empleado</h2>
              <div class="overflow-x-auto rounded-xl border border-gray-800">
                <table class="w-full text-sm">
                  <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                      <th class="text-left px-3 py-2">Documento</th>
                      <th class="hidden sm:table-cell text-left px-3 py-2">Categoría</th>
                      <th class="hidden sm:table-cell text-left px-3 py-2">Fecha</th>
                      <th class="hidden md:table-cell text-left px-3 py-2">Tamaño</th>
                      <th class="text-right px-3 py-2"><span class="sr-only sm:not-sr-only">Acción</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in employeeDocuments" :key="d.id" class="border-t border-gray-800 text-gray-200">
                      <td class="px-3 py-2 max-w-0 w-full sm:max-w-none sm:w-auto">
                        <div class="truncate">{{ d.original_name || d.filename }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ d.description || '-' }}</div>
                        <div class="sm:hidden text-[11px] text-gray-500 truncate capitalize">{{ d.category || '-' }} · {{ formatDate(d.created_at) }} · {{ formatSize(d.size) }}</div>
                      </td>
                      <td class="hidden sm:table-cell px-3 py-2 capitalize">{{ d.category || '-' }}</td>
                      <td class="hidden sm:table-cell px-3 py-2">{{ formatDate(d.created_at) }}</td>
                      <td class="hidden md:table-cell px-3 py-2">{{ formatSize(d.size) }}</td>
                      <td class="px-3 py-2 text-right">
                        <button class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-gray-700 text-xs text-gray-200 hover:bg-gray-800 disabled:opacity-50" :disabled="downloadingDocId === d.id" :aria-label="`Descargar ${d.original_name || d.filename}`" @click="downloadEmployeeDocument(d)">
                          <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                          </svg>
                          <span class="hidden sm:inline">{{ downloadingDocId === d.id ? 'Bajando...' : 'Descargar' }}</span>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!employeeDocuments.length">
                      <td colspan="5" class="px-3 py-6 text-center text-gray-500">Sin documentos para este empleado.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>
        </div>
      </main>
    </div>

  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: true })

import { computed, ref, watch, onBeforeMount, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import AppSidebar from '../../components/AppSidebar.vue'
import AttendanceButton from '../../components/AttendanceButton.vue'
import UserMenu from '../../components/UserMenu.vue'

const { $swal } = useNuxtApp()

declare const process: any
declare const $fetch: any

type TabId = 'fichajes' | 'estadisticas' | 'perfil' | 'documentos'

const tabs: Array<{ id: TabId; label: string }> = [
  { id: 'fichajes', label: 'Fichajes' },
  { id: 'estadisticas', label: 'Estadisticas' },
  { id: 'perfil', label: 'Perfil' },
  { id: 'documentos', label: 'Documentos' },
]

const { authenticated, fetchUser, logout, apiBase, user } = useAuth()
const router = useRouter()
const route = useRoute()
const sidebar = ref<{ open: boolean } | null>(null)

const activeTab = ref<TabId>('fichajes')
const loadingEmployee = ref(false)
const savingProfile = ref(false)
const error = ref<string | null>(null)
const selectedMonth = ref(localIsoDate().slice(0, 7))
const attendanceRows = ref<any[]>([])
const attendanceSummary = ref<any>({
  worked_hhmm: '00:00',
  target_hhmm: '00:00',
  compliance_percent: 0,
  worked_days: 0,
  working_days: 0,
  missing_days: 0,
})
const employeeDocuments = ref<any[]>([])
const teams = ref<any[]>([])
const roles = ref<Array<{ id: number; name: string; is_admin: boolean }>>([])
const canChangeRole = ref(false)
const currentRoleName = ref('')
// the employee's current role may not be in the assignable list (e.g. Admin for a non-admin viewer)
const currentRoleHidden = computed(() => form.value.role_id !== null && !roles.value.some((r) => r.id === form.value.role_id))
const scheduleTemplates = ref<any[]>([])
const preview = ref<string | null>(null)
const downloadingDocId = ref<number | null>(null)
const selectedScheduleTemplateIds = ref<number[]>([])
const scheduleSelectionError = ref('')

const form = ref<any>({
  name: '',
  email: '',
  team_id: null,
  role_id: null,
  phone: '',
  hire_date: '',
  birth_date: '',
  dni: '',
  social_security_number: '',
  contract_type: '',
  work_mode: '',
  contract_start_date: '',
  vacation_days_total: 0,
  password: '',
  password_confirmation: '',
  start_time: '',
  end_time: '',
  days: ['L', 'M', 'X', 'J', 'V'],
  photo: null,
})

const employeeId = computed(() => Number(route.params.id))
const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1, 1)
  return d.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})
const workedVsTargetPercent = computed(() => {
  const worked = Number(attendanceSummary.value?.worked_minutes || 0)
  const target = Number(attendanceSummary.value?.target_minutes || 0)
  if (target <= 0) return 0
  return Math.max(0, Math.min(100, Math.round((worked / target) * 100)))
})
const workedDaysPercent = computed(() => {
  const workedDays = Number(attendanceSummary.value?.worked_days || 0)
  const totalDays = Number(attendanceSummary.value?.working_days || 0)
  if (totalDays <= 0) return 0
  return Math.max(0, Math.min(100, Math.round((workedDays / totalDays) * 100)))
})
const canViewEmployeeDetails = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_view_employee_details) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.view_details')
})
const canEditEmployee = computed(() => {
  const currentUser: any = user.value || {}
  if (currentUser?.can_edit_employee) return true
  const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : []
  return permissions.includes('employees.edit')
})
const canViewWorkDays = computed(() => {
  return canEditEmployee.value || canViewEmployeeDetails.value
})
const selectedTemplates = computed(() => {
  const ids = new Set<number>(selectedScheduleTemplateIds.value)
  return scheduleTemplates.value.filter((tpl: any) => ids.has(Number(tpl.id)))
})

// ── Estadísticas (RRHH) ──
// Colors: categorical slot 1 for "worked", the status palette for good /
// late / absent (validated on the dark surface, see the dataviz guide).
const VIZ = {
  series: '#3987e5',
  good: '#0ca30c',
  warning: '#fab219',
  serious: '#ec835a',
  critical: '#d03b3b',
  neutral: '#6b7280',
  pending: '#374151',
}
/** minutes after the scheduled start that still count as on time */
const LATE_TOLERANCE = 5

const scheduleInfo = ref<{ start_time?: string | null; daily_target_minutes?: number | null }>({})

const hhmmToMin = (v?: string | null) => {
  const m = String(v || '').match(/^(\d{1,2}):(\d{2})/)
  return m ? Number(m[1]) * 60 + Number(m[2]) : null
}
const minToHHMM = (min: number) => `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(Math.round(min % 60)).padStart(2, '0')}`

const stats = computed(() => {
  const rows = Array.isArray(attendanceRows.value) ? attendanceRows.value : []
  const todayIso = localIsoDate()
  const startMin = hhmmToMin(scheduleInfo.value?.start_time)
  const dailyTargetMin = Number(scheduleInfo.value?.daily_target_minutes || 0)

  const days = rows.map((r: any) => {
    const minutes = parseHHMM(r?.hours_worked) || 0
    const entryMin = hhmmToMin(r?.start_time)
    const worked = minutes > 0 || entryMin !== null
    const future = String(r.date) > todayIso
    const working = r.is_working_day !== false
    const date = parseDate(r.date)
    return {
      date: String(r.date),
      day: date ? date.getDate() : '',
      label: date ? date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'short' }) : r.date,
      short: date ? date.toLocaleDateString('es-ES', { weekday: 'short', day: '2-digit' }) : r.date,
      minutes,
      hhmm: minToHHMM(minutes),
      worked,
      future,
      working,
      holiday: r.holiday || null,
      absent: working && !worked && !future,
      entry: entryMin !== null ? minToHHMM(entryMin) : null,
      entryMin,
      lateMin: entryMin !== null && startMin !== null ? entryMin - startMin : 0,
    }
  })

  const workedDays = days.filter((d) => d.minutes > 0)
  const entries = days
    .filter((d) => d.entryMin !== null && startMin !== null)
    .map((d) => ({ date: d.date, short: d.short, entry: d.entry, diff: d.lateMin }))
  const onTime = entries.filter((e) => e.diff <= LATE_TOLERANCE).length
  const overtime = workedDays.reduce((acc, d) => acc + (dailyTargetMin ? Math.max(0, d.minutes - dailyTargetMin) : 0), 0)
  const shortfall = workedDays.reduce((acc, d) => acc + (dailyTargetMin ? Math.max(0, dailyTargetMin - d.minutes) : 0), 0)
  const maxWorked = days.reduce((acc, d) => Math.max(acc, d.minutes), 0)
  const avgEntryMin = entries.length ? Math.round(entries.reduce((a, e) => a + (hhmmToMin(e.entry) || 0), 0) / entries.length) : null

  const count = (fn: (d: any) => boolean) => days.filter(fn).length
  return {
    days,
    entries,
    // y axis: whole hours, a bit above the highest bar / target
    maxMin: Math.max(60, Math.ceil(Math.max(maxWorked, dailyTargetMin) * 1.1 / 60) * 60),
    maxDiff: Math.max(15, ...entries.map((e) => Math.abs(e.diff))),
    dailyTargetMin,
    dailyTarget: dailyTargetMin ? minToHHMM(dailyTargetMin) : null,
    scheduleStart: startMin !== null ? minToHHMM(startMin) : null,
    punctualityPct: entries.length ? Math.round((onTime / entries.length) * 100) : null,
    lateDays: entries.length - onTime,
    avgEntry: avgEntryMin !== null ? minToHHMM(avgEntryMin) : null,
    avgDay: workedDays.length ? minToHHMM(workedDays.reduce((a, d) => a + d.minutes, 0) / workedDays.length) : null,
    overtime: minToHHMM(overtime),
    shortfall: minToHHMM(shortfall),
    absences: count((d) => d.absent),
    distribution: [
      { key: 'worked', label: 'Trabajados', value: count((d) => d.working && d.worked), color: VIZ.series },
      { key: 'absent', label: 'Ausencias', value: count((d) => d.absent), color: VIZ.critical },
      { key: 'holiday', label: 'Festivos', value: count((d) => !d.working && !!d.holiday), color: VIZ.neutral },
      { key: 'future', label: 'Por venir', value: count((d) => d.working && d.future && !d.worked), color: VIZ.pending },
    ],
  }
})

const requestSegments = computed(() => [
  { key: 'pending', label: 'Pendientes', icon: '⏳', value: Number(attendanceSummary.value?.requests_pending || 0), color: VIZ.warning },
  { key: 'approved', label: 'Aprobadas', icon: '✓', value: Number(attendanceSummary.value?.requests_approved || 0), color: VIZ.good },
  { key: 'rejected', label: 'Rechazadas', icon: '✕', value: Number(attendanceSummary.value?.requests_rejected || 0), color: VIZ.critical },
])

const dailyBars = computed(() => {
  const rows = Array.isArray(attendanceRows.value) ? attendanceRows.value : []
  const withHours = rows
    .map((r: any) => {
      const h = parseHHMM(r?.hours_worked)
      return {
        date: String(r?.date || ''),
        minutes: h,
        hhmm: h > 0 ? minutesToHHMM(h) : '--:--',
        label: r?.date ? formatDate(r.date).slice(0, 5) : '--/--',
      }
    })
    .filter((x: any) => x.minutes > 0)

  const max = withHours.reduce((acc: number, row: any) => Math.max(acc, row.minutes), 0) || 1
  return withHours.slice(0, 12).map((row: any) => ({
    ...row,
    percent: Math.max(6, Math.round((row.minutes / max) * 100)),
  }))
})

function openSidebar() {
  if (sidebar.value) sidebar.value.open = true
}

function onPhotoChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const file = input.files[0]
    form.value.photo = file
    const reader = new FileReader()
    reader.onload = (ev) => {
      preview.value = (ev.target?.result as string) || null
    }
    reader.readAsDataURL(file)
  }
}

function changeMonth(delta: number) {
  const [y, m] = selectedMonth.value.split('-').map(Number)
  const d = new Date(y, (m || 1) - 1 + delta, 1)
  const yy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  selectedMonth.value = `${yy}-${mm}`
}

function formatDate(dt?: string) {
  if (!dt) return '-'
  const d = parseDate(dt)
  return d ? d.toLocaleDateString('es-AR') : dt
}

function formatSize(size?: number) {
  const s = Number(size || 0)
  if (s < 1024) return `${s} B`
  if (s < 1024 * 1024) return `${(s / 1024).toFixed(1)} KB`
  return `${(s / (1024 * 1024)).toFixed(1)} MB`
}

function parseHHMM(v?: string | null): number {
  if (!v) return 0
  const parts = String(v).split(':').map((x) => Number(x))
  if (parts.length < 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) return 0
  return (parts[0] * 60) + parts[1]
}

function minutesToHHMM(minutes: number): string {
  const m = Math.max(0, Number(minutes || 0))
  const h = Math.floor(m / 60)
  const mm = m % 60
  return `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
}

function resolvePhotoUrl(path?: string | null) {
  if (!path) return null
  const raw = String(path)
  if (/^https?:\/\//i.test(raw)) return raw
  const base = String(apiBase).replace(/\/+$/, '')
  return raw.startsWith('/') ? `${base}${raw}` : `${base}/${raw}`
}

function normalizeTimeToHm(value?: string | null) {
  if (!value) return ''
  const raw = String(value).trim()
  if (/^\d{2}:\d{2}:\d{2}$/.test(raw)) return raw.slice(0, 5)
  if (/^\d{2}:\d{2}$/.test(raw)) return raw
  return raw
}

function hasOverlappingDays(ids: number[]) {
  const used = new Set<string>()
  const selected = scheduleTemplates.value.filter((t: any) => ids.includes(Number(t.id)))
  for (const tpl of selected) {
    for (const day of Array.isArray(tpl?.days) ? tpl.days : []) {
      if (used.has(day)) return true
      used.add(day)
    }
  }
  return false
}

function toggleScheduleTemplate(id: number, checked: boolean) {
  const set = new Set<number>(selectedScheduleTemplateIds.value)
  if (checked) set.add(id)
  else set.delete(id)

  const next = Array.from(set.values())
  if (hasOverlappingDays(next)) {
    scheduleSelectionError.value = 'No puedes seleccionar jornadas con dias repetidos.'
    return
  }

  scheduleSelectionError.value = ''
  selectedScheduleTemplateIds.value = next
}

async function loadEmployee() {
  error.value = null
  loadingEmployee.value = true
  try {
    const res = await $fetch(`/api/employees/${employeeId.value}`, {
    })

    const employee = res?.employee || {}
    form.value.name = employee.name || ''
    form.value.email = employee.email || ''
    form.value.team_id = employee.team_id ?? null
    form.value.role_id = employee.role_id ?? null
    currentRoleName.value = employee.role?.name || 'Rol actual'
    form.value.phone = employee.phone || ''
    form.value.hire_date = employee.hire_date || ''
    form.value.birth_date = employee.birth_date || ''
    form.value.dni = employee.dni || ''
    form.value.social_security_number = employee.social_security_number || ''
    form.value.contract_type = employee.contract_type || ''
    form.value.work_mode = employee.work_mode || ''
    form.value.contract_start_date = employee.contract_start_date || ''
    form.value.vacation_days_total = Number(employee.vacation_days_total || 0)
    preview.value = resolvePhotoUrl(employee.photo || employee.profile_photo_path)

    const schedule = res?.schedule || null
    form.value.start_time = normalizeTimeToHm(schedule?.start_time || '')
    form.value.end_time = normalizeTimeToHm(schedule?.end_time || '')
    form.value.days = Array.isArray(schedule?.days) && schedule.days.length ? schedule.days : ['L', 'M', 'X', 'J', 'V']
    scheduleTemplates.value = res?.schedule_templates || []
    selectedScheduleTemplateIds.value = Array.isArray(res?.schedule_template_ids)
      ? res.schedule_template_ids.map((id: any) => Number(id)).filter((id: number) => Number.isFinite(id))
      : []
    scheduleSelectionError.value = ''

    teams.value = res?.teams || []
    roles.value = res?.roles || []
    canChangeRole.value = Boolean(res?.can_change_role)
  } catch (e: any) {
    console.error('employee load failed', e)
    error.value = e?.data?.message || 'No se pudo cargar el empleado'
  } finally {
    loadingEmployee.value = false
  }
}

async function loadAttendance() {
  if (!authenticated.value || !employeeId.value) return
  try {
    const res = await $fetch(`/api/employees/${employeeId.value}/attendance-month?month=${selectedMonth.value}`, {
    })
    attendanceRows.value = res?.rows || []
    attendanceSummary.value = res?.summary || attendanceSummary.value
    scheduleInfo.value = res?.schedule || {}
  } catch (e) {
    console.error('employee attendance load failed', e)
    attendanceRows.value = []
  }
}

async function loadDocuments() {
  if (!authenticated.value || !employeeId.value) return
  try {
    const res = await $fetch(`/api/employees/${employeeId.value}/documents`, {
    })
    employeeDocuments.value = res?.documents || []
  } catch (e) {
    console.error('employee documents load failed', e)
    employeeDocuments.value = []
  }
}

async function saveProfile() {
  if (!authenticated.value || !employeeId.value) return
  if (scheduleSelectionError.value) {
    $swal.toast('error', scheduleSelectionError.value)
    return
  }
  savingProfile.value = true
  try {
    const data = new FormData()
    data.append('_method', 'PUT')
    data.append('name', form.value.name || '')
    data.append('email', form.value.email || '')
    if (form.value.team_id !== null && form.value.team_id !== '') data.append('team_id', String(form.value.team_id))
    if (canChangeRole.value) data.append('role_id', form.value.role_id === null ? '' : String(form.value.role_id))
    data.append('phone', form.value.phone || '')
    data.append('hire_date', form.value.hire_date || '')
    data.append('birth_date', form.value.birth_date || '')
    data.append('dni', form.value.dni || '')
    data.append('social_security_number', form.value.social_security_number || '')
    data.append('contract_type', form.value.contract_type || '')
    data.append('work_mode', form.value.work_mode || '')
    data.append('contract_start_date', form.value.contract_start_date || '')
    data.append('vacation_days_total', String(Number(form.value.vacation_days_total || 0)))
    if (form.value.password) {
      data.append('password', form.value.password)
      data.append('password_confirmation', form.value.password_confirmation || '')
    }
    if (canViewWorkDays.value) {
      for (const id of selectedScheduleTemplateIds.value) data.append('schedule_template_ids[]', String(id))
    }
    if (form.value.photo) data.append('photo', form.value.photo)

    await $fetch(`/api/employees/${employeeId.value}`, {
      method: 'POST',
      body: data,
    })

    form.value.password = ''
    form.value.password_confirmation = ''
    $swal.toast('success', 'Empleado actualizado correctamente')
    await loadEmployee()
  } catch (e: any) {
    console.error('employee save failed', e)
    $swal.toast('error', e?.data?.message || 'No se pudo guardar el empleado')
  } finally {
    savingProfile.value = false
  }
}

async function downloadEmployeeDocument(d: any) {
  if (!authenticated.value || !employeeId.value || !d?.id) return
  downloadingDocId.value = Number(d.id)
  try {
    const res = await fetch(`/api/employees/${employeeId.value}/documents/${d.id}/download`, {
    })
    if (!res.ok) throw new Error('download_failed')

    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = d.original_name || `documento_${d.id}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    $swal.toast('success', 'Descarga iniciada')
  } catch (e) {
    console.error('employee doc download failed', e)
    $swal.toast('error', 'No se pudo descargar el documento')
  } finally {
    downloadingDocId.value = null
  }
}

const onLogout = async () => {
  await logout()
  router.push('/login')
}

watch(selectedMonth, () => {
  if (activeTab.value === 'fichajes' || activeTab.value === 'estadisticas') {
    loadAttendance()
  }
})

watch(activeTab, (tab) => {
  if (tab === 'documentos') {
    loadDocuments()
  }
  if (tab === 'fichajes' || tab === 'estadisticas') {
    loadAttendance()
  }
})

onBeforeMount(async () => {
  await fetchUser()
  if (!canViewEmployeeDetails.value) {
    return router.push('/dashboard')
  }

  await loadEmployee()
  if (!canEditEmployee.value) {
    activeTab.value = 'fichajes'
  }
  await loadAttendance()
  await loadDocuments()
})



// Modalidad de trabajo: '' = office + remote on request, office / remote = fixed
function workModeHint(mode: string) {
  if (mode === 'remote') return 'Siempre de teletrabajo, sin necesidad de solicitarlo.'
  if (mode === 'office') return 'Siempre en la oficina; no puede solicitar teletrabajo.'
  return 'En la oficina; teletrabajo solo los días que solicite y se aprueben.'
}
</script>

