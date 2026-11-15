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
        <!-- confetti + fireworks -->
        <canvas ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

        <!-- balloons / stars / emoji rain -->
        <div v-if="!reducedMotion" class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
          <span
            v-for="f in floaters"
            :key="f.id"
            class="absolute select-none"
            :class="f.cls"
            :style="f.style"
          >{{ f.emoji }}</span>
        </div>

        <div class="celebration-card relative w-full max-w-sm rounded-3xl border border-white/10 bg-gray-900/95 p-7 text-center shadow-2xl shadow-black/60">
          <div class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full blur-3xl" :class="isBirthday ? 'bg-rose-500/30' : 'bg-amber-400/25'"></div>

          <div v-if="isPreview" class="relative mb-3 inline-block text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full border border-blue-400/40 bg-blue-500/10 text-blue-200">
            Simulación
          </div>
          <div class="celebration-emoji relative text-6xl mb-3" aria-hidden="true">{{ isBirthday ? '🎂' : '🏆' }}</div>
          <h2 class="relative text-2xl font-bold text-white leading-tight break-words">{{ title }}</h2>
          <p v-for="(line, i) in lines" :key="i" class="relative mt-2 text-sm text-gray-300 break-words">{{ line }}</p>

          <button
            type="button"
            class="relative mt-6 w-full rounded-xl py-2.5 font-semibold text-white shadow-lg transition"
            :class="isBirthday ? 'bg-rose-500 hover:bg-rose-400 shadow-rose-500/30' : 'bg-amber-500 hover:bg-amber-400 shadow-amber-500/30'"
            @click="stop"
          >
            {{ isPreview ? 'Cerrar simulación' : '¡Gracias! 🎉' }}
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { DEFAULT_CELEBRATION_EFFECTS, useCelebration, type CelebrationEffect } from '../composables/useCelebration'

const { user } = useAuth()
const { celebrations, current, playing, play, stop, alreadySeen } = useCelebration()
const isPreview = useState<any>('celebration_preview')

const canvas = ref<HTMLCanvasElement | null>(null)
const reducedMotion = ref(false)

const firstName = computed(() => String((user.value as any)?.first_name || (user.value as any)?.name || '').trim().split(' ')[0])
const birthday = computed(() => current.value.find(c => c.type === 'birthday'))
const anniversary = computed(() => current.value.find(c => c.type === 'anniversary'))
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

// union of the effects of today's celebrations (birthday + anniversary)
const effects = computed(() => {
  const set = new Set<CelebrationEffect>()
  for (const c of current.value) {
    const list = Array.isArray(c.effects) ? c.effects : DEFAULT_CELEBRATION_EFFECTS[c.type]
    list.forEach(e => set.add(e))
  }
  return set
})

// ---- DOM effects (balloons, stars, emoji rain) ----
type Floater = { id: string; emoji: string; cls: string; style: Record<string, string> }
const floaters = ref<Floater[]>([])
const rnd = (min: number, max: number) => min + Math.random() * (max - min)

const buildFloaters = () => {
  const out: Floater[] = []
  const fx = effects.value
  if (fx.has('balloons')) {
    const hues = [0, 25, 50, 140, 200, 270, 320]
    for (let i = 0; i < 16; i++) {
      out.push({
        id: `b${i}`, emoji: '🎈', cls: 'celebration-rise bottom-0',
        style: {
          left: `${(i * 6.1 + rnd(0, 5)) % 95}%`, fontSize: `${rnd(30, 52)}px`,
          animationDelay: `${rnd(0, 3)}s`, animationDuration: `${rnd(5, 8)}s`,
          filter: `hue-rotate(${hues[i % hues.length]}deg)`,
        },
      })
    }
  }
  if (fx.has('stars')) {
    const pool = ['⭐', '✨', '🌟', '💫']
    for (let i = 0; i < 18; i++) {
      out.push({
        id: `s${i}`, emoji: pool[i % pool.length], cls: 'celebration-twinkle',
        style: {
          left: `${rnd(2, 94)}%`, top: `${rnd(3, 90)}%`, fontSize: `${rnd(16, 34)}px`,
          animationDelay: `${rnd(0, 2)}s`, animationDuration: `${rnd(1.6, 2.8)}s`,
        },
      })
    }
  }
  if (fx.has('emojis')) {
    const pool = isBirthday.value ? ['🎉', '🥳', '🎁', '🎂', '🍰', '🎊'] : ['🎉', '🥳', '🏆', '👏', '🙌', '🎊']
    for (let i = 0; i < 22; i++) {
      out.push({
        id: `e${i}`, emoji: pool[i % pool.length], cls: 'celebration-fall top-0',
        style: {
          left: `${(i * 4.4 + rnd(0, 4)) % 96}%`, fontSize: `${rnd(22, 38)}px`,
          animationDelay: `${rnd(0, 3.5)}s`, animationDuration: `${rnd(3.5, 6)}s`,
        },
      })
    }
  }
  floaters.value = out
}

// ---- canvas effects (confetti, fireworks), no dependencies ----
type Confetti = { x: number; y: number; vx: number; vy: number; w: number; h: number; rot: number; vr: number; color: string }
type Rocket = { x: number; y: number; vy: number; targetY: number; hue: number }
type Spark = { x: number; y: number; vx: number; vy: number; life: number; hue: number }
const COLORS = ['#f43f5e', '#fb923c', '#facc15', '#4ade80', '#38bdf8', '#a78bfa', '#f472b6']
let confetti: Confetti[] = []
let rockets: Rocket[] = []
let sparks: Spark[] = []
let schedule: Array<{ at: number; run: () => void }> = []
let raf = 0

const runCanvas = () => {
  const el = canvas.value
  const fx = effects.value
  if (!el || (!fx.has('confetti') && !fx.has('fireworks'))) return
  const ctx = el.getContext('2d')
  if (!ctx) return
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  const w = window.innerWidth
  const h = window.innerHeight
  el.width = w * dpr
  el.height = h * dpr
  ctx.scale(dpr, dpr)

  confetti = []; rockets = []; sparks = []; schedule = []
  const small = w < 640

  const burst = (originX: number, count: number) => {
    for (let i = 0; i < count; i++) {
      const angle = -Math.PI / 2 + (Math.random() - 0.5) * 1.6
      const speed = 7 + Math.random() * 9
      confetti.push({
        x: originX, y: h,
        vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed * 1.4,
        w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
        rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3,
        color: COLORS[(Math.random() * COLORS.length) | 0],
      })
    }
  }
  const explode = (r: Rocket) => {
    const n = small ? 45 : 70
    for (let i = 0; i < n; i++) {
      const a = (Math.PI * 2 * i) / n
      const s = 2 + Math.random() * 3.5
      sparks.push({ x: r.x, y: r.y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 1, hue: r.hue + rnd(-20, 20) })
    }
  }

  if (fx.has('confetti')) {
    const count = small ? 35 : 60
    for (const at of [0, 700, 1500]) {
      schedule.push({ at, run: () => { burst(w * 0.15, count); burst(w * 0.85, count) } })
    }
  }
  if (fx.has('fireworks')) {
    for (let i = 0; i < (small ? 7 : 10); i++) {
      schedule.push({
        at: 200 + i * 550 + rnd(0, 250),
        run: () => rockets.push({ x: rnd(w * 0.1, w * 0.9), y: h, vy: -rnd(9, 13), targetY: rnd(h * 0.12, h * 0.45), hue: rnd(0, 360) }),
      })
    }
  }
  schedule.sort((a, b) => a.at - b.at)
  const startedAt = performance.now()

  const frame = (now: number) => {
    const t = now - startedAt
    while (schedule.length && t >= schedule[0].at) schedule.shift()!.run()

    ctx.clearRect(0, 0, w, h)

    for (const p of confetti) {
      p.vy += 0.25; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.rot += p.vr
      ctx.save()
      ctx.translate(p.x, p.y)
      ctx.rotate(p.rot)
      ctx.fillStyle = p.color
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.abs(Math.cos(p.rot * 2)))
      ctx.restore()
    }
    confetti = confetti.filter(p => p.y < h + 40)

    for (const r of rockets) {
      r.y += r.vy
      r.vy *= 0.985
      ctx.fillStyle = `hsl(${r.hue} 100% 75%)`
      ctx.fillRect(r.x - 1.5, r.y, 3, 10)
      if (r.y <= r.targetY || r.vy > -2) { explode(r); r.y = -9999 }
    }
    rockets = rockets.filter(r => r.y > -9000)

    for (const s of sparks) {
      s.vy += 0.05; s.vx *= 0.98; s.vy *= 0.98
      s.x += s.vx; s.y += s.vy; s.life -= 0.012
      ctx.globalAlpha = Math.max(0, s.life)
      ctx.fillStyle = `hsl(${s.hue} 100% 65%)`
      ctx.beginPath()
      ctx.arc(s.x, s.y, 2.2, 0, Math.PI * 2)
      ctx.fill()
    }
    ctx.globalAlpha = 1
    sparks = sparks.filter(s => s.life > 0)

    if (confetti.length || rockets.length || sparks.length || schedule.length) raf = requestAnimationFrame(frame)
  }
  raf = requestAnimationFrame(frame)
}

const stopCanvas = () => {
  cancelAnimationFrame(raf)
  raf = 0
  confetti = []; rockets = []; sparks = []; schedule = []
}

watch(playing, async (on) => {
  stopCanvas()
  if (!on) return
  if (reducedMotion.value) return
  buildFloaters()
  await nextTick()
  runCanvas()
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
  stopCanvas()
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

.celebration-rise { opacity: 0; animation-name: celebration-rise; animation-timing-function: ease-in; animation-iteration-count: infinite; }
.celebration-fall { opacity: 0; animation-name: celebration-fall; animation-timing-function: linear; animation-iteration-count: infinite; }
.celebration-twinkle { opacity: 0; animation-name: celebration-twinkle; animation-timing-function: ease-in-out; animation-iteration-count: infinite; }

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
@keyframes celebration-fall {
  0% { transform: translateY(-10vh) rotate(0); opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { transform: translateY(110vh) rotate(360deg); opacity: 0; }
}
@keyframes celebration-twinkle {
  0%, 100% { transform: scale(0.4) rotate(0); opacity: 0; }
  50% { transform: scale(1.2) rotate(20deg); opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .celebration-card, .celebration-emoji { animation: none; }
}
</style>
