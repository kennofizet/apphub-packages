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

        <div class="apphub-start__lists">
          <template v-if="isSearching">
            <p class="apphub-start__section-label">{{ searchResultsLabel }}</p>
            <ul class="apphub-start__list">
              <li v-for="app in searchResults" :key="app.id">
                <button type="button" class="apphub-start__list-item" @click="onOpen(app)">
                  <span class="apphub-start__list-icon-wrap">
                    <span class="apphub-start__list-icon">{{ app.icon }}</span>
                  </span>
                  <span class="apphub-start__list-name">{{ app.name }}</span>
                </button>
              </li>
              <li v-if="!searchResults.length" class="apphub-start__empty">{{ emptyLabel }}</li>
            </ul>
          </template>

          <template v-else>
            <p class="apphub-start__section-label">{{ favoritesLabel }}</p>
            <ul class="apphub-start__list">
              <li v-for="app in favoriteApps" :key="`fav-${app.id}`">
                <button type="button" class="apphub-start__list-item" @click="onOpen(app)">
                  <span class="apphub-start__list-icon-wrap">
                    <span class="apphub-start__list-icon">{{ app.icon }}</span>
                  </span>
                  <span class="apphub-start__list-name">{{ app.name }}</span>
                </button>
              </li>
              <li v-if="!favoriteApps.length" class="apphub-start__empty">{{ emptyLabel }}</li>
            </ul>

            <p class="apphub-start__section-label apphub-start__section-label--spaced">{{ recentLabel }}</p>
            <ul class="apphub-start__list">
              <li v-for="app in recentApps" :key="`recent-${app.id}`">
                <button type="button" class="apphub-start__list-item" @click="onOpen(app)">
                  <span class="apphub-start__list-icon-wrap">
                    <span class="apphub-start__list-icon">{{ app.icon }}</span>
                  </span>
                  <span class="apphub-start__list-name">{{ app.name }}</span>
                </button>
              </li>
              <li v-if="!recentApps.length" class="apphub-start__empty">{{ emptyLabel }}</li>
            </ul>
          </template>
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

        <button
          v-if="settingsApp"
          type="button"
          class="apphub-start__hero apphub-start__hero--settings"
          @click="onOpen(settingsApp)"
        >
          <span class="apphub-start__hero-icon">{{ settingsApp.icon }}</span>
          <span class="apphub-start__hero-body">
            <strong class="apphub-start__hero-title">{{ settingsApp.name }}</strong>
            <span class="apphub-start__hero-hint">{{ settingsApp.hint }}</span>
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
        <p v-else-if="!featuredApp && !guideApp && !settingsApp" class="apphub-start__empty apphub-start__empty--right">
          {{ emptyLabel }}
        </p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  favoriteApps: { type: Array, default: () => [] },
  recentApps: { type: Array, default: () => [] },
  suggestedApps: { type: Array, default: () => [] },
  catalogApps: { type: Array, default: () => [] },
  visibleInStartIds: { type: Array, default: () => [] },
  searchPlaceholder: { type: String, default: '' },
  favoritesLabel: { type: String, default: '' },
  recentLabel: { type: String, default: '' },
  searchResultsLabel: { type: String, default: '' },
  suggestedLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
})

const emit = defineEmits(['close', 'open-app'])

const query = ref('')

watch(() => props.open, (isOpen) => {
  if (!isOpen) query.value = ''
})

const visibleSet = computed(() => new Set(props.visibleInStartIds ?? []))

const catalog = computed(() => props.catalogApps ?? [])

const isSearching = computed(() => query.value.trim().length > 0)

const searchResults = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return []
  return catalog.value.filter((app) => app.name?.toLowerCase().includes(q))
})

function findVisibleBuiltin(module) {
  const app = catalog.value.find((a) => a.builtin && a.module === module)
  return app && visibleSet.value.has(app.id) ? app : null
}

const featuredApp = computed(() => findVisibleBuiltin('app-store'))
const guideApp = computed(() => findVisibleBuiltin('guide'))
const settingsApp = computed(() => findVisibleBuiltin('settings'))

function onOpen(app) {
  emit('open-app', app)
  emit('close')
}
</script>
