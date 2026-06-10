<template>
  <div class="apphub-store-settings">
    <section class="apphub-store-settings__section">
      <h3 class="apphub-store-settings__heading">{{ labels.user_title }}</h3>
      <dl class="apphub-store-settings__dl">
        <div class="apphub-store-settings__row">
          <dt>{{ labels.user_id }}</dt>
          <dd>{{ userDisplay.id }}</dd>
        </div>
        <div class="apphub-store-settings__row">
          <dt>{{ labels.user_name }}</dt>
          <dd>{{ userDisplay.name }}</dd>
        </div>
        <div class="apphub-store-settings__row">
          <dt>{{ labels.auth_status }}</dt>
          <dd>
            <span class="apphub-store-settings__badge" :class="authBadgeClass">{{ authBadgeLabel }}</span>
          </dd>
        </div>
        <div v-if="zone.state.isManager" class="apphub-store-settings__row">
          <dt>{{ labels.manager }}</dt>
          <dd>{{ labels.manager_yes }}</dd>
        </div>
      </dl>
    </section>

    <section class="apphub-store-settings__section">
      <h3 class="apphub-store-settings__heading">{{ labels.zone_title }}</h3>
      <p class="apphub-store-settings__hint">{{ labels.zone_hint }}</p>

      <p v-if="zone.state.loading" class="apphub-store-settings__msg">{{ labels.zone_loading }}</p>
      <p v-else-if="zone.state.error === 'no_core_api'" class="apphub-store-settings__msg">
        {{ labels.zone_no_core }}
      </p>
      <p v-else-if="zone.state.error" class="apphub-store-settings__msg">{{ labels.zone_error }}</p>
      <p v-else-if="!zone.state.zones.length" class="apphub-store-settings__msg">{{ labels.zone_empty }}</p>

      <template v-else>
        <label v-if="zone.state.zones.length > 1" class="apphub-store-settings__check">
          <input
            type="checkbox"
            :checked="zone.state.viewAllZones"
            @change="onViewAllChange"
          />
          {{ labels.zone_all }}
        </label>

        <div
          v-if="zone.state.zones.length > 1 && !zone.state.viewAllZones"
          class="apphub-store-settings__field"
        >
          <label class="apphub-store-settings__label" for="apphub-store-zone-select">{{ labels.zone_select }}</label>
          <select
            id="apphub-store-zone-select"
            class="apphub-store-settings__select"
            :value="zone.state.selectedZoneId ?? ''"
            @change="onZoneSelect"
          >
            <option v-for="z in zone.state.zones" :key="z.id" :value="z.id">
              {{ z.name || labels.zone_name(z.id) }}
            </option>
          </select>
        </div>

        <div v-else-if="zone.state.zones.length === 1" class="apphub-store-settings__single">
          <span class="apphub-store-settings__zone-chip">
            {{ zone.state.zones[0].name || labels.zone_name(zone.state.zones[0].id) }}
          </span>
        </div>

        <ul class="apphub-store-settings__zone-list">
          <li
            v-for="z in zone.state.zones"
            :key="z.id"
            class="apphub-store-settings__zone-item"
            :class="{ 'apphub-store-settings__zone-item--active': isZoneActive(z.id) }"
          >
            <span class="apphub-store-settings__zone-id">#{{ z.id }}</span>
            <span>{{ z.name || labels.zone_name(z.id) }}</span>
          </li>
        </ul>

        <p v-if="zone.state.timezone" class="apphub-store-settings__meta">
          {{ labels.timezone }}: {{ zone.state.timezone }}
        </p>
      </template>

      <button type="button" class="apphub-store-settings__refresh" @click="onRefresh">
        {{ labels.refresh }}
      </button>
    </section>

    <AppHubDevReviewPanel
      :root-app="rootApp"
      :dev-apps="devApps"
      @refreshed="onDevRefreshed"
    />

    <section v-if="devApps.length" class="apphub-store-settings__section">
      <h3 class="apphub-store-settings__heading">{{ labels.dev_title }}</h3>
      <p class="apphub-store-settings__hint">{{ labels.dev_hint }}</p>
      <ul class="apphub-store-settings__dev-list">
        <li v-for="app in devApps" :key="app.slug" class="apphub-store-settings__dev-item">
          <span>{{ app.name }} ({{ app.status }})</span>
          <button
            v-if="app.status !== 'disabled'"
            type="button"
            class="apphub-store-settings__dev-btn"
            @click="disableApp(app.slug)"
          >
            {{ labels.dev_disable }}
          </button>
          <button
            v-else
            type="button"
            class="apphub-store-settings__dev-btn"
            @click="setAppStatus(app.slug, 'active')"
          >
            {{ labels.dev_enable }}
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { getHostApiForApp } from '../../../composables/useAppHubHostApi.js'
import { useAppHubZoneContext } from '../../../composables/useAppHubZoneContext.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import AppHubDevReviewPanel from './AppHubDevReviewPanel.vue'

const props = defineProps({
  rootApp: { type: Object, default: null },
})

const emit = defineEmits(['refreshed'])

const devApps = ref([])
const zone = useAppHubZoneContext()
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  user_title: t('app_store_settings_user_title', lang.value),
  user_id: t('app_store_settings_user_id', lang.value),
  user_name: t('app_store_settings_user_name', lang.value),
  auth_status: t('app_store_settings_auth_status', lang.value),
  auth_ok: t('app_store_settings_auth_ok', lang.value),
  auth_missing: t('app_store_settings_auth_missing', lang.value),
  auth_fail: t('app_store_settings_auth_fail', lang.value),
  manager: t('app_store_settings_manager', lang.value),
  manager_yes: t('app_store_settings_manager_yes', lang.value),
  zone_title: t('app_store_settings_zone_title', lang.value),
  zone_hint: t('app_store_settings_zone_hint', lang.value),
  zone_loading: t('app_store_settings_zone_loading', lang.value),
  zone_no_core: t('app_store_settings_zone_no_core', lang.value),
  zone_error: t('app_store_settings_zone_error', lang.value),
  zone_empty: t('app_store_settings_zone_empty', lang.value),
  zone_select: t('app_store_settings_zone_select', lang.value),
  zone_all: t('app_store_settings_zone_all', lang.value),
  zone_name: (id) => t('app_store_settings_zone_name', lang.value, { id }),
  timezone: t('app_store_settings_timezone', lang.value),
  refresh: t('app_store_settings_refresh', lang.value),
  dev_title: t('app_store_settings_dev_title', lang.value),
  dev_hint: t('app_store_settings_dev_hint', lang.value),
  dev_disable: t('app_store_settings_dev_disable', lang.value),
  dev_enable: t('app_store_settings_dev_enable', lang.value),
}))

const userDisplay = computed(() => ({
  id: zone.state.user?.id ?? '—',
  name: zone.state.user?.name ?? '—',
}))

const authBadgeClass = computed(() => {
  if (!moduleOptions?.hasToken) return 'apphub-store-settings__badge--warn'
  if (zone.state.authOk) return 'apphub-store-settings__badge--ok'
  return 'apphub-store-settings__badge--warn'
})

const authBadgeLabel = computed(() => {
  if (!moduleOptions?.hasToken) return labels.value.auth_missing
  if (zone.state.authOk) return labels.value.auth_ok
  return labels.value.auth_fail
})

function isZoneActive(id) {
  if (zone.state.viewAllZones) return true
  return zone.state.selectedZoneId === id
}

function onZoneSelect(event) {
  const id = Number(event.target.value)
  if (Number.isFinite(id)) zone.selectZone(id)
}

function onViewAllChange(event) {
  zone.setViewAllZones(event.target.checked)
}

async function loadDevApps() {
  const api = getHostApiForApp(props.rootApp)
  if (!api?.bootstrap || !api?.devApps) {
    devApps.value = []
    return
  }
  try {
    const boot = await api.bootstrap()
    const isDev = boot?.data?.data?.is_dev_user === true
    if (!isDev) {
      devApps.value = []
      return
    }
    const res = await api.devApps()
    devApps.value = res?.data?.data ?? []
  } catch {
    devApps.value = []
  }
}

async function disableApp(slug) {
  const api = getHostApiForApp(props.rootApp)
  if (!api?.devDisableApp) return
  try {
    await api.devDisableApp(slug)
    await loadDevApps()
    emit('refreshed')
  } catch {
    /* ignore */
  }
}

async function setAppStatus(slug, status) {
  const api = getHostApiForApp(props.rootApp)
  if (!api?.devSetAppStatus) return
  try {
    await api.devSetAppStatus(slug, status)
    await loadDevApps()
    emit('refreshed')
  } catch {
    /* ignore */
  }
}

async function onDevRefreshed() {
  await loadDevApps()
  emit('refreshed')
}

async function onRefresh() {
  await zone.refresh()
  await loadDevApps()
  emit('refreshed')
}

onMounted(() => {
  if (!zone.state.zones.length && !zone.state.loading) {
    onRefresh()
  } else {
    loadDevApps()
  }
})
</script>
