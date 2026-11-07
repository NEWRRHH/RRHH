export default defineNuxtConfig({
  ssr: true,
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
  // En Docker sobre Windows los bind mounts no disparan inotify:
  // con polling el hot-reload vuelve a funcionar sin reiniciar el contenedor.
  vite: {
    server: {
      watch: {
        usePolling: true,
        interval: 1000,
      },
    },
  },
  app: {
    head: {
      title: 'RRHH - Front (Nuxt)'
    }
  },
  runtimeConfig: {
    // Server-side only: where the Nitro proxy (server/api/*) forwards API
    // calls. Inside Docker it is the internal nginx service; when running
    // `npm run dev` on the host set NUXT_API_BASE=http://localhost:8000.
    apiBase: process.env.NUXT_API_BASE || 'http://localhost:8000',
    // Set NUXT_COOKIE_SECURE=true when the app is served over HTTPS.
    cookieSecure: process.env.NUXT_COOKIE_SECURE === 'true',
    public: {
      // Browser-facing base URL, only used for static assets (/storage/*).
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      // Reverb / WebSocket settings (used by your client-side notification code)
      reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || 'localhost',
      reverbPort: process.env.NUXT_PUBLIC_REVERB_PORT || '6001',
      reverbAppId: process.env.NUXT_PUBLIC_REVERB_APP_ID || '',
      reverbAppKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
    }
  }
})