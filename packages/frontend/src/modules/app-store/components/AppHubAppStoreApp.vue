<template>
  <div class="apphub-store">
    <header class="apphub-store__header">
      <h2 class="apphub-store__title">{{ labels.app_store_title }}</h2>
      <input
        v-model="store.state.search"
        type="search"
        class="apphub-store__search"
        :placeholder="labels.app_store_search"
      />
    </header>
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
</template>

<script setup>
import { computed, inject } from 'vue'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { useAppStore } from '../composables/useAppStore.js'

const props = defineProps({
  onInstalled: { type: Function, default: null },
})

const store = useAppStore()
const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))

const labels = computed(() => ({
  app_store_title: t('app_store_title', lang.value),
  app_store_search: t('app_store_search', lang.value),
  app_store_install: t('app_store_install', lang.value),
  app_store_empty: t('app_store_empty', lang.value),
}))

async function onInstall(app) {
  store.installApp(app.slug)
  await props.onInstalled?.(app)
}
</script>
