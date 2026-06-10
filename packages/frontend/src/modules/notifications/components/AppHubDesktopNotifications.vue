<template>
  <div
    class="apphub-notifications"
    role="region"
    :aria-label="labels.region"
    aria-live="polite"
    aria-relevant="additions"
  >
    <TransitionGroup name="apphub-notifications__item">
      <article
        v-for="item in items"
        :key="item.id"
        class="apphub-notifications__toast"
        :class="`apphub-notifications__toast--${item.type}`"
      >
        <div class="apphub-notifications__accent" aria-hidden="true" />
        <div class="apphub-notifications__body">
          <header v-if="item.title" class="apphub-notifications__title">{{ item.title }}</header>
          <p class="apphub-notifications__message">{{ item.message || item.title }}</p>
        </div>
        <button
          type="button"
          class="apphub-notifications__close"
          :aria-label="labels.close"
          @click="dismiss(item.id)"
        >
          ×
        </button>
      </article>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { DESKTOP_NOTIFICATIONS_KEY } from '../composables/createDesktopNotifications.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'

const notifications = inject(DESKTOP_NOTIFICATIONS_KEY, null)
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  region: t('notif_region', lang.value),
  close: t('notif_close', lang.value),
}))

const items = computed(() => notifications?.state?.items ?? [])

function dismiss(id) {
  notifications?.dismiss?.(id)
}
</script>
