<template>
  <div class="apphub-settings-panel">
    <h3 class="apphub-settings-panel__title">{{ labels.page_title }}</h3>
    <p class="apphub-settings-panel__hint">{{ labels.page_hint }}</p>

    <p class="apphub-settings-panel__section-label">{{ labels.pins_section }}</p>
    <p v-if="!pinnedRows.length" class="apphub-settings-panel__msg">{{ labels.pins_empty }}</p>

    <ul v-else class="apphub-settings-panel__pin-list">
      <li v-for="row in pinnedRows" :key="`pin-${row.id}`" class="apphub-settings-panel__pin-item">
        <span class="apphub-settings-panel__pin-icon" aria-hidden="true">{{ row.icon }}</span>
        <span class="apphub-settings-panel__pin-name">{{ row.name }}</span>
        <label class="apphub-settings-panel__pin-toggle">
          <input
            type="checkbox"
            :checked="row.visible"
            @change="onPinVisibleChange(row.id, $event.target.checked)"
          />
          <span>{{ labels.pins_show }}</span>
        </label>
      </li>
    </ul>

    <p class="apphub-settings-panel__section-label apphub-settings-panel__section-label--spaced">{{ labels.favorites_section }}</p>
    <p v-if="!favoriteRows.length" class="apphub-settings-panel__msg">{{ labels.favorites_empty }}</p>

    <ul v-else class="apphub-settings-panel__pin-list">
      <li v-for="row in favoriteRows" :key="`fav-${row.id}`" class="apphub-settings-panel__pin-item">
        <span class="apphub-settings-panel__pin-icon" aria-hidden="true">{{ row.icon }}</span>
        <span class="apphub-settings-panel__pin-name">{{ row.name }}</span>
        <label class="apphub-settings-panel__pin-toggle">
          <input
            type="checkbox"
            :checked="row.visible"
            @change="onFavoriteVisibleChange(row.id, $event.target.checked)"
          />
          <span>{{ labels.favorites_show }}</span>
        </label>
      </li>
    </ul>

    <p class="apphub-settings-panel__note">{{ labels.page_note }}</p>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../../i18n/index.js'
import { resolveLang } from '../../../../i18n/resolveLang.js'
import {
  useDesktopHubSettings,
  useHubFavoriteRows,
  useHubPinnedRows,
} from '../../composables/useDesktopHubSettings.js'

const hub = useDesktopHubSettings()
const pinnedRows = useHubPinnedRows(hub)
const favoriteRows = useHubFavoriteRows(hub)
const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))

const labels = computed(() => ({
  page_title: t('hub_settings_pin_favorite_title', lang.value),
  page_hint: t('hub_settings_pin_favorite_hint', lang.value),
  pins_section: t('hub_settings_pins_section', lang.value),
  pins_empty: t('hub_settings_start_empty', lang.value),
  pins_show: t('hub_settings_start_show', lang.value),
  favorites_section: t('start_menu_favorites', lang.value),
  favorites_empty: t('hub_settings_start_favorites_empty', lang.value),
  favorites_show: t('hub_settings_start_favorites_show', lang.value),
  page_note: t('hub_settings_pin_favorite_note', lang.value),
}))

function onPinVisibleChange(appId, visible) {
  hub.setPinVisible?.(appId, visible)
}

function onFavoriteVisibleChange(appId, visible) {
  hub.setFavoriteVisible?.(appId, visible)
}
</script>

<style scoped>
.apphub-settings-panel__section-label {
  margin: 20px 0 10px;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ah-text-muted, rgba(255, 255, 255, 0.38));
}

.apphub-settings-panel__section-label--spaced {
  margin-top: 28px;
}
</style>
