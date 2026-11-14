// Date parsing that behaves the same in every browser (auto-imported by Nuxt).
//
// `new Date(str)` is not safe for what the API sends:
// - "2026-12-25" is read as UTC midnight, so west of UTC it shows the 24th;
// - "2026-11-14 10:00:00" (MySQL format, space instead of "T") is not a
//   standard format and Safari may return Invalid Date.
// Both are local wall-clock values, so they are built as local dates here.
// Full ISO strings with a zone ("...Z", "+01:00") keep the native parser.

const LOCAL_DATE_TIME = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?)?$/

/** Parse an API date/datetime; null when empty or invalid. */
export function parseDate(value?: string | number | Date | null): Date | null {
  if (value === null || value === undefined || value === '') return null
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value
  if (typeof value === 'number') return new Date(value)

  const m = LOCAL_DATE_TIME.exec(value.trim())
  const d = m
    ? new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0))
    : new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

/** Today (or `date`) as local "YYYY-MM-DD" (toISOString() would give the UTC day). */
export function localIsoDate(date: Date = new Date()): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}
