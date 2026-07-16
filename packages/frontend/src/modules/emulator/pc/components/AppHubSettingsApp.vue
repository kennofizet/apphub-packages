<template>
  <div class="apphub-settings-app">
    <header class="apphub-settings-app__header">
      <h2 class="apphub-settings-app__title">{{ labels.title }}</h2>
      <p class="apphub-settings-app__subtitle">{{ labels.subtitle }}</p>
    </header>

    <div class="apphub-settings-app__layout">
      <nav class="apphub-settings-app__nav" :aria-label="labels.nav_label">
        <button
          v-for="item in menuItems"
          :key="item.id"
          type="button"
          class="apphub-settings-app__nav-item"
          :class="{ 'apphub-settings-app__nav-item--active': section === item.id }"
          @click="section = item.id"
        >
          <span class="apphub-settings-app__nav-icon" aria-hidden="true">{{ item.icon }}</span>
          <span class="apphub-settings-app__nav-label">{{ item.label }}</span>
        </button>
      </nav>

      <div class="apphub-settings-app__body">
        <AppHubSettingsScreenPanel v-if="section === 'screen'" />
        <AppHubSettingsKeyboardPanel v-else-if="section === 'keyboard'" />
        <AppHubSettingsStartMenuPanel v-else-if="section === 'start'" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { t } from '../../../../i18n/index.js'
import { resolveLang } from '../../../../i18n/resolveLang.js'
import AppHubSettingsKeyboardPanel from './settings/AppHubSettingsKeyboardPanel.vue'
import AppHubSettingsScreenPanel from './settings/AppHubSettingsScreenPanel.vue'
import AppHubSettingsStartMenuPanel from './settings/AppHubSettingsStartMenuPanel.vue'

const section = ref('screen')
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('hub_settings_app_title', lang.value),
  subtitle: t('hub_settings_app_subtitle', lang.value),
  nav_label: t('hub_settings_app_nav', lang.value),
  menu_screen: t('hub_settings_menu_screen', lang.value),
  menu_keyboard: t('hub_settings_menu_keyboard', lang.value),
  menu_pin_favorite: t('hub_settings_menu_pin_favorite', lang.value),
}))

const menuItems = computed(() => [
  { id: 'screen', label: labels.value.menu_screen, icon: '🖥' },
  { id: 'start', label: labels.value.menu_pin_favorite, icon: '📌' },
  { id: 'keyboard', label: labels.value.menu_keyboard, icon: '⌨' },
])
</script>

<style scoped>
.apphub-settings-app {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  color: var(--ah-text-secondary, #cbd5e1);
  background: var(--ah-surface, #1e293b);
}

.apphub-settings-app__header {
  flex: 0 0 auto;
  padding: 20px 24px 12px;
  border-bottom: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-settings-app__title {
  margin: 0;
  font-size: 1.35rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-settings-app__subtitle {
  margin: 6px 0 0;
  font-size: 0.88rem;
  color: var(--ah-text-muted, #94a3b8);
}

.apphub-settings-app__layout {
  flex: 1;
  min-height: 0;
  display: flex;
}

.apphub-settings-app__nav {
  flex: 0 0 200px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  border-right: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
  overflow-y: auto;
}

.apphub-settings-app__nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  color: var(--ah-text-muted, #94a3b8);
  font-size: 0.875rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.apphub-settings-app__nav-item:hover {
  background: var(--ah-hover, rgba(255, 255, 255, 0.08));
  color: var(--ah-text, #f0f4fc);
}

.apphub-settings-app__nav-item--active {
  background: var(--ah-hover-strong, rgba(255, 255, 255, 0.12));
  border-color: var(--ah-border, rgba(255, 255, 255, 0.1));
  color: var(--ah-text, #f0f4fc);
}

.apphub-settings-app__nav-icon {
  flex-shrink: 0;
  font-size: 1.1rem;
}

.apphub-settings-app__body {
  flex: 1;
  min-width: 0;
  overflow-y: auto;
  padding: 16px 24px 24px;
}

@media (max-width: 520px) {
  .apphub-settings-app__layout {
    flex-direction: column;
  }

  .apphub-settings-app__nav {
    flex: 0 0 auto;
    flex-direction: row;
    flex-wrap: wrap;
    border-right: none;
    border-bottom: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
  }

  .apphub-settings-app__nav-item {
    flex: 1 1 auto;
    min-width: 140px;
  }
}
</style>

<style>
.apphub-settings-panel__title {
  margin: 0 0 8px;
  font-size: 1rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-settings-panel__hint {
  margin: 0 0 16px;
  font-size: 0.85rem;
  color: var(--ah-text-muted, #94a3b8);
  line-height: 1.5;
}

.apphub-settings-panel .apphub-desktop-settings {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.apphub-settings-panel .apphub-desktop-settings__row {
  padding: 10px 12px;
  border-radius: 6px;
}

.apphub-settings-panel__row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border-radius: 6px;
  cursor: pointer;
}

.apphub-settings-panel__row:hover {
  background: var(--ah-hover, rgba(255, 255, 255, 0.06));
}

.apphub-settings-panel__field {
  margin-bottom: 20px;
}

.apphub-settings-panel__label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.85rem;
  color: var(--ah-text-muted, #94a3b8);
}

.apphub-settings-panel__select {
  width: 100%;
  max-width: 320px;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid var(--ah-border, #475569);
  background: var(--ah-surface-raised, #0f172a);
  color: var(--ah-text, #e2e8f0);
}

.apphub-settings-panel__table {
  width: 100%;
  max-width: 520px;
  border-collapse: collapse;
  font-size: 0.875rem;
  margin-bottom: 16px;
}

.apphub-settings-panel__table th,
.apphub-settings-panel__table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-settings-panel__table th {
  color: var(--ah-text-muted, #94a3b8);
  font-weight: 600;
}

.apphub-settings-panel__kbd {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  background: var(--ah-hover, rgba(0, 0, 0, 0.25));
  border: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.1));
  font-family: inherit;
  font-size: 0.8rem;
}

.apphub-settings-panel__note {
  margin: 8px 0 0;
  font-size: 0.8rem;
  color: var(--ah-text-muted, #94a3b8);
  line-height: 1.5;
}

.apphub-settings-panel__callout {
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: 6px;
  border: 1px solid rgba(251, 191, 36, 0.35);
  background: rgba(251, 191, 36, 0.1);
  font-size: 0.85rem;
  color: var(--ah-text-secondary, #cbd5e1);
  line-height: 1.5;
}

.apphub-settings-panel__msg {
  color: var(--ah-text-muted, #94a3b8);
  font-size: 0.875rem;
}

.apphub-settings-panel__pin-list {
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 520px;
}

.apphub-settings-panel__pin-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--ah-hover, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-settings-panel__pin-icon {
  flex-shrink: 0;
  font-size: 1.25rem;
}

.apphub-settings-panel__pin-name {
  flex: 1;
  min-width: 0;
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.apphub-settings-panel__pin-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  color: var(--ah-text-muted, #94a3b8);
  cursor: pointer;
  flex-shrink: 0;
}
</style>
