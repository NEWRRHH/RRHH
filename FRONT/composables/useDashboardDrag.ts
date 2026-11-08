// Drag & drop for dashboard cards with SortableJS (mouse + touch).
//
// Markup contract inside `root`:
//   - data-dash-list               container whose direct children are cards (SortableJS list)
//   - data-dash-drop="0".."8"      a position;  data-dash-drop="hidden"  the hidden-cards tray
//                                  (any element; the whole box accepts the drop)
//   - data-dash-key="<widget key>" a draggable card
//
// SortableJS only provides the gesture. On drop, the target is resolved from
// the pointer position (so dropping anywhere on a zone works, header text
// included), the DOM move SortableJS made is reverted and `onDrop(key, target)`
// is called: Vue stays the single owner of the layout state (swap / hide /
// show rules live in moveDashboardWidget).
import Sortable from 'sortablejs'
import { nextTick, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'
import type { DashboardWidgetKey } from '~/utils/dashboardWidgets'

export type DashboardDropTarget = number | 'hidden'

function pointerOf(event: Event | undefined): { x: number; y: number } | null {
  if (!event) return null
  const touch = (event as TouchEvent).changedTouches?.[0]
  if (touch) return { x: touch.clientX, y: touch.clientY }
  const mouse = event as MouseEvent
  return typeof mouse.clientX === 'number' ? { x: mouse.clientX, y: mouse.clientY } : null
}

function zoneOf(el: Element | null | undefined, root: HTMLElement): HTMLElement | null {
  const zone = el?.closest?.('[data-dash-drop]') as HTMLElement | null
  return zone && root.contains(zone) ? zone : null
}

export function useDashboardDrag(
  root: Ref<HTMLElement | null>,
  onDrop: (key: DashboardWidgetKey, target: DashboardDropTarget) => void,
  enabled?: Ref<boolean>,
) {
  let instances: Sortable[] = []
  /** key of the card being dragged (null when idle) */
  const dragging = ref<DashboardWidgetKey | null>(null)
  const group = `dashboard-${Math.random().toString(36).slice(2)}`

  function destroy() {
    instances.forEach((s) => s.destroy())
    instances = []
  }

  function setup() {
    destroy()
    const container = root.value
    if (!container || (enabled && !enabled.value)) return

    container.querySelectorAll<HTMLElement>('[data-dash-list]').forEach((list) => {
      instances.push(Sortable.create(list, {
        group,
        draggable: '[data-dash-key]',
        sort: false,
        animation: 150,
        // same behaviour on desktop and touch devices
        forceFallback: true,
        fallbackOnBody: true,
        fallbackTolerance: 4,
        // on phones a short press-and-hold starts the drag, a swipe still scrolls
        delay: 180,
        delayOnTouchOnly: true,
        touchStartThreshold: 6,
        emptyInsertThreshold: 24,
        ghostClass: 'dash-drag-ghost',
        chosenClass: 'dash-drag-chosen',
        dragClass: 'dash-drag-dragging',
        onStart: (evt) => {
          dragging.value = (evt.item.dataset.dashKey as DashboardWidgetKey) || null
        },
        onEnd: (evt) => {
          dragging.value = null
          const key = evt.item.dataset.dashKey as DashboardWidgetKey | undefined

          // undo SortableJS's DOM move: Vue re-renders from the new state
          if (evt.to !== evt.from || evt.oldIndex !== evt.newIndex) {
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex ?? 0] ?? null)
          }

          const point = pointerOf((evt as any).originalEvent)
          const hit = point ? document.elementFromPoint(point.x, point.y) : null
          const target = zoneOf(hit, container) ?? zoneOf(evt.to, container)
          const origin = zoneOf(evt.from, container)
          if (!key || !target || target === origin) return

          const raw = target.dataset.dashDrop as string
          onDrop(key, raw === 'hidden' ? 'hidden' : Number(raw))
        },
      }))
    })
  }

  onMounted(() => nextTick(setup))
  onBeforeUnmount(destroy)
  if (enabled) {
    watch(enabled, () => nextTick(setup))
  }

  return { dragging, refresh: () => nextTick(setup) }
}
