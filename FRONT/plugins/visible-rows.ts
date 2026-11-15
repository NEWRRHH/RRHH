// v-visible-rows="n": a list shows its first n items and scrolls for the rest.
//
// Put it on the element whose direct children are the list items. Its height
// is measured from the real items (they differ in height between cards), so
// the cut always falls exactly under the n-th item. Fewer than n+1 items: no
// height limit and no scrollbar.
import type { Directive } from 'vue'

type RowsEl = HTMLElement & { __rowsResize?: () => void }

function apply(el: HTMLElement, rows: unknown) {
  const n = Number(rows)
  const items = Array.from(el.children).filter((c): c is HTMLElement => c instanceof HTMLElement)
  if (!Number.isFinite(n) || n < 1 || items.length <= n) {
    el.style.maxHeight = ''
    el.style.overflowY = ''
    return
  }
  const last = items[n - 1]
  // + scrollTop: correct even when the list is already scrolled
  const height = last.getBoundingClientRect().bottom - el.getBoundingClientRect().top + el.scrollTop
  el.style.maxHeight = `${Math.ceil(height)}px`
  el.style.overflowY = 'auto'
}

const visibleRows: Directive<RowsEl, number> = {
  mounted(el, binding) {
    apply(el, binding.value)
    el.__rowsResize = () => apply(el, binding.value)
    window.addEventListener('resize', el.__rowsResize)
  },
  updated(el, binding) {
    if (el.__rowsResize) window.removeEventListener('resize', el.__rowsResize)
    el.__rowsResize = () => apply(el, binding.value)
    window.addEventListener('resize', el.__rowsResize)
    apply(el, binding.value)
  },
  beforeUnmount(el) {
    if (el.__rowsResize) window.removeEventListener('resize', el.__rowsResize)
  },
  // server render: no measuring, the client applies it on mount
  getSSRProps: () => ({}),
}

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('visible-rows', visibleRows)
})
