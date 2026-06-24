<template>
  <Teleport to="body">
    <Transition name="apphub-inbox-drawer">
      <div
        v-if="open"
        class="apphub-inbox-drawer"
        role="dialog"
        aria-modal="true"
        :aria-label="labels.title"
      >
        <button
          type="button"
          class="apphub-inbox-drawer__backdrop"
          :aria-label="labels.close"
          @click="close"
        />

        <aside class="apphub-inbox-drawer__panel">
          <header class="apphub-inbox-drawer__head">
            <h2 class="apphub-inbox-drawer__title">{{ labels.title }}</h2>
            <div class="apphub-inbox-drawer__actions">
              <button
                v-if="items.length"
                type="button"
                class="apphub-inbox-drawer__read-all"
                @click="onReadAll"
              >
                {{ labels.readAll }}
              </button>
              <button
                type="button"
                class="apphub-inbox-drawer__close"
                :aria-label="labels.close"
                @click="close"
              >
                ×
              </button>
            </div>
          </header>

          <div class="apphub-inbox-drawer__body">
            <p v-if="loading && !items.length" class="apphub-inbox-drawer__empty">
              {{ labels.loading }}
            </p>
            <p v-else-if="!items.length" class="apphub-inbox-drawer__empty">
              {{ labels.empty }}
            </p>

            <ul v-else class="apphub-inbox-drawer__list">
              <TransitionGroup name="apphub-inbox-card">
                <li
                  v-for="item in items"
                  :key="item.id"
                  class="apphub-inbox-drawer__card"
                  :class="{
                    'apphub-inbox-drawer__card--unread': !item.read_at,
                    'apphub-inbox-drawer__card--removing': removingIds.includes(item.id),
                  }"
                >
                  <div class="apphub-inbox-drawer__card-accent" aria-hidden="true" />
                  <div class="apphub-inbox-drawer__card-icon" aria-hidden="true">
                    {{ item.app_icon || '📱' }}
                  </div>
                  <div class="apphub-inbox-drawer__card-body">
                    <div class="apphub-inbox-drawer__card-meta">
                      <strong>{{ item.app_name || item.app_slug }}</strong>
                      <time :datetime="item.created_at">{{ formatWhen(item.created_at) }}</time>
                    </div>
                    <h3 class="apphub-inbox-drawer__card-title">{{ item.title }}</h3>
                    <p v-if="item.body" class="apphub-inbox-drawer__card-text">{{ item.body }}</p>
                  </div>
                  <button
                    type="button"
                    class="apphub-inbox-drawer__card-dismiss"
                    :aria-label="labels.dismiss"
                    @click="dismiss(item.id)"
                  >
                    ×
                  </button>
                </li>
              </TransitionGroup>
            </ul>

            <button
              v-if="hasMore"
              type="button"
              class="apphub-inbox-drawer__more"
              :disabled="loadingMore"
              @click="loadMore"
            >
              {{ loadingMore ? labels.loading : labels.loadMore }}
            </button>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, inject } from 'vue'
import { USER_NOTIFICATION_CENTER_KEY } from '../composables/createUserNotificationCenter.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'

const center = inject(USER_NOTIFICATION_CENTER_KEY, null)
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('inbox_notif_title', lang.value),
  close: t('inbox_notif_close', lang.value),
  readAll: t('inbox_notif_read_all', lang.value),
  dismiss: t('inbox_notif_dismiss', lang.value),
  empty: t('inbox_notif_empty', lang.value),
  loading: t('inbox_notif_loading', lang.value),
  loadMore: t('inbox_notif_load_more', lang.value),
}))

const open = computed(() => center?.state.drawerOpen ?? false)
const items = computed(() => center?.state.items ?? [])
const loading = computed(() => center?.state.loading ?? false)
const loadingMore = computed(() => center?.state.loadingMore ?? false)
const hasMore = computed(() => center?.state.hasMore ?? false)
const removingIds = computed(() => center?.state.removingIds ?? [])

function close() {
  center?.closeDrawer?.()
}

function dismiss(id) {
  center?.dismissItem?.(id)
}

function onReadAll() {
  center?.readAll?.()
}

function loadMore() {
  center?.loadMore?.()
}

function formatWhen(iso) {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return String(iso)
  }
}
</script>
