// Display metadata of the dashboard cards (auto-imported by Nuxt).
// Same icons/colors as the real cards so the editor looks like the dashboard.

export type DashboardWidgetKey =
  | 'welcome' | 'birthdays' | 'team' | 'vacation' | 'notifications'
  | 'worked_hours' | 'holidays' | 'announcements' | 'documents'

export type DashboardSlots = Array<DashboardWidgetKey | null>

export const DASHBOARD_POSITIONS = 9
/** The dashboard fills positions column by column: 1-3 first column, 4-6 second, 7-9 third. */
export const DASHBOARD_COLUMNS = [[0, 1, 2], [3, 4, 5], [6, 7, 8]]

export const DASHBOARD_WIDGETS: Record<DashboardWidgetKey, { name: string; description: string; icon: string; tone: string }> = {
  welcome: {
    name: 'Bienvenida',
    description: 'Saludo con el mensaje de la empresa',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    tone: 'bg-blue-600 text-white',
  },
  birthdays: {
    name: 'Cumpleaños y aniversarios',
    description: 'Próximos cumpleaños y aniversarios en la empresa',
    icon: 'M12 8v4m0 0l-2 2m2-2l2 2M5 21h14a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2zM12 4v1',
    tone: 'bg-pink-500/15 text-pink-300',
  },
  team: {
    name: 'Tu equipo',
    description: 'Compañeros y quién está trabajando',
    icon: 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m8-4.13a4 4 0 11-8 0 4 4 0 018 0z',
    tone: 'bg-emerald-500/15 text-emerald-300',
  },
  vacation: {
    name: 'Vacaciones',
    description: 'Días disponibles y próximas vacaciones',
    icon: 'M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    tone: 'bg-cyan-500/15 text-cyan-300',
  },
  notifications: {
    name: 'Notificaciones',
    description: 'Mensajes sin leer',
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    tone: 'bg-amber-500/15 text-amber-300',
  },
  worked_hours: {
    name: 'Horas trabajadas',
    description: 'Horas del día y del mes',
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    tone: 'bg-sky-500/15 text-sky-300',
  },
  holidays: {
    name: 'Próximos festivos',
    description: 'Festivos de la empresa',
    icon: 'M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    tone: 'bg-orange-500/15 text-orange-300',
  },
  announcements: {
    name: 'Comunicados',
    description: 'Últimos comunicados',
    icon: 'M7 8h10M7 12h7m-7 4h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H9l-6 0V5a2 2 0 012-2z',
    tone: 'bg-violet-500/15 text-violet-300',
  },
  documents: {
    name: 'Documentos',
    description: 'Resumen de tus documentos',
    icon: 'M7 7V3h10v4m-9 4h8m-8 4h8m-9 6h10a2 2 0 002-2V7H5v12a2 2 0 002 2z',
    tone: 'bg-indigo-500/15 text-indigo-300',
  },
}

export const DASHBOARD_WIDGET_KEYS = Object.keys(DASHBOARD_WIDGETS) as DashboardWidgetKey[]

/** 9 positions, known widgets only, no repeats. */
export function normalizeDashboardSlots(input: unknown): DashboardSlots {
  const out: DashboardSlots = Array(DASHBOARD_POSITIONS).fill(null)
  if (!Array.isArray(input)) return out
  input.slice(0, DASHBOARD_POSITIONS).forEach((key, i) => {
    if (typeof key === 'string' && key in DASHBOARD_WIDGETS && !out.includes(key as DashboardWidgetKey)) {
      out[i] = key as DashboardWidgetKey
    }
  })
  return out
}

// ── Card widths (how many of the 3 desktop columns a card spans) ──

export type DashboardWidths = Partial<Record<DashboardWidgetKey, number>>
export const DASHBOARD_MAX_WIDTH = 3
const ROWS = DASHBOARD_POSITIONS / DASHBOARD_COLUMNS.length // 3

const posAt = (col: number, row: number) => col * ROWS + row

export function normalizeDashboardWidths(input: unknown): DashboardWidths {
  const out: DashboardWidths = {}
  for (const key of DASHBOARD_WIDGET_KEYS) {
    const n = Number((input as any)?.[key])
    out[key] = Number.isFinite(n) ? Math.max(1, Math.min(DASHBOARD_MAX_WIDTH, Math.round(n))) : 1
  }
  return out
}

/**
 * Columns the card at `pos` really gets: what RRHH chose, limited by the
 * right edge and by taken positions to its right in the same row (the card
 * narrows instead of covering another one).
 */
export function effectiveDashboardWidth(slots: DashboardSlots, widths: DashboardWidths, pos: number): number {
  const key = slots[pos]
  if (!key) return 1
  const wanted = Math.max(1, Math.min(DASHBOARD_MAX_WIDTH, widths[key] ?? 1))
  const col = Math.floor(pos / ROWS)
  const row = pos % ROWS
  let w = 1
  while (w < wanted && col + w < DASHBOARD_COLUMNS.length && slots[posAt(col + w, row)] === null) w++
  return w
}

export type DashboardCell = { col: number; span: number; positions: number[] }
export type DashboardSegment = { key: string; cells: DashboardCell[] }

/**
 * How the board is drawn. Rows without wide cards are grouped and drawn as
 * the usual 3 independent columns (cards stack with no gaps); a row with a
 * wide card becomes a horizontal band where that card spans several
 * columns. With no wide cards this is exactly the classic 3-column board.
 */
export function dashboardSegments(slots: DashboardSlots, widths: DashboardWidths): DashboardSegment[] {
  const segments: DashboardSegment[] = []
  let stackRows: number[] = []
  const flushStack = () => {
    if (!stackRows.length) return
    const rows = stackRows
    segments.push({
      key: `cols-${rows.join('')}`,
      cells: DASHBOARD_COLUMNS.map((_, col) => ({ col, span: 1, positions: rows.map((r) => posAt(col, r)) })),
    })
    stackRows = []
  }

  for (let row = 0; row < ROWS; row++) {
    const cells: DashboardCell[] = []
    let wide = false
    for (let col = 0; col < DASHBOARD_COLUMNS.length;) {
      const pos = posAt(col, row)
      const span = effectiveDashboardWidth(slots, widths, pos)
      if (span > 1) wide = true
      cells.push({ col, span, positions: [pos] })
      col += span
    }
    if (wide) {
      flushStack()
      segments.push({ key: `band-${row}`, cells })
    } else {
      stackRows.push(row)
    }
  }
  flushStack()
  return segments
}

// static class names so Tailwind generates them (desktop = xl)
export const DASHBOARD_COL_START_XL = ['xl:col-start-1', 'xl:col-start-2', 'xl:col-start-3']
export const DASHBOARD_COL_SPAN_XL = ['', 'xl:col-span-1', 'xl:col-span-2', 'xl:col-span-3']
export const DASHBOARD_COL_START_SM = ['sm:col-start-1', 'sm:col-start-2', 'sm:col-start-3']
export const DASHBOARD_COL_SPAN_SM = ['', 'sm:col-span-1', 'sm:col-span-2', 'sm:col-span-3']

/** Pure move used by every drag & drop: grid<->grid swaps, tray<->grid places/hides. */
export function moveDashboardWidget(slots: DashboardSlots, key: DashboardWidgetKey, to: number | 'hidden'): DashboardSlots {
  const next = [...slots]
  const from = next.indexOf(key)
  if (to === 'hidden') {
    if (from >= 0) next[from] = null
    return next
  }
  const occupant = next[to]
  if (from >= 0) next[from] = occupant // swap (occupant may be null)
  // dropped from the tray onto an occupied position: the occupant becomes hidden
  next[to] = key
  return next
}
