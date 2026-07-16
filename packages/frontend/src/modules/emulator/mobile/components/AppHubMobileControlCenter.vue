<template>
  <Transition name="apphub-mobile-control-center">
    <div
      v-if="visible"
      class="apphub-mobile-control-center"
      :class="[
        `apphub-mobile-control-center--${phone || '_default'}`,
        { 'apphub-mobile-control-center--pulling': pulling || closePullActive },
      ]"
      role="dialog"
      aria-modal="true"
      :aria-label="labels.title"
    >
      <button
        type="button"
        class="apphub-mobile-control-center__backdrop"
        :style="backdropStyle"
        :aria-label="labels.close"
        @click="close"
      />

      <section
        class="apphub-mobile-control-center__panel"
        :style="panelStyle"
        @pointerdown="onClosePullStart"
        @pointermove="onClosePullMove"
        @pointerup="onClosePullEnd"
        @pointercancel="onClosePullCancel"
      >
        <header class="apphub-mobile-control-center__hero">
          <div>
            <strong class="apphub-mobile-control-center__clock">{{ clock }}</strong>
            <p class="apphub-mobile-control-center__date">{{ formattedDate }}</p>
          </div>
          <div class="apphub-mobile-control-center__hero-trail">
            <span class="apphub-mobile-control-center__status-icons" aria-hidden="true">
              ◉ 5G ▮▮▮
            </span>
          </div>
        </header>

        <div class="apphub-mobile-control-center__tools">
          <button
            v-if="fullscreenSupported"
            type="button"
            class="apphub-mobile-control-center__tool"
            :class="{ 'apphub-mobile-control-center__tool--active': isFullscreen }"
            @click="emit('toggle-fullscreen')"
          >
            <span class="apphub-mobile-control-center__tool-icon" aria-hidden="true">
              {{ isFullscreen ? '↙' : '⛶' }}
            </span>
            <span>
              <strong>{{ isFullscreen ? labels.exitFullscreen : labels.fullscreen }}</strong>
              <small>{{ isFullscreen ? labels.on : labels.off }}</small>
            </span>
          </button>

          <button
            v-if="themeToggle"
            type="button"
            class="apphub-mobile-control-center__tool"
            :class="{ 'apphub-mobile-control-center__tool--active': activeTheme === 'light' }"
            @click="emit('toggle-theme')"
          >
            <span class="apphub-mobile-control-center__tool-icon" aria-hidden="true">
              {{ activeTheme === 'light' ? '☀' : '☾' }}
            </span>
            <span>
              <strong>{{ labels.appearance }}</strong>
              <small>{{ activeTheme === 'light' ? labels.light : labels.dark }}</small>
            </span>
          </button>

          <button
            type="button"
            class="apphub-mobile-control-center__tool"
            :disabled="loading"
            @click="refresh"
          >
            <span class="apphub-mobile-control-center__tool-icon" aria-hidden="true">↻</span>
            <span>
              <strong>{{ labels.refresh }}</strong>
              <small>{{ loading ? labels.loading : labels.ready }}</small>
            </span>
          </button>

          <button
            type="button"
            class="apphub-mobile-control-center__tool"
            :class="{ 'apphub-mobile-control-center__tool--active': unreadCount > 0 }"
            @click="scrollToNotifications"
          >
            <span class="apphub-mobile-control-center__tool-icon" aria-hidden="true">🔔</span>
            <span>
              <strong>{{ labels.notifications }}</strong>
              <small>{{ unreadCount ? `${unreadCount} ${labels.unread}` : labels.empty }}</small>
            </span>
          </button>
        </div>

        <label class="apphub-mobile-control-center__brightness">
          <span aria-hidden="true">A</span>
          <span class="apphub-mobile-control-center__brightness-track">
            <span aria-hidden="true">☀</span>
            <input
              type="range"
              min="0.25"
              max="1"
              step="0.05"
              :value="brightness"
              :aria-label="labels.brightness"
              @input="onBrightnessInput"
            >
            <span aria-hidden="true">☀</span>
          </span>
        </label>

        <button
          type="button"
          class="apphub-mobile-control-center__grabber-hit"
          :aria-label="labels.close"
          @pointerdown="onClosePullStart"
          @pointermove="onClosePullMove"
          @pointerup="onClosePullEnd"
          @pointercancel="onClosePullCancel"
          @touchstart.stop.prevent="onCloseTouchStart"
          @touchmove.stop.prevent="onCloseTouchMove"
          @touchend.stop.prevent="onCloseTouchEnd"
          @touchcancel.stop.prevent="onCloseTouchCancel"
        >
          <span class="apphub-mobile-control-center__grabber" aria-hidden="true" />
        </button>

        <section ref="notificationSection" class="apphub-mobile-control-center__notifications">
          <header class="apphub-mobile-control-center__section-header">
            <h3>{{ labels.notifications }}</h3>
          </header>

          <div class="apphub-mobile-control-center__notification-body">
            <p v-if="loading && !items.length" class="apphub-mobile-control-center__empty">
              {{ labels.loading }}
            </p>
            <p v-else-if="!items.length" class="apphub-mobile-control-center__empty">
              {{ labels.empty }}
            </p>

            <ul v-else class="apphub-mobile-control-center__notification-list">
              <TransitionGroup name="apphub-mobile-notification">
                <li
                  v-for="item in items"
                  :key="item.id"
                  class="apphub-mobile-control-center__notification"
                  :class="{
                    'apphub-mobile-control-center__notification--unread': !item.read_at,
                    'apphub-mobile-control-center__notification--removing':
                      removingIds.includes(item.id),
                    'apphub-mobile-control-center__notification--swiping':
                      notificationSwipe?.id === item.id,
                  }"
                  :style="notificationSwipeStyle(item.id)"
                  @pointerdown="onNotificationPointerDown(item.id, $event)"
                  @pointermove="onNotificationPointerMove(item.id, $event)"
                  @pointerup="onNotificationPointerEnd(item.id, $event)"
                  @pointercancel="onNotificationPointerCancel(item.id, $event)"
                >
                  <span class="apphub-mobile-control-center__notification-icon" aria-hidden="true">
                    {{ item.app_icon || '📱' }}
                  </span>
                  <div class="apphub-mobile-control-center__notification-content">
                    <div class="apphub-mobile-control-center__notification-meta">
                      <strong>{{ item.app_name || item.app_slug }}</strong>
                      <time :datetime="item.created_at">{{ formatWhen(item.created_at) }}</time>
                    </div>
                    <h4>{{ item.title }}</h4>
                    <p v-if="item.body">{{ item.body }}</p>
                  </div>
                </li>
              </TransitionGroup>
            </ul>

            <button
              v-if="hasMore"
              type="button"
              class="apphub-mobile-control-center__more"
              :disabled="loadingMore"
              @click="loadMore"
            >
              {{ loadingMore ? labels.loading : labels.loadMore }}
            </button>
          </div>

          <button
            v-if="items.length"
            type="button"
            class="apphub-mobile-control-center__clear-all"
            :aria-label="labels.readAll"
            :title="labels.readAll"
            @click="readAll"
          >
            🗑
          </button>
        </section>

        <button
          type="button"
          class="apphub-mobile-control-center__bottom-close-zone"
          :aria-label="labels.close"
          @pointerdown="onClosePullStart"
          @pointermove="onClosePullMove"
          @pointerup="onClosePullEnd"
          @pointercancel="onClosePullCancel"
          @touchstart.stop.prevent="onCloseTouchStart"
          @touchmove.stop.prevent="onCloseTouchMove"
          @touchend.stop.prevent="onCloseTouchEnd"
          @touchcancel.stop.prevent="onCloseTouchCancel"
        >
          <span aria-hidden="true" />
        </button>
      </section>
    </div>
  </Transition>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { USER_NOTIFICATION_CENTER_KEY } from '../../../user-notifications/composables/createUserNotificationCenter.js'
import { t } from '../../../../i18n/index.js'
import { resolveLang } from '../../../../i18n/resolveLang.js'

const props = defineProps({
  phone: { type: String, default: '_default' },
  fullscreenSupported: { type: Boolean, default: false },
  isFullscreen: { type: Boolean, default: false },
  activeTheme: { type: String, default: 'dark' },
  themeToggle: { type: Boolean, default: true },
  clock: { type: String, default: '' },
  brightness: { type: Number, default: 1 },
  pulling: { type: Boolean, default: false },
  pullProgress: { type: Number, default: 0 },
})

const emit = defineEmits(['toggle-fullscreen', 'toggle-theme', 'brightness-change'])
const center = inject(USER_NOTIFICATION_CENTER_KEY, null)
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))
const notificationSection = ref(null)
const closePullActive = ref(false)
const closePullProgress = ref(0)
const notificationSwipe = ref(null)
let closePull = null
let notificationHoldTimer = null

const labels = computed(() => ({
  title: t('mobile_control_center', lang.value),
  close: t('inbox_notif_close', lang.value),
  notifications: t('inbox_notif_title', lang.value),
  readAll: t('inbox_notif_read_all', lang.value),
  dismiss: t('inbox_notif_dismiss', lang.value),
  empty: t('inbox_notif_empty', lang.value),
  loading: t('inbox_notif_loading', lang.value),
  loadMore: t('inbox_notif_load_more', lang.value),
  refresh: t('mobile_control_refresh', lang.value),
  appearance: t('mobile_control_appearance', lang.value),
  brightness: t('mobile_control_brightness', lang.value),
  on: t('mobile_control_on', lang.value),
  off: t('mobile_control_off', lang.value),
  ready: t('mobile_control_ready', lang.value),
  unread: t('mobile_control_unread', lang.value),
  light: t('mobile_control_light', lang.value),
  dark: t('mobile_control_dark', lang.value),
  fullscreen: t('mobile_enter_fullscreen', lang.value),
  exitFullscreen: t('mobile_exit_fullscreen', lang.value),
}))

const formattedDate = computed(() => {
  // Depend on the ticking clock prop so the date updates after midnight.
  void props.clock
  return new Intl.DateTimeFormat(lang.value === 'vi' ? 'vi-VN' : 'en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date())
})

const open = computed(() => center?.state.drawerOpen ?? false)
const visible = computed(() => open.value || props.pulling)
const normalizedPullProgress = computed(() =>
  Math.max(0, Math.min(1, Number(props.pullProgress) || 0)),
)
const panelStyle = computed(() => {
  if (props.pulling) {
    return {
      transform: `translateY(${(normalizedPullProgress.value - 1) * 100}%)`,
    }
  }
  if (closePullActive.value) {
    return { transform: `translateY(${-closePullProgress.value * 100}%)` }
  }
  return undefined
})
const backdropStyle = computed(() => {
  if (props.pulling) return { opacity: normalizedPullProgress.value }
  if (closePullActive.value) return { opacity: 1 - closePullProgress.value }
  return undefined
})
const items = computed(() => center?.state.items ?? [])
const loading = computed(() => center?.state.loading ?? false)
const loadingMore = computed(() => center?.state.loadingMore ?? false)
const hasMore = computed(() => center?.state.hasMore ?? false)
const removingIds = computed(() => center?.state.removingIds ?? [])
const unreadCount = computed(() => center?.state.unreadCount ?? 0)

function close() {
  center?.closeDrawer?.()
}

function onClosePullStart(event) {
  if (event.pointerType === 'touch') return
  if (event.button !== 0 || closePull) return
  const target = event.target instanceof Element ? event.target : null
  const onGrabber = target?.closest('.apphub-mobile-control-center__grabber-hit')
  const onNotification = target?.closest('.apphub-mobile-control-center__notification')
  const onInteractive = target?.closest('button, input, a, select, textarea')
  const notificationBody = target?.closest('.apphub-mobile-control-center__notification-body')
  if (onNotification || (onInteractive && !onGrabber)) return
  if (notificationBody && notificationBody.scrollTop > 0) return

  closePull = {
    pointerId: event.pointerId,
    startY: event.clientY,
    currentY: event.clientY,
    lastY: event.clientY,
    lastTime: performance.now(),
    velocityY: 0,
  }
  closePullActive.value = true
  closePullProgress.value = 0
  event.currentTarget?.setPointerCapture?.(event.pointerId)
}

function onClosePullMove(event) {
  if (!closePull || event.pointerId !== closePull.pointerId) return
  updateClosePullMotion(event.clientY)
  const upwardDistance = Math.max(0, closePull.startY - closePull.currentY)
  closePullProgress.value = Math.min(1, upwardDistance / Math.max(1, window.innerHeight))
  if (upwardDistance > 0) event.preventDefault()
}

function updateClosePullMotion(clientY) {
  if (!closePull) return
  const now = performance.now()
  const elapsed = Math.max(1, now - closePull.lastTime)
  const instantVelocity = (clientY - closePull.lastY) / elapsed
  closePull.velocityY = closePull.velocityY * 0.55 + instantVelocity * 0.45
  closePull.currentY = clientY
  closePull.lastY = clientY
  closePull.lastTime = now
}

function shouldCloseAfterPull(upwardDistance) {
  if (!closePull) return false
  const farEnough = closePullProgress.value >= 0.45
  const fastUpwardFlick = upwardDistance >= 20 && closePull.velocityY <= -0.65
  return farEnough || fastUpwardFlick
}

function finishClosePull(shouldClose) {
  if (shouldClose) close()
  closePullActive.value = false
  closePullProgress.value = 0
  closePull = null
}

function onClosePullEnd(event) {
  if (!closePull || event.pointerId !== closePull.pointerId) return
  const upwardDistance = closePull.startY - closePull.currentY
  finishClosePull(shouldCloseAfterPull(upwardDistance))
}

function onClosePullCancel(event) {
  if (!closePull || event.pointerId !== closePull.pointerId) return
  finishClosePull(false)
}

function touchById(touches, id) {
  return Array.from(touches ?? []).find((touch) => touch.identifier === id) ?? null
}

function onCloseTouchStart(event) {
  if (closePull) return
  const touch = event.changedTouches?.[0]
  if (!touch) return
  closePull = {
    pointerId: `touch-${touch.identifier}`,
    touchId: touch.identifier,
    startY: touch.clientY,
    currentY: touch.clientY,
    lastY: touch.clientY,
    lastTime: performance.now(),
    velocityY: 0,
  }
  closePullActive.value = true
  closePullProgress.value = 0
}

function onCloseTouchMove(event) {
  if (!closePull || closePull.touchId == null) return
  const touch = touchById(event.touches, closePull.touchId)
  if (!touch) return
  updateClosePullMotion(touch.clientY)
  const upwardDistance = Math.max(0, closePull.startY - touch.clientY)
  closePullProgress.value = Math.min(1, upwardDistance / Math.max(1, window.innerHeight))
}

function onCloseTouchEnd(event) {
  if (!closePull || closePull.touchId == null) return
  const touch = touchById(event.changedTouches, closePull.touchId)
  const currentY = touch?.clientY ?? closePull.currentY
  if (touch) updateClosePullMotion(currentY)
  const upwardDistance = closePull.startY - currentY
  finishClosePull(shouldCloseAfterPull(upwardDistance))
}

function onCloseTouchCancel() {
  if (!closePull || closePull.touchId == null) return
  finishClosePull(false)
}

function refresh() {
  void center?.loadInbox?.({ reset: true, announce: false })
}

function scrollToNotifications() {
  notificationSection.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
}

function onBrightnessInput(event) {
  emit('brightness-change', Number(event.target?.value) || 1)
}

function dismiss(id) {
  center?.dismissItem?.(id)
}

function clearNotificationHold() {
  if (notificationHoldTimer) {
    window.clearTimeout(notificationHoldTimer)
    notificationHoldTimer = null
  }
}

function resetNotificationSwipe() {
  clearNotificationHold()
  notificationSwipe.value = null
}

function onNotificationPointerDown(id, event) {
  if (event.button !== 0) return
  resetNotificationSwipe()
  notificationSwipe.value = {
    id,
    pointerId: event.pointerId,
    startX: event.clientX,
    startY: event.clientY,
    currentX: 0,
    ready: false,
    target: event.currentTarget,
  }
  notificationHoldTimer = window.setTimeout(() => {
    const swipe = notificationSwipe.value
    if (!swipe || swipe.id !== id || swipe.pointerId !== event.pointerId) return
    swipe.ready = true
    swipe.target?.setPointerCapture?.(swipe.pointerId)
  }, 180)
}

function onNotificationPointerMove(id, event) {
  const swipe = notificationSwipe.value
  if (!swipe || swipe.id !== id || swipe.pointerId !== event.pointerId || !swipe.ready) return
  const dx = Math.max(0, event.clientX - swipe.startX)
  const dy = Math.abs(event.clientY - swipe.startY)
  if (dx <= dy) return
  event.preventDefault()
  swipe.currentX = dx
}

function onNotificationPointerEnd(id, event) {
  const swipe = notificationSwipe.value
  if (!swipe || swipe.id !== id || swipe.pointerId !== event.pointerId) return
  const threshold = Math.max(72, (swipe.target?.clientWidth || 0) * 0.28)
  if (swipe.ready && swipe.currentX >= threshold) dismiss(id)
  resetNotificationSwipe()
}

function onNotificationPointerCancel(id, event) {
  const swipe = notificationSwipe.value
  if (!swipe || swipe.id !== id || swipe.pointerId !== event.pointerId) return
  resetNotificationSwipe()
}

function notificationSwipeStyle(id) {
  const swipe = notificationSwipe.value
  if (!swipe || swipe.id !== id || !swipe.ready) return undefined
  const width = swipe.target?.clientWidth || 320
  const progress = Math.min(1, swipe.currentX / width)
  return {
    transform: `translateX(${swipe.currentX}px)`,
    opacity: 1 - progress * 0.7,
  }
}

function readAll() {
  void center?.readAll?.()
}

function loadMore() {
  void center?.loadMore?.()
}

function formatWhen(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString([], {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return String(iso)
  }
}
</script>
