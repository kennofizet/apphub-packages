<template>
  <div class="apphub-origin-loading" role="status" aria-live="polite">
    <div class="apphub-origin-loading__panel">
      <div class="apphub-origin-loading__spinner" aria-hidden="true" />
      <p class="apphub-origin-loading__title">{{ labels.title }}</p>
      <p class="apphub-origin-loading__hint">{{ labels.hint }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'

const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('origin_loading_title', lang.value),
  hint: t('origin_loading_hint', lang.value),
}))
</script>
