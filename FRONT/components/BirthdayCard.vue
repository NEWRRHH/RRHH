<template>
  <div class="w-full">
    <div class="relative overflow-hidden bg-gray-900 border border-gray-800 rounded-2xl p-5 flex flex-col shadow-lg shadow-black/40 transition-transform hover:-translate-y-0.5">
      <div class="absolute -top-8 -right-8 w-28 h-28 bg-rose-500/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-10 -left-10 w-28 h-28 bg-orange-400/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-rose-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-rose-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13a3 3 0 013-3h1a2 2 0 012 2v1a3 3 0 01-3 3m-3-3a3 3 0 00-3-3H8a2 2 0 00-2 2v1a3 3 0 003 3m0 0v2m0 0h6"/>
            </svg>
          </div>
          <h3 class="text-xs text-gray-300 uppercase tracking-wide font-medium">Cumpleaños y aniversarios</h3>
        </div>
        <span class="text-[11px] px-2 py-1 rounded-full border border-rose-400/30 bg-rose-500/10 text-rose-200 shrink-0">
          {{ birthdays?.length || 0 }} próximos
        </span>
      </div>

      <div v-if="loading" class="space-y-3">
        <div class="h-24 rounded-xl bg-gray-800 animate-pulse"></div>
        <div class="h-12 rounded-xl bg-gray-800 animate-pulse"></div>
        <div class="h-12 rounded-xl bg-gray-800 animate-pulse"></div>
      </div>

      <div v-else-if="birthdays && birthdays.length" class="flex flex-col gap-3">
        <!-- next (or today's) celebration -->
        <div
          class="relative overflow-hidden rounded-xl border p-3"
          :class="featured.is_today
            ? 'border-amber-400/40 bg-gradient-to-r from-rose-500/20 via-amber-400/15 to-fuchsia-500/20 celebrate-glow'
            : isAnniversary(featured) ? 'border-amber-500/20 bg-gradient-to-r from-amber-500/10 to-yellow-400/5' : 'border-rose-500/20 bg-gradient-to-r from-rose-500/10 to-orange-400/10'"
        >
          <span v-if="featured.is_today" class="celebrate-sparkle" aria-hidden="true" style="top:8px;right:14px">✨</span>
          <span v-if="featured.is_today" class="celebrate-sparkle" aria-hidden="true" style="bottom:8px;right:44px;animation-delay:.7s">🎉</span>

          <div class="text-[11px] uppercase tracking-wide mb-2" :class="featured.is_today ? 'text-amber-200 font-semibold' : isAnniversary(featured) ? 'text-amber-200/90' : 'text-rose-200/90'">
            {{ featured.is_today ? '¡Hoy se celebra!' : 'Siguiente' }}
          </div>
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-800 border flex items-center justify-center text-white font-semibold" :class="isAnniversary(featured) ? 'border-amber-400/40' : 'border-rose-400/30'">
                <img v-if="featured.photo" :src="featured.photo" class="w-full h-full object-cover" />
                <span v-else>{{ initials(featured) }}</span>
              </div>
              <span class="absolute -bottom-1 -right-1 text-base leading-none" :class="{ 'celebrate-bounce': featured.is_today }" aria-hidden="true">{{ emoji(featured) }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-white font-semibold truncate">{{ featured.full_name || featured.first_name }}</p>
              <p class="text-xs" :class="isAnniversary(featured) ? 'text-amber-100/90' : 'text-rose-100/90'">{{ featured.is_today ? 'Hoy' : formatDate(featured.date) }}</p>
              <p class="text-[11px] text-gray-300">{{ describe(featured) }}</p>
            </div>
            <button
              v-if="isMine(featured)"
              type="button"
              class="shrink-0 w-9 h-9 rounded-full bg-amber-400/20 hover:bg-amber-400/30 text-lg transition"
              title="Ver festejo"
              aria-label="Ver festejo"
              @click="play"
            >🎉</button>
          </div>
        </div>

        <div class="space-y-2 pr-1 birthday-scroll max-h-40 overflow-y-auto">
          <div
            v-for="b in birthdays.slice(1)"
            :key="b.id"
            class="rounded-xl border px-3 py-2 flex items-center gap-3"
            :class="b.is_today ? 'border-amber-400/40 bg-amber-400/10' : 'border-gray-800 bg-gray-950/70'"
          >
            <div class="relative shrink-0">
              <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-200 text-xs font-semibold">
                <img v-if="b.photo" :src="b.photo" class="w-full h-full object-cover" />
                <span v-else>{{ initials(b) }}</span>
              </div>
              <span class="absolute -bottom-1 -right-1 text-xs leading-none" aria-hidden="true">{{ emoji(b) }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm text-gray-100 truncate">{{ b.full_name || b.first_name }}</p>
              <p class="text-[11px] text-gray-400 truncate">{{ b.is_today ? 'Hoy' : formatDate(b.date) }} · {{ describe(b) }}</p>
            </div>
            <button
              v-if="isMine(b)"
              type="button"
              class="shrink-0 text-base"
              title="Ver festejo"
              aria-label="Ver festejo"
              @click="play"
            >🎉</button>
            <span
              v-else
              class="shrink-0 text-[11px] rounded-full px-2 py-0.5 border"
              :class="b.is_today ? 'text-amber-100 border-amber-400/40 bg-amber-400/20' : isAnniversary(b) ? 'text-amber-200 border-amber-500/30 bg-amber-500/10' : 'text-rose-200 border-rose-500/30 bg-rose-500/10'"
            >
              {{ b.is_today ? '¡Hoy!' : b.years }}
            </span>
          </div>
        </div>
      </div>

      <div v-else class="flex-1 rounded-xl border border-gray-800 bg-gray-950/60 p-5 text-center text-gray-400 flex items-center justify-center">
        No hay cumpleaños ni aniversarios próximos
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'

// Items come from /api/birthdays: { type: 'birthday' | 'anniversary', date, years, is_today, user_id, ... }
const props = defineProps({
  birthdays: { type: Array as PropType<Array<Record<string, any>>>, default: () => [] },
  loading: { type: Boolean as PropType<boolean>, default: false }
})

const { user } = useAuth()
const { celebrations, play } = useCelebration()

const featured = computed(() => props.birthdays[0] || {})

const isAnniversary = (b: Record<string, any>) => b?.type === 'anniversary'
const emoji = (b: Record<string, any>) => (isAnniversary(b) ? '🏆' : '🎂')
const yearsText = (n: number) => `${n} ${Number(n) === 1 ? 'año' : 'años'}`
const describe = (b: Record<string, any>) => isAnniversary(b)
  ? `${b.is_today ? 'Cumple' : 'Cumplirá'} ${yearsText(b.years)} en la empresa`
  : `Cumple ${yearsText(b.years)}`

// the logged-in user's own celebration today can replay the confetti
const isMine = (b: Record<string, any>) =>
  !!b?.is_today && b.user_id === (user.value as any)?.id && celebrations.value.length > 0

const formatDate = (iso?: string) => {
  const d = parseDate(iso)
  return d ? d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }) : (iso || '')
}

const initials = (b: Record<string, any>) => {
  const full = String(b?.full_name || b?.first_name || '?').trim()
  if (!full) return '?'
  const parts = full.split(' ').filter(Boolean)
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase()
  return `${parts[0].slice(0, 1)}${parts[1].slice(0, 1)}`.toUpperCase()
}
</script>

<style scoped>
.celebrate-glow { animation: celebrate-glow 2.4s ease-in-out infinite; }
.celebrate-sparkle { position: absolute; font-size: 14px; pointer-events: none; animation: celebrate-twinkle 1.6s ease-in-out infinite; }
.celebrate-bounce { display: inline-block; animation: celebrate-bounce 1.2s ease-in-out infinite; }

@keyframes celebrate-glow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
  50% { box-shadow: 0 0 22px 2px rgba(251, 191, 36, 0.25); }
}
@keyframes celebrate-twinkle {
  0%, 100% { opacity: 0.2; transform: scale(0.8) rotate(0); }
  50% { opacity: 1; transform: scale(1.15) rotate(12deg); }
}
@keyframes celebrate-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
@media (prefers-reduced-motion: reduce) {
  .celebrate-glow, .celebrate-sparkle, .celebrate-bounce { animation: none; }
}

.birthday-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(251, 113, 133, 0.45) rgba(31, 41, 55, 0.4);
}

.birthday-scroll::-webkit-scrollbar {
  width: 8px;
}

.birthday-scroll::-webkit-scrollbar-track {
  background: rgba(31, 41, 55, 0.4);
  border-radius: 9999px;
}

.birthday-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, rgba(251, 113, 133, 0.8), rgba(249, 115, 22, 0.8));
  border-radius: 9999px;
}
</style>
