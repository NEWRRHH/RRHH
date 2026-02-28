export default defineNuxtConfig({
  ssr: true,
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      title: 'RRHH - Front (Nuxt)'
    }
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      // Reverb / WebSocket settings (used by your client-side notification code)
      reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || 'localhost',
      reverbPort: process.env.NUXT_PUBLIC_REVERB_PORT || '6001',
      reverbAppId: process.env.NUXT_PUBLIC_REVERB_APP_ID || '',
      reverbAppKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
    }
  }
})
