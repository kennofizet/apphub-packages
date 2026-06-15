<template>
  <div class="apphub-origin-block" role="alert">
    <div class="apphub-origin-block__panel">
      <span class="apphub-origin-block__icon" aria-hidden="true">🛡️</span>
      <h1 class="apphub-origin-block__title">{{ labels.title }}</h1>
      <p class="apphub-origin-block__message">{{ message }}</p>
      <p class="apphub-origin-block__hint">{{ labels.hint }}</p>
      <dl v-if="showOrigins" class="apphub-origin-block__dl">
        <div class="apphub-origin-block__row">
          <dt>{{ labels.current_origin }}</dt>
          <dd>{{ currentOrigin }}</dd>
        </div>
        <div v-if="expectedHubOrigin" class="apphub-origin-block__row">
          <dt>{{ labels.expected_hub_origin }}</dt>
          <dd>{{ expectedHubOrigin }}</dd>
        </div>
        <div v-if="expectedRuntimeOrigin" class="apphub-origin-block__row">
          <dt>{{ labels.expected_runtime_origin }}</dt>
          <dd>{{ expectedRuntimeOrigin }}</dd>
        </div>
        <div v-if="parentOrigin" class="apphub-origin-block__row">
          <dt>{{ labels.parent_origin }}</dt>
          <dd>{{ parentOrigin }}</dd>
        </div>
      </dl>

      <div v-if="devOriginVisible" class="apphub-origin-block__dev">
        <p class="apphub-origin-block__dev-title">{{ devOriginLabels.title }}</p>
        <p class="apphub-origin-block__dev-status">
          {{ devFriendlyOn ? devOriginLabels.status_relaxed : devOriginLabels.status_strict }}
        </p>
        <button type="button" class="apphub-origin-block__dev-btn" @click="toggleDevOrigin">
          {{ devFriendlyOn ? devOriginLabels.action_enable_strict : devOriginLabels.action_enable_relaxed }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { useDevOriginToggle } from '../../../composables/useDevOriginToggle.js'
import {
  ORIGIN_UNSAFE_DIRECT_PRODUCT_MOUNT,
  ORIGIN_UNSAFE_NOT_CONFIGURED,
  ORIGIN_UNSAFE_RUNTIME_NOT_CONFIGURED,
  ORIGIN_UNSAFE_RUNTIME_SAME_ORIGIN,
  ORIGIN_UNSAFE_SAME_ORIGIN_EMBED,
  ORIGIN_UNSAFE_WRONG_ORIGIN,
} from '../../../utils/originSafety.js'

const props = defineProps({
  reason: { type: String, default: null },
  parentOrigin: { type: String, default: null },
  expectedHubOrigin: { type: String, default: null },
  expectedRuntimeOrigin: { type: String, default: null },
  labels: { type: Object, required: true },
})

const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))
const { visible: devOriginVisible, devFriendlyOn, toggle: toggleDevOrigin } = useDevOriginToggle()

const devOriginLabels = computed(() => ({
  title: t('dev_origin_bar_title', lang.value),
  status_relaxed: t('dev_origin_status_relaxed', lang.value),
  status_strict: t('dev_origin_status_strict', lang.value),
  action_enable_strict: t('dev_origin_action_strict', lang.value),
  action_enable_relaxed: t('dev_origin_action_relaxed', lang.value),
}))

const currentOrigin = computed(() => {
  if (typeof window === 'undefined') return ''
  return window.location.origin
})

const message = computed(() => {
  if (props.reason === ORIGIN_UNSAFE_SAME_ORIGIN_EMBED) {
    return props.labels.same_origin_embed
  }
  if (props.reason === ORIGIN_UNSAFE_DIRECT_PRODUCT_MOUNT || props.reason === ORIGIN_UNSAFE_NOT_CONFIGURED) {
    return props.labels.not_configured
  }
  if (props.reason === ORIGIN_UNSAFE_WRONG_ORIGIN) {
    return props.labels.wrong_origin
  }
  if (props.reason === ORIGIN_UNSAFE_RUNTIME_NOT_CONFIGURED) {
    return props.labels.runtime_not_configured
  }
  if (props.reason === ORIGIN_UNSAFE_RUNTIME_SAME_ORIGIN) {
    return props.labels.runtime_same_origin
  }
  return props.labels.generic
})

const showOrigins = computed(() =>
  Boolean(
    currentOrigin.value
    || props.parentOrigin
    || props.expectedHubOrigin
    || props.expectedRuntimeOrigin,
  ),
)
</script>
