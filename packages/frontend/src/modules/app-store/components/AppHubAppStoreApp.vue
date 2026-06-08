<template>
  <div class="apphub-store">
    <header class="apphub-store__header">
      <h2 class="apphub-store__title">
        {{ settingsOpen ? labels.settings_title : labels.app_store_title }}
      </h2>
      <div class="apphub-store__toolbar">
        <button
          type="button"
          class="apphub-store__settings-btn"
          :class="{ 'apphub-store__settings-btn--active': settingsOpen }"
          :title="settingsOpen ? labels.settings_close : labels.settings"
          @click="settingsOpen = !settingsOpen"
        >
          <svg v-if="settingsOpen" class="apphub-store__settings-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"
            />
          </svg>
          <svg v-else class="apphub-store__settings-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"
            />
          </svg>
        </button>
      </div>
    </header>

    <div class="apphub-store__content">
      <AppHubAppStoreSettingsPanel v-if="settingsOpen" />
      <div v-else class="apphub-store__panel">
        <input
          v-model="store.state.search"
          type="search"
          class="apphub-store__search"
          :placeholder="labels.app_store_search"
        />
        <div v-if="!store.filteredApps.length" class="apphub-store__empty">
          {{ labels.app_store_empty }}
        </div>
        <ul v-else class="apphub-store__grid">
          <li v-for="app in store.filteredApps" :key="app.slug" class="apphub-store__card">
            <span class="apphub-store__icon">{{ app.icon }}</span>
            <div class="apphub-store__meta">
              <strong>{{ app.name }}</strong>
              <p>{{ app.description }}</p>
            </div>
            <button
              v-if="!store.isInstalled(app.slug)"
              type="button"
              class="apphub-store__btn"
              @click="onInstall(app)"
            >
              {{ labels.app_store_install }}
            </button>
            <span v-else class="apphub-store__installed">✓</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { useAppStore } from '../composables/useAppStore.js'
import AppHubAppStoreSettingsPanel from './AppHubAppStoreSettingsPanel.vue'

const props = defineProps({
  onInstalled: { type: Function, default: null },
})

const settingsOpen = ref(false)
const store = useAppStore()
const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))

const labels = computed(() => ({
  app_store_title: t('app_store_title', lang.value),
  app_store_search: t('app_store_search', lang.value),
  app_store_install: t('app_store_install', lang.value),
  app_store_empty: t('app_store_empty', lang.value),
  settings: t('app_store_settings_btn', lang.value),
  settings_title: t('app_store_settings_title', lang.value),
  settings_close: t('app_store_settings_close', lang.value),
}))

async function onInstall(app) {
  store.installApp(app.slug)
  await props.onInstalled?.(app)
}
</script>
