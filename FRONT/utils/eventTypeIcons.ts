// Default icon + colour of each event type (vacaciones, solicitudes...).
// Types are matched by their stable `code`; custom types without a code get
// a guess from the name, and a generic tag icon otherwise. Auto-imported.

/** color = fixed colour of the type (same as EventType::COLORS in the API) */
export type EventTypeVisual = { icon: string; tone: string; color: string }

const VISUALS: Record<string, EventTypeVisual> = {
  // sun / beach
  vacation: {
    icon: 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z',
    tone: 'bg-teal-500/15 text-teal-300',
    color: '#14B8A6',
  },
  // id badge / signed paper
  permission: {
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    tone: 'bg-amber-500/15 text-amber-300',
    color: '#F59E0B',
  },
  // calendar
  general: {
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    tone: 'bg-blue-500/15 text-blue-300',
    color: '#3B82F6',
  },
  // people
  meeting: {
    icon: 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m8-4.13a4 4 0 11-8 0 4 4 0 018 0z',
    tone: 'bg-violet-500/15 text-violet-300',
    color: '#8B5CF6',
  },
  // clock
  attendance_request: {
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    tone: 'bg-orange-500/15 text-orange-300',
    color: '#F97316',
  },
}

// heart-pulse for medical types created by companies
const MEDICAL: EventTypeVisual = {
  icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
  tone: 'bg-rose-500/15 text-rose-300',
  color: '#64748B',
}

const FALLBACK: EventTypeVisual = {
  icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z',
  tone: 'bg-gray-500/15 text-gray-300',
  color: '#64748B',
}

export function eventTypeVisual(code?: string | null, name?: string | null): EventTypeVisual {
  if (code && VISUALS[code]) return VISUALS[code]
  const n = String(name || '').toLowerCase()
  if (n.includes('vacacion')) return VISUALS.vacation
  if (n.includes('permiso') || n.includes('licencia')) return VISUALS.permission
  if (n.includes('reuni')) return VISUALS.meeting
  if (n.includes('fichaj')) return VISUALS.attendance_request
  if (n.includes('médic') || n.includes('medic') || n.includes('salud') || n.includes('enferm')) return MEDICAL
  if (n.includes('general')) return VISUALS.general
  return FALLBACK
}

/** General and Permiso need a description; the rest is optional. */
export const DESCRIPTION_REQUIRED_CODES = ['general', 'permission']
