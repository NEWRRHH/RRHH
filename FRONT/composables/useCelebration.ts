// Birthday / work anniversary celebration of the logged-in user.
// The overlay (components/CelebrationOverlay.vue) plays automatically once a
// day; the dashboard card can replay it with play(), and Configuración >
// Festejos simulates it with preview() (does not count as "seen").

export type CelebrationEffect = 'confetti' | 'balloons' | 'stars' | 'fireworks' | 'emojis'

export type Celebration = {
  type: 'birthday' | 'anniversary'
  years: number
  title?: string
  message?: string
  effects?: CelebrationEffect[]
}

export const CELEBRATION_EFFECTS: Array<{ key: CelebrationEffect; name: string; emoji: string }> = [
  { key: 'confetti', name: 'Confeti', emoji: '🎊' },
  { key: 'balloons', name: 'Globos', emoji: '🎈' },
  { key: 'stars', name: 'Estrellas', emoji: '⭐' },
  { key: 'fireworks', name: 'Fuegos artificiales', emoji: '🎆' },
  { key: 'emojis', name: 'Lluvia de emojis', emoji: '🥳' },
]

export const DEFAULT_CELEBRATION_EFFECTS: Record<Celebration['type'], CelebrationEffect[]> = {
  birthday: ['confetti', 'balloons'],
  anniversary: ['confetti', 'stars'],
}

export const useCelebration = () => {
  const { user } = useAuth()
  const playing = useState<boolean>('celebration_playing', () => false)
  // set while simulating from the settings screen
  const previewItems = useState<Celebration[] | null>('celebration_preview', () => null)

  const celebrations = computed<Celebration[]>(() => {
    const u = user.value as any
    // a super admin browsing a company is not the person being celebrated
    if (!u || u.impersonated_by) return []
    return Array.isArray(u.celebrations_today) ? u.celebrations_today : []
  })

  // what the overlay shows right now
  const current = computed<Celebration[]>(() => previewItems.value ?? celebrations.value)

  const seenKey = () => `celebration:seen:${(user.value as any)?.id}:${localIsoDate()}`

  const alreadySeen = () => {
    try { return localStorage.getItem(seenKey()) === '1' } catch { return false }
  }
  const markSeen = () => {
    try { localStorage.setItem(seenKey(), '1') } catch { /* private mode */ }
  }

  const play = () => {
    previewItems.value = null
    if (celebrations.value.length) playing.value = true
  }

  const preview = (item: Celebration) => {
    previewItems.value = [item]
    // restart if it was already open
    playing.value = false
    nextTick(() => { playing.value = true })
  }

  const stop = () => {
    playing.value = false
    if (previewItems.value) previewItems.value = null
    else markSeen()
  }

  return { celebrations, current, playing, play, preview, stop, alreadySeen }
}
