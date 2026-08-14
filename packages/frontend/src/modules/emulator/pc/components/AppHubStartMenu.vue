<template>
  <div
    v-if="open"
    class="apphub-start"
    :data-ah-skin="skin || undefined"
    @click.stop="emit('close')"
    @contextmenu.prevent.stop
  >
    <div
      class="apphub-start__panel"
      :class="skin ? [`apphub-start__panel--skin`, `apphub-start__panel--skin-${skin}`] : null"
      @click.stop
      @contextmenu.prevent.stop
    >
      <header v-if="skin" class="apphub-start__skin-bar" aria-hidden="true">
        <span class="apphub-start__skin-badge">{{ skin }}</span>
        <span
          v-for="(glyph, i) in (skinMix || []).slice(0, 4)"
          :key="'bar-' + skin + i"
          class="apphub-start__skin-bar-glyph"
        >{{ glyph }}</span>
      </header>

      <div class="apphub-start__body">
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
              <li v-for="(app, idx) in searchResults" :key="app.id">
                <button
                  type="button"
                  class="apphub-start__list-item"
                  @click="onOpen(app)"
                  @contextmenu.prevent.stop="onAppContextMenu(app, $event)"
                >
                  <span class="apphub-start__list-icon-wrap">
                    <AppHubSkinChrome :skin="skin || ''" tone="pin">
                      <span class="apphub-start__list-icon">{{ glyphFor(idx, app) }}</span>
                    </AppHubSkinChrome>
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
              <li v-for="(app, idx) in favoriteApps" :key="`fav-${app.id}`">
                <button
                  type="button"
                  class="apphub-start__list-item"
                  @click="onOpen(app)"
                  @contextmenu.prevent.stop="onAppContextMenu(app, $event)"
                >
                  <span class="apphub-start__list-icon-wrap">
                    <AppHubSkinChrome :skin="skin || ''" tone="pin">
                      <span class="apphub-start__list-icon">{{ glyphFor(idx, app) }}</span>
                    </AppHubSkinChrome>
                  </span>
                  <span class="apphub-start__list-name">{{ app.name }}</span>
                </button>
              </li>
              <li v-if="!favoriteApps.length" class="apphub-start__empty">{{ emptyLabel }}</li>
            </ul>

            <p class="apphub-start__section-label apphub-start__section-label--spaced">{{ recentLabel }}</p>
            <ul class="apphub-start__list">
              <li v-for="(app, idx) in recentApps" :key="`recent-${app.id}`">
                <button
                  type="button"
                  class="apphub-start__list-item"
                  @click="onOpen(app)"
                  @contextmenu.prevent.stop="onAppContextMenu(app, $event)"
                >
                  <span class="apphub-start__list-icon-wrap">
                    <AppHubSkinChrome :skin="skin || ''" tone="pin">
                      <span class="apphub-start__list-icon">{{ glyphFor(idx + 2, app) }}</span>
                    </AppHubSkinChrome>
                  </span>
                  <span class="apphub-start__list-name">{{ app.name }}</span>
                </button>
              </li>
              <li v-if="!recentApps.length" class="apphub-start__empty">{{ emptyLabel }}</li>
            </ul>
          </template>
        </div>

        <footer v-if="shutdownAction" class="apphub-start__power">
          <button
            type="button"
            class="apphub-start__shutdown"
            :title="shutdownLabel"
            :aria-label="shutdownLabel"
            @click.stop="onShutdown"
          >
            <span class="apphub-start__shutdown-icon" aria-hidden="true">⏻</span>
            <span class="apphub-start__shutdown-label">{{ shutdownLabel }}</span>
          </button>
        </footer>
      </aside>

      <section class="apphub-start__right">
        <button
          v-if="featuredApp"
          type="button"
          class="apphub-start__hero"
          @click="onOpen(featuredApp)"
          @contextmenu.prevent.stop="onAppContextMenu(featuredApp, $event)"
        >
          <span class="apphub-start__hero-icon-wrap">
            <AppHubSkinChrome :skin="skin || ''" tone="tray">
              <span class="apphub-start__hero-icon">{{ glyphFor(0, featuredApp) }}</span>
            </AppHubSkinChrome>
          </span>
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
          @contextmenu.prevent.stop="onAppContextMenu(guideApp, $event)"
        >
          <span class="apphub-start__hero-icon-wrap">
            <AppHubSkinChrome :skin="skin || ''" tone="tray">
              <span class="apphub-start__hero-icon">{{ glyphFor(1, guideApp) }}</span>
            </AppHubSkinChrome>
          </span>
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
          @contextmenu.prevent.stop="onAppContextMenu(settingsApp, $event)"
        >
          <span class="apphub-start__hero-icon-wrap">
            <AppHubSkinChrome :skin="skin || ''" tone="tray">
              <span class="apphub-start__hero-icon">{{ glyphFor(2, settingsApp) }}</span>
            </AppHubSkinChrome>
          </span>
          <span class="apphub-start__hero-body">
            <strong class="apphub-start__hero-title">{{ settingsApp.name }}</strong>
            <span class="apphub-start__hero-hint">{{ settingsApp.hint }}</span>
          </span>
          <span class="apphub-start__hero-arrow" aria-hidden="true">›</span>
        </button>

        <p v-if="suggestedApps.length" class="apphub-start__section-label">{{ suggestedLabel }}</p>
        <div v-if="suggestedApps.length" class="apphub-start__grid">
          <button
            v-for="(app, idx) in suggestedApps"
            :key="app.id"
            type="button"
            class="apphub-start__app-tile"
            @click="onOpen(app)"
            @contextmenu.prevent.stop="onAppContextMenu(app, $event)"
          >
            <span class="apphub-start__app-tile-icon-wrap">
              <AppHubSkinChrome :skin="skin || ''" tone="pin">
                <span class="apphub-start__app-tile-icon">{{ glyphFor(idx, app) }}</span>
              </AppHubSkinChrome>
            </span>
            <span class="apphub-start__app-tile-name">{{ app.name }}</span>
          </button>
        </div>
        <p v-else-if="!featuredApp && !guideApp && !settingsApp" class="apphub-start__empty apphub-start__empty--right">
          {{ emptyLabel }}
        </p>
      </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppHubSkinChrome from './AppHubSkinChrome.vue'

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
  /** Normalized action name; empty hides the Start-panel shutdown control */
  shutdownAction: { type: String, default: '' },
  shutdownLabel: { type: String, default: '' },
  /** Active desktop theme skin — Start menu chrome + mix glyphs. */
  skin: { type: String, default: '' },
  skinMix: { type: Array, default: null },
})

const emit = defineEmits(['close', 'open-app', 'shutdown', 'app-context-menu'])

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

function glyphFor(_index, app) {
  return app?.icon || '◆'
}

function onOpen(app) {
  emit('open-app', app)
  emit('close')
}

function onAppContextMenu(app, event) {
  emit('app-context-menu', app, event)
}

function onShutdown() {
  emit('shutdown')
}
</script>
