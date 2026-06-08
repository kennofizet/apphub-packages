<template>
  <div v-if="open" class="apphub-start" @click.stop="emit('close')">
    <div class="apphub-start__panel" @click.stop>
      <aside class="apphub-start__left">
        <div class="apphub-start__search-wrap">
          <svg class="apphub-start__search-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"
            />
          </svg>
          <input
            v-model="query"
            type="search"
            class="apphub-start__search"
            :placeholder="searchPlaceholder"
            @keydown.esc="emit('close')"
          />
        </div>

        <p class="apphub-start__section-label">{{ pinnedLabel }}</p>
        <ul class="apphub-start__list">
          <li v-for="app in filteredApps" :key="app.id">
            <button type="button" class="apphub-start__list-item" @click="onOpen(app)">
              <span class="apphub-start__list-icon-wrap">
                <span class="apphub-start__list-icon">{{ app.icon }}</span>
              </span>
              <span class="apphub-start__list-name">{{ app.name }}</span>
            </button>
          </li>
          <li v-if="!filteredApps.length" class="apphub-start__empty">{{ emptyLabel }}</li>
        </ul>

        <div class="apphub-start__footer">
          <AppHubDesktopSettings
            :snap-to-grid="snapToGrid"
            :snap-label="snapLabel"
            :theme="theme"
            :theme-label="themeLabel"
            :show-theme-toggle="showThemeToggle"
            @update:snap-to-grid="emit('update:snapToGrid', $event)"
            @update:theme="emit('update:theme', $event)"
          />
        </div>
      </aside>

      <section class="apphub-start__right">
        <button
          v-if="featuredApp"
          type="button"
          class="apphub-start__hero"
          @click="onOpen(featuredApp)"
        >
          <span class="apphub-start__hero-icon">{{ featuredApp.icon }}</span>
          <span class="apphub-start__hero-body">
            <strong class="apphub-start__hero-title">{{ featuredApp.name }}</strong>
            <span class="apphub-start__hero-hint">{{ featuredApp.hint }}</span>
          </span>
          <span class="apphub-start__hero-arrow" aria-hidden="true">›</span>
        </button>

        <button
          v-if="guideApp"
          type="button"
          class="apphub-start__hero apphub-start__hero--guide"
          @click="onOpen(guideApp)"
        >
          <span class="apphub-start__hero-icon">{{ guideApp.icon }}</span>
          <span class="apphub-start__hero-body">
            <strong class="apphub-start__hero-title">{{ guideApp.name }}</strong>
            <span class="apphub-start__hero-hint">{{ guideApp.hint }}</span>
          </span>
          <span class="apphub-start__hero-arrow" aria-hidden="true">›</span>
        </button>

        <p v-if="suggestedApps.length" class="apphub-start__section-label">{{ suggestedLabel }}</p>
        <div v-if="suggestedApps.length" class="apphub-start__grid">
          <button
            v-for="app in suggestedApps"
            :key="app.id"
            type="button"
            class="apphub-start__app-tile"
            @click="onOpen(app)"
          >
            <span class="apphub-start__app-tile-icon">{{ app.icon }}</span>
            <span class="apphub-start__app-tile-name">{{ app.name }}</span>
          </button>
        </div>
        <p v-else-if="!featuredApp" class="apphub-start__empty apphub-start__empty--right">
          {{ emptyLabel }}
        </p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppHubDesktopSettings from './AppHubDesktopSettings.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  apps: { type: Array, default: () => [] },
  searchPlaceholder: { type: String, default: '' },
  pinnedLabel: { type: String, default: '' },
  suggestedLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
  snapToGrid: { type: Boolean, default: true },
  snapLabel: { type: String, default: '' },
  theme: { type: String, default: 'dark' },
  themeLabel: { type: String, default: '' },
  showThemeToggle: { type: Boolean, default: true },
})

const emit = defineEmits(['close', 'open-app', 'update:snapToGrid', 'update:theme'])

const query = ref('')

watch(() => props.open, (isOpen) => {
  if (!isOpen) query.value = ''
})

const filteredApps = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = props.apps ?? []
  if (!q) return list
  return list.filter((app) => app.name?.toLowerCase().includes(q))
})

const featuredApp = computed(() =>
  (props.apps ?? []).find((a) => a.builtin && a.module === 'app-store') ?? null,
)

const guideApp = computed(() =>
  (props.apps ?? []).find((a) => a.builtin && a.module === 'guide') ?? null,
)

const suggestedApps = computed(() => (props.apps ?? []).filter((a) => !a.builtin).slice(0, 8))

function onOpen(app) {
  emit('open-app', app)
  emit('close')
}
</script>
