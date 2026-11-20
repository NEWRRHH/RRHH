// Today's presence of the company (shared by every avatar of the app):
//   remote   -> approved "Teletrabajo" today      (house icon)
//   office   -> clocked in, not on remote work    (building icon)
//   vacation -> approved "Vacaciones" today       (sun icon)
// Loaded once from /api/presence/today and refreshed in real time when
// someone clocks in/out or RRHH decides a remote-work / vacation request
// (CompanyAttendanceChanged on company.{id}.attendance), plus every 5 min.

export type PresenceKind = 'remote' | 'office' | 'vacation'

type PresenceState = { remote: number[]; office: number[]; vacation: number[]; loadedAt: number }

let refreshTimer: ReturnType<typeof setTimeout> | null = null
let pollTimer: ReturnType<typeof setInterval> | null = null
let subscribedCompany = 0

export const usePresence = () => {
  const { user, authenticated, realtimeInstance, lastAttendanceChange } = useAuth()
  const state = useState<PresenceState>('presence_today', () => ({ remote: [], office: [], vacation: [], loadedAt: 0 }))

  const load = async () => {
    if (!authenticated.value) return
    try {
      const res: any = await $fetch('/api/presence/today')
      state.value = {
        remote: (res?.remote || []).map(Number),
        office: (res?.office || []).map(Number),
        vacation: (res?.vacation || []).map(Number),
        loadedAt: Date.now(),
      }
    } catch {
      // keep the last known state
    }
  }

  // several people clocking in at 9:00 -> one request
  const scheduleRefresh = () => {
    if (refreshTimer) clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => { void load() }, 500)
  }

  const subscribe = () => {
    const companyId = Number((user.value as any)?.company_id || 0)
    const echo: any = realtimeInstance?.()
    if (!echo || !companyId || subscribedCompany === companyId) return
    subscribedCompany = companyId
    echo.private(`company.${companyId}.attendance`).listen('.CompanyAttendanceChanged', scheduleRefresh)
  }

  /** Call once (app.vue): first load, realtime and a slow poll (new day...). */
  const start = () => {
    if (import.meta.server) return
    if (!state.value.loadedAt || Date.now() - state.value.loadedAt > 60_000) void load()
    subscribe()
    if (!pollTimer) {
      pollTimer = setInterval(() => { void load() }, 5 * 60_000)
      // my own clock-in / out (other tabs too)
      watch(lastAttendanceChange, scheduleRefresh)
    }
  }

  const kindOf = (userId?: number | string | null): PresenceKind | null => {
    const id = Number(userId || 0)
    if (!id) return null
    if (state.value.remote.includes(id)) return 'remote'
    if (state.value.vacation.includes(id)) return 'vacation'
    if (state.value.office.includes(id)) return 'office'
    return null
  }

  return { presence: state, kindOf, load, start }
}
