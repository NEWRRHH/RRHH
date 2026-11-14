module.exports = {
  future: {
    // hover: styles only on devices with a real mouse; on touch screens a tap
    // used to leave cards "stuck" lifted/highlighted.
    hoverOnlyWhenSupported: true,
  },
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.{vue,js,ts}',
    './pages/**/*.{vue,js,ts}',
    './composables/**/*.{js,ts,vue}',
    './app.vue',
    './nuxt.config.{js,ts}'
  ],
  theme: {
    extend: {}
  },
  plugins: []
}
