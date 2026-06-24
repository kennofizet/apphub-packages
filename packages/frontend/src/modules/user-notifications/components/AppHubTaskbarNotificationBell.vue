<template>
  <button
    type="button"
    class="apphub-taskbar-notif"
    :class="{ 'apphub-taskbar-notif--pulse': center?.state.bellPulse }"
    :title="labels.open"
    :aria-label="labels.open"
    @click="center?.toggleDrawer?.()"
  >
    <span class="apphub-taskbar-notif__icon" aria-hidden="true">🔔</span>
    <span
      v-if="unreadCount > 0"
      class="apphub-taskbar-notif__badge"
      :class="{ 'apphub-taskbar-notif__badge--pop': badgePop }"
      aria-hidden="true"
    >
      {{ badgeLabel }}
    </span>
  </button>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue'
import { USER_NOTIFICATION_CENTER_KEY } from '../composables/createUserNotificationCenter.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'

const center = inject(USER_NOTIFICATION_CENTER_KEY, null)
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  open: t('inbox_notif_open', lang.value),
}))

const unreadCount = computed(() => center?.state.unreadCount ?? 0)
const badgePop = ref(false)

watch(unreadCount, (next, prev) => {
  if (next > 0 && next !== prev) {
    badgePop.value = true
    window.setTimeout(() => {
      badgePop.value = false
    }, 420)
  }
})

const badgeLabel = computed(() => {
  const n = unreadCount.value
  if (n > 99) return '99+'
  return String(n)
})
</script>
