// Birthday / work anniversary celebration of the logged-in user.
// The overlay (components/CelebrationOverlay.vue) plays automatically once a
// day; the dashboard card can replay it with play().

export type Celebration = { type: 'birthday' | 'anniversary'; years: number; title?: string; message?: string }

export const useCelebration = () => {
  const { user } = useAuth()
  const playing = useState<boolean>('celebration_playing', () => false)

  const celebrations = computed<Celebration[]>(() => {
    const u = user.value as any
    // a super admin browsing a company is not the person being celebrated
    if (!u || u.impersonated_by) return []
    return Array.isArray(u.celebrations_today) ? u.celebrations_today : []
  })

  const seenKey = () => `celebration:seen:${(user.value as any)?.id}:${localIsoDate()}`

  const alreadySeen = () => {
    try { return localStorage.getItem(seenKey()) === '1' } catch { return false }
  }
  const markSeen = () => {
    try { localStorage.setItem(seenKey(), '1') } catch { /* private mode */ }
  }

  const play = () => { if (celebrations.value.length) playing.value = true }
  const stop = () => { playing.value = false; markSeen() }

  return { celebrations, playing, play, stop, alreadySeen }
}
