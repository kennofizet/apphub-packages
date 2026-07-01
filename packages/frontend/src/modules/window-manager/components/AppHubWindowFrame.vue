<template>
  <div
    class="apphub-win"
    :class="{
      'apphub-win--active': active,
      'apphub-win--fullscreen': window.display === 'fullscreen',
      'apphub-win--resizable': window.display !== 'fullscreen',
    }"
    :style="frameStyle"
    :data-window-id="window.id"
    @pointerdown.capture="onFocus"
    @focusin.capture="onFocus"
  >
    <header
      class="apphub-win__titlebar"
      @mousedown.stop="onDragStart"
      @dblclick.stop="onTitlebarDblClick"
    >
      <span class="apphub-win__icon">
        <AppHubCatalogIcon
          :app="{ name: window.title, icon: window.icon, icon_url: window.icon_url }"
          emoji-class="apphub-win__icon-emoji"
          img-class="apphub-win__icon-img"
        />
      </span>
      <span class="apphub-win__title">{{ window.title }}</span>
      <div class="apphub-win__controls" @mousedown.stop @dblclick.stop>
        <button
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
    <section ref="bodyRef" class="apphub-win__body" @pointerdown.capture="onFocus">
      <component :is="window.component" v-bind="window.props" />
      <div
        v-if="showIframeShield"
        class="apphub-win__body-shield"
        aria-hidden="true"
        @pointerdown.stop.prevent="onIframeShieldPointerDown"
      />
    </section>

    <template v-if="window.display !== 'fullscreen'">
      <div
        v-for="edge in resizeEdges"
        :key="edge"
        class="apphub-win__resize"
        :class="`apphub-win__resize--${edge}`"
        @mousedown.stop="onResizeStart(edge, $event)"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, inject, onBeforeUnmount, provide, ref } from 'vue'
import AppHubCatalogIcon from '../../../components/AppHubCatalogIcon.vue'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { t } from '../../../i18n/index.js'
import { useWindowManager } from '../composables/useWindowManager.js'
import {
  useWindowFrameActivation,
  WINDOW_FRAME_ACTIVATE_KEY,
} from '../composables/useWindowFrameActivation.js'
import { applyWindowResize, clampWindowToWorkArea } from '../utils/windowLayout.js'

const DESKTOP_HOST_KEY = 'apphubDesktopHost'
const resizeEdges = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw']

const props = defineProps({
  window: { type: Object, required: true },
  active: { type: Boolean, default: false },
})

const emit = defineEmits(['session-change'])

const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))
const workAreaRef = inject(DESKTOP_HOST_KEY, null)

const labels = computed(() => ({
  window_close: t('window_close', lang.value),
  window_minimize: t('window_minimize', lang.value),
  window_fullscreen: t('window_fullscreen', lang.value),
  window_restore: t('window_restore', lang.value),
}))

const wm = useWindowManager()
const drag = ref(null)
const resize = ref(null)
const bodyRef = ref(null)

function onFocus() {
  if (!wm) return
  wm.focusWindow(props.window.id)
}

provide(WINDOW_FRAME_ACTIVATE_KEY, onFocus)

const { hasEmbeddedFrame } = useWindowFrameActivation(bodyRef, onFocus)

/** Cross-origin iframe clicks never reach the parent — shield captures the first click. */
const showIframeShield = computed(() => !props.active && hasEmbeddedFrame.value)

function onIframeShieldPointerDown() {
  onFocus()
}

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

function onTitlebarDblClick(event) {
  if (event.target.closest('button, .apphub-win__controls')) return
  cleanupPointerHandlers()
  wm?.toggleWindowDisplay(props.window.id)
  notifySession()
}

function onDragStart(event) {
  if (!wm) return
  if (event.target.closest('button, .apphub-win__controls')) return

  wm.focusWindow(props.window.id)

  const win = wm.state.windows.find((w) => w.id === props.window.id)
  if (!win) return

  if (win.display === 'fullscreen') {
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
    if (win) {
      clampWindowToWorkArea(win)
      wm?.clearSnap(props.window.id)
    }
    wm?.saveWindowLayoutState(props.window.id)
    notifySession()
  }
  drag.value = null
}

function onResizeStart(edge, event) {
  if (!wm || event.button !== 0) return
  const win = wm.state.windows.find((w) => w.id === props.window.id)
  if (!win || win.display === 'fullscreen') return

  wm.focusWindow(props.window.id)
  wm.clearSnap(props.window.id)
  resize.value = {
    id: props.window.id,
    edge,
    startX: event.clientX,
    startY: event.clientY,
  }
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', onResizeEnd)
}

function onResizeMove(event) {
  if (!resize.value) return
  const win = wm.state.windows.find((w) => w.id === resize.value.id)
  if (!win) return
  const dx = event.clientX - resize.value.startX
  const dy = event.clientY - resize.value.startY
  applyWindowResize(win, resize.value.edge, dx, dy)
  resize.value.startX = event.clientX
  resize.value.startY = event.clientY
}

function onResizeEnd() {
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', onResizeEnd)
  if (resize.value) {
    wm?.saveWindowLayoutState(props.window.id)
    notifySession()
  }
  resize.value = null
}

function cleanupPointerHandlers() {
  onDragEnd()
  onResizeEnd()
}

onBeforeUnmount(cleanupPointerHandlers)
</script>
