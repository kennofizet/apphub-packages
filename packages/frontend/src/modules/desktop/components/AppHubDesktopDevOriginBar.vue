<template>
  <div
    v-if="visible"
    class="apphub-dev-origin-bar"
    :class="`apphub-dev-origin-bar--${placement}`"
    role="region"
    :aria-label="labels.title"
  >
    <span class="apphub-dev-origin-bar__badge">DEV</span>
    <div v-if="placement === 'corner'" class="apphub-dev-origin-bar__text">
      <strong class="apphub-dev-origin-bar__title">{{ labels.title }}</strong>
      <span class="apphub-dev-origin-bar__status">{{ statusLabel }}</span>
    </div>
    <span v-else class="apphub-dev-origin-bar__status apphub-dev-origin-bar__status--inline">
      {{ statusLabel }}
    </span>
    <button
      type="button"
      class="apphub-dev-origin-bar__btn"
      :title="labels.title"
      @click="toggle"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { useDevOriginToggle } from '../../../composables/useDevOriginToggle.js'

defineProps({
  placement: { type: String, default: 'corner' },
})

const { visible, devFriendlyOn, toggle } = useDevOriginToggle()
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('dev_origin_bar_title', lang.value),
  status_relaxed: t('dev_origin_status_relaxed', lang.value),
  status_strict: t('dev_origin_status_strict', lang.value),
  action_enable_strict: t('dev_origin_action_strict', lang.value),
  action_enable_relaxed: t('dev_origin_action_relaxed', lang.value),
}))

const statusLabel = computed(() =>
  devFriendlyOn.value ? labels.value.status_relaxed : labels.value.status_strict,
)

const actionLabel = computed(() =>
  devFriendlyOn.value ? labels.value.action_enable_strict : labels.value.action_enable_relaxed,
)
</script>
