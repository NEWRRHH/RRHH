// Announcement priorities (comunicados) and their colours, shared by the
// comunicados page and the dashboard card. Auto-imported by Nuxt.

export type AnnouncementPriority = 'normal' | 'importante' | 'urgente'

export const ANNOUNCEMENT_PRIORITIES: Array<{
  key: AnnouncementPriority
  label: string
  /** badge */
  badge: string
  /** left accent of the card */
  accent: string
  /** dot in the select / legend */
  dot: string
}> = [
  { key: 'normal', label: 'Normal', badge: 'border-gray-600 bg-gray-700/40 text-gray-200', accent: 'border-l-gray-600', dot: 'bg-gray-400' },
  { key: 'importante', label: 'Importante', badge: 'border-amber-500/50 bg-amber-500/15 text-amber-200', accent: 'border-l-amber-400', dot: 'bg-amber-400' },
  { key: 'urgente', label: 'Urgente', badge: 'border-red-500/50 bg-red-500/15 text-red-200', accent: 'border-l-red-500', dot: 'bg-red-500' },
]

export function announcementPriority(key?: string | null) {
  return ANNOUNCEMENT_PRIORITIES.find((p) => p.key === key) ?? ANNOUNCEMENT_PRIORITIES[0]
}
