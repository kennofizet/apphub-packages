<template>
  <div
    class="apphub-win"
    :class="{
      'apphub-win--active': active,
      'apphub-win--fullscreen': window.display === 'fullscreen',
    }"
    :style="frameStyle"
    @mousedown="onFocus"
  >
    <header class="apphub-win__titlebar" @mousedown.stop="onDragStart">
      <span class="apphub-win__icon">{{ window.icon }}</span>
      <span class="apphub-win__title">{{ window.title }}</span>
      <div class="apphub-win__controls" @mousedown.stop>
        <button
          v-if="window.layoutKey"
          type="button"
          class="apphub-win__btn"
          :title="window.display === 'fullscreen' ? labels.window_restore : labels.window_fullscreen"
          @mousedown.stop
          @click.stop="onToggleDisplay"
        >
          {{ window.display === 'fullscreen' ? '⤢' : '⛶' }}
        </button>
        <button type="button" class="apphub-win__btn" :title="labels.window_minimize" @mousedown.stop @click.stop="onMinimize">—</button>
        <button type="button" class="apphub-win__btn apphub-win__btn--close" :title="labels.window_close" @mousedown.stop @click.stop="onClose">×</button>
      </div>
    </header>
    <section class="apphub-win__body">
      <component :is="window.component" v-bind="window.props" />
    </section>
  </div>
</template>

<script setup>
import { computed, inject, onBeforeUnmount, ref } from 'vue'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { t } from '../../../i18n/index.js'
import { useWindowManager } from '../composables/useWindowManager.js'
import { clampWindowToWorkArea } from '../utils/windowLayout.js'

const DESKTOP_HOST_KEY = 'apphubDesktopHost'

const props = defineProps({
  window: { type: Object, required: true },
  active: { type: Boolean, default: false },
})

const emit = defineEmits(['session-change'])

const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))
const workAreaRef = inject(DESKTOP_HOST_KEY, null)

const labels = computed(() => ({
  window_close: t('window_close', lang.value),
  window_minimize: t('window_minimize', lang.value),
  window_fullscreen: t('window_fullscreen', lang.value),
  window_restore: t('window_restore', lang.value),
}))

const wm = useWindowManager()
const drag = ref(null)

const frameStyle = computed(() => ({
  width: `${props.window.width}px`,
  height: `${props.window.height}px`,
  left: `${props.window.x}px`,
  top: `${props.window.y}px`,
  zIndex: String(props.window.zIndex),
}))

function getPointerInWorkArea(clientX, clientY) {
  const el = workAreaRef?.value
  if (!el) return { x: clientX, y: clientY }
  const rect = el.getBoundingClientRect()
  return { x: clientX - rect.left, y: clientY - rect.top }
}

function onFocus() {
  wm?.focusWindow(props.window.id)
}

function notifySession() {
  emit('session-change')
}

function onClose() {
  wm?.closeWindow(props.window.id)
  notifySession()
}

function onMinimize() {
  wm?.minimizeWindow(props.window.id)
  notifySession()
}

function onToggleDisplay() {
  wm?.toggleWindowDisplay(props.window.id)
  notifySession()
}

function onDragStart(event) {
  if (!wm) return
  if (event.target.closest('button, .apphub-win__controls')) return

  const win = wm.state.windows.find((w) => w.id === props.window.id)
  if (!win) return

  if (win.display === 'fullscreen' && win.layoutKey) {
    const pointer = getPointerInWorkArea(event.clientX, event.clientY)
    wm.restoreWindowFromFullscreen(props.window.id, {
      pointerX: pointer.x,
      pointerY: pointer.y,
      offsetX: event.offsetX,
      offsetY: event.offsetY,
    })
  }

  const current = wm.state.windows.find((w) => w.id === props.window.id)
  if (!current || current.display === 'fullscreen') return

  drag.value = {
    id: props.window.id,
    startX: event.clientX,
    startY: event.clientY,
    origX: current.x,
    origY: current.y,
  }
  window.addEventListener('mousemove', onDragMove)
  window.addEventListener('mouseup', onDragEnd)
}

function onDragMove(event) {
  if (!drag.value) return
  const win = wm.state.windows.find((w) => w.id === drag.value.id)
  if (!win) return
  win.x = drag.value.origX + (event.clientX - drag.value.startX)
  win.y = drag.value.origY + (event.clientY - drag.value.startY)
  clampWindowToWorkArea(win)
}

function onDragEnd() {
  window.removeEventListener('mousemove', onDragMove)
  window.removeEventListener('mouseup', onDragEnd)
  if (drag.value) {
    const win = wm.state.windows.find((w) => w.id === drag.value.id)
    if (win) clampWindowToWorkArea(win)
    wm?.saveWindowLayoutState(props.window.id)
    notifySession()
  }
  drag.value = null
}

onBeforeUnmount(onDragEnd)
</script>
