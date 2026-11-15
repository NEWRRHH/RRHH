<template>
  <Teleport to="body">
    <Transition name="celebration-fade">
      <div
        v-if="playing"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.self="stop"
      >
        <canvas ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

        <!-- balloons rising (birthday) / stars floating (anniversary) -->
        <div v-if="!reducedMotion" class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
          <span
            v-for="f in floaters"
            :key="f.id"
            class="celebration-floater absolute bottom-0 select-none"
            :style="{ left: f.left + '%', fontSize: f.size + 'px', animationDelay: f.delay + 's', animationDuration: f.duration + 's' }"
          >{{ f.emoji }}</span>
        </div>

        <div class="celebration-card relative w-full max-w-sm rounded-3xl border border-white/10 bg-gray-900/95 p-7 text-center shadow-2xl shadow-black/60">
          <div class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full blur-3xl" :class="isBirthday ? 'bg-rose-500/30' : 'bg-amber-400/25'"></div>

          <div class="celebration-emoji relative text-6xl mb-3" aria-hidden="true">{{ isBirthday ? '🎂' : '🏆' }}</div>
          <h2 class="relative text-2xl font-bold text-white leading-tight">{{ title }}</h2>
          <p v-for="(line, i) in lines" :key="i" class="relative mt-2 text-sm text-gray-300">{{ line }}</p>

          <button
            type="button"
            class="relative mt-6 w-full rounded-xl py-2.5 font-semibold text-white shadow-lg transition"
            :class="isBirthday ? 'bg-rose-500 hover:bg-rose-400 shadow-rose-500/30' : 'bg-amber-500 hover:bg-amber-400 shadow-amber-500/30'"
            @click="stop"
          >
            ¡Gracias! 🎉
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const { user } = useAuth()
const { celebrations, playing, play, stop, alreadySeen } = useCelebration()

const canvas = ref<HTMLCanvasElement | null>(null)
const reducedMotion = ref(false)

const firstName = computed(() => String((user.value as any)?.first_name || (user.value as any)?.name || '').trim().split(' ')[0])
const birthday = computed(() => celebrations.value.find(c => c.type === 'birthday'))
const anniversary = computed(() => celebrations.value.find(c => c.type === 'anniversary'))
const isBirthday = computed(() => !!birthday.value)

const yearsText = (n: number) => `${n} ${n === 1 ? 'año' : 'años'}`

// Texts come from the server (RRHH sets them in Configuración > Festejos);
// the fallbacks only cover an older backend without them.
const title = computed(() => {
  const main = birthday.value || anniversary.value
  if (main?.title) return main.title
  const name = firstName.value ? `, ${firstName.value}` : ''
  return isBirthday.value ? `¡Feliz cumpleaños${name}!` : `¡Feliz aniversario${name}!`
})

const lines = computed(() => {
  const out: string[] = []
  if (birthday.value) out.push(birthday.value.message || 'Todo el equipo te desea un día increíble.')
  // both on the same day: the anniversary title becomes a line of its own
  if (birthday.value && anniversary.value?.title) out.push(anniversary.value.title)
  if (anniversary.value) {
    out.push(anniversary.value.message || `Hoy cumples ${yearsText(anniversary.value.years)} con nosotros. ¡Gracias por ser parte del equipo!`)
  }
  return out
})

const floaters = computed(() => {
  const pool = isBirthday.value ? ['🎈', '🎈', '🎉', '🎁'] : ['⭐', '✨', '🎉', '🥳']
  return Array.from({ length: 14 }, (_, i) => ({
    id: i,
    emoji: pool[i % pool.length],
    left: (i * 7.3 + Math.random() * 6) % 96,
    size: 26 + Math.random() * 20,
    delay: Math.random() * 2.5,
    duration: 5 + Math.random() * 3,
  }))
})

// ---- confetti (plain canvas, no dependencies) ----
type Piece = { x: number; y: number; vx: number; vy: number; w: number; h: number; rot: number; vr: number; color: string }
const COLORS = ['#f43f5e', '#fb923c', '#facc15', '#4ade80', '#38bdf8', '#a78bfa', '#f472b6']
let pieces: Piece[] = []
let raf = 0
let startedAt = 0
let bursts: number[] = []

const burst = (w: number, h: number, originX: number, count: number) => {
  for (let i = 0; i < count; i++) {
    const angle = -Math.PI / 2 + (Math.random() - 0.5) * 1.6
    const speed = 7 + Math.random() * 9
    pieces.push({
      x: originX, y: h,
      vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed * 1.4,
      w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
      rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3,
      color: COLORS[(Math.random() * COLORS.length) | 0],
    })
  }
}

const runConfetti = () => {
  const el = canvas.value
  if (!el) return
  const ctx = el.getContext('2d')
  if (!ctx) return
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  const w = window.innerWidth
  const h = window.innerHeight
  el.width = w * dpr
  el.height = h * dpr
  ctx.scale(dpr, dpr)

  pieces = []
  startedAt = performance.now()
  bursts = [0, 700, 1500] // ms: three waves
  const count = w < 640 ? 70 : 120

  const frame = (now: number) => {
    const t = now - startedAt
    while (bursts.length && t >= bursts[0]) {
      bursts.shift()
      burst(w, h, w * 0.15, count / 2)
      burst(w, h, w * 0.85, count / 2)
    }
    ctx.clearRect(0, 0, w, h)
    for (const p of pieces) {
      p.vy += 0.25 // gravity
      p.vx *= 0.99
      p.x += p.vx
      p.y += p.vy
      p.rot += p.vr
      ctx.save()
      ctx.translate(p.x, p.y)
      ctx.rotate(p.rot)
      ctx.fillStyle = p.color
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.abs(Math.cos(p.rot * 2)))
      ctx.restore()
    }
    pieces = pieces.filter(p => p.y < h + 40)
    if (pieces.length || bursts.length) raf = requestAnimationFrame(frame)
  }
  raf = requestAnimationFrame(frame)
}

const stopConfetti = () => { cancelAnimationFrame(raf); raf = 0; pieces = [] }

watch(playing, async (on) => {
  if (!on) { stopConfetti(); return }
  if (reducedMotion.value) return
  await nextTick()
  runConfetti()
})

// play automatically once a day, as soon as the user (with celebrations) is known
const tryAutoPlay = () => {
  if (celebrations.value.length && !alreadySeen()) setTimeout(play, 600)
}

const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape' && playing.value) stop() }

onMounted(() => {
  reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  window.addEventListener('keydown', onKey)
  tryAutoPlay()
})
watch(() => (user.value as any)?.id, (id, old) => { if (id && id !== old) tryAutoPlay() })

onBeforeUnmount(() => {
  stopConfetti()
  window.removeEventListener('keydown', onKey)
})
</script>

<style scoped>
.celebration-fade-enter-active,
.celebration-fade-leave-active { transition: opacity 0.3s ease; }
.celebration-fade-enter-from,
.celebration-fade-leave-to { opacity: 0; }

.celebration-card { animation: celebration-pop 0.55s cubic-bezier(0.2, 1.4, 0.4, 1) both; }
.celebration-emoji { display: inline-block; animation: celebration-bounce 1.4s ease-in-out infinite; }
.celebration-floater { animation-name: celebration-rise; animation-timing-function: ease-in; animation-iteration-count: infinite; opacity: 0; }

@keyframes celebration-pop {
  from { transform: scale(0.6); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes celebration-bounce {
  0%, 100% { transform: translateY(0) rotate(0); }
  25% { transform: translateY(-10px) rotate(-6deg); }
  50% { transform: translateY(0) rotate(0); }
  75% { transform: translateY(-6px) rotate(6deg); }
}
@keyframes celebration-rise {
  0% { transform: translateY(10vh) translateX(0); opacity: 0; }
  10% { opacity: 1; }
  50% { transform: translateY(-45vh) translateX(18px); }
  90% { opacity: 1; }
  100% { transform: translateY(-110vh) translateX(-12px); opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .celebration-card, .celebration-emoji { animation: none; }
}
</style>
