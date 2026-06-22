<template>
  <div class="apphub-settings-panel">
    <h3 class="apphub-settings-panel__title">{{ labels.title }}</h3>
    <p class="apphub-settings-panel__hint">{{ labels.hint }}</p>

    <p class="apphub-settings-panel__callout">{{ labels.browser_note }}</p>

    <label class="apphub-settings-panel__row">
      <input
        type="checkbox"
        :checked="hub.keyboardSettings.enabled"
        @change="onEnabledChange"
      />
      <span>{{ labels.enabled }}</span>
    </label>

    <p class="apphub-settings-panel__note">{{ labels.modifier_note }}</p>

    <table class="apphub-settings-panel__table">
      <thead>
        <tr>
          <th>{{ labels.col_action }}</th>
          <th>{{ labels.col_keys }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in shortcutRows" :key="row.id">
          <td>{{ row.label }}</td>
          <td><kbd class="apphub-settings-panel__kbd">{{ row.keys }}</kbd></td>
        </tr>
      </tbody>
    </table>

    <p class="apphub-settings-panel__note">{{ labels.titlebar_note }}</p>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../../i18n/index.js'
import { resolveLang } from '../../../../i18n/resolveLang.js'
import { useDesktopHubSettings } from '../../composables/useDesktopHubSettings.js'

const hub = useDesktopHubSettings()
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('hub_settings_keyboard_title', lang.value),
  hint: t('hub_settings_keyboard_hint', lang.value),
  browser_note: t('hub_settings_keyboard_browser_note', lang.value),
  enabled: t('hub_settings_keyboard_enabled', lang.value),
  modifier_note: t('hub_settings_keyboard_modifier_note', lang.value),
  col_action: t('hub_settings_keyboard_col_action', lang.value),
  col_keys: t('hub_settings_keyboard_col_keys', lang.value),
  titlebar_note: t('hub_settings_keyboard_titlebar_note', lang.value),
  snap_left: t('hub_settings_keyboard_snap_left', lang.value),
  snap_right: t('hub_settings_keyboard_snap_right', lang.value),
  snap_up: t('hub_settings_keyboard_snap_up', lang.value),
  snap_down: t('hub_settings_keyboard_snap_down', lang.value),
  modifier_ctrl_alt: t('hub_settings_keyboard_modifier_ctrl_alt', lang.value),
}))

const shortcutRows = computed(() => {
  const mod = labels.value.modifier_ctrl_alt
  const l = lang.value
  return [
    { id: 'left', label: labels.value.snap_left, keys: t('hub_settings_keyboard_key_left', l, { mod }) },
    { id: 'right', label: labels.value.snap_right, keys: t('hub_settings_keyboard_key_right', l, { mod }) },
    { id: 'up', label: labels.value.snap_up, keys: t('hub_settings_keyboard_key_up', l, { mod }) },
    { id: 'down', label: labels.value.snap_down, keys: t('hub_settings_keyboard_key_down', l, { mod }) },
  ]
})

function persist() {
  hub.saveKeyboardSettings?.()
}

function onEnabledChange(event) {
  hub.keyboardSettings.enabled = event.target.checked
  persist()
}
</script>
