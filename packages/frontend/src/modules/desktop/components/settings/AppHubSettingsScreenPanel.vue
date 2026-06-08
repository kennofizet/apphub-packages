<template>
  <div class="apphub-settings-panel">
    <h3 class="apphub-settings-panel__title">{{ labels.title }}</h3>
    <p class="apphub-settings-panel__hint">{{ labels.hint }}</p>

    <AppHubDesktopSettings
      :snap-to-grid="hub.desktopSettings.snapToGrid"
      :snap-label="labels.snap_grid"
      :theme="activeTheme"
      :theme-label="labels.light_mode"
      :show-theme-toggle="showThemeToggle"
      @update:snap-to-grid="hub.setSnapToGrid"
      @update:theme="hub.setTheme"
    />
  </div>
</template>

<script setup>
import { computed, inject, unref } from 'vue'
import { t } from '../../../../i18n/index.js'
import { resolveLang } from '../../../../i18n/resolveLang.js'
import { useDesktopHubSettings } from '../../composables/useDesktopHubSettings.js'
import AppHubDesktopSettings from '../AppHubDesktopSettings.vue'

const hub = useDesktopHubSettings()
const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))
const activeTheme = computed(() => unref(hub.activeTheme) ?? 'dark')
const showThemeToggle = computed(() => unref(hub.showThemeToggle) !== false)

const labels = computed(() => ({
  title: t('hub_settings_screen_title', lang.value),
  hint: t('hub_settings_screen_hint', lang.value),
  snap_grid: t('settings_snap_grid', lang.value),
  light_mode: t('settings_light_mode', lang.value),
}))
</script>
