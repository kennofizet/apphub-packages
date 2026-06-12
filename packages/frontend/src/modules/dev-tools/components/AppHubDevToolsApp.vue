<template>
  <div class="apphub-dev-tools">
    <header class="apphub-dev-tools__header">
      <h2 class="apphub-dev-tools__title">{{ labels.title }}</h2>
      <p class="apphub-dev-tools__hint">{{ labels.hint }}</p>
    </header>

    <div v-if="!accessChecked" class="apphub-dev-tools__body">
      <p class="apphub-dev-tools__msg">{{ labels.loading }}</p>
    </div>

    <div v-else-if="!isDev" class="apphub-dev-tools__body">
      <p class="apphub-dev-tools__msg">{{ labels.forbidden }}</p>
    </div>

    <template v-else>
      <nav class="apphub-dev-tools__tabs" role="tablist">
        <button
          type="button"
          class="apphub-dev-tools__tab"
          :class="{ 'apphub-dev-tools__tab--active': tab === 'review' }"
          @click="tab = 'review'"
        >
          {{ labels.tab_review }} ({{ draftApps.length }})
        </button>
        <button
          type="button"
          class="apphub-dev-tools__tab"
          :class="{ 'apphub-dev-tools__tab--active': tab === 'active' }"
          @click="tab = 'active'"
        >
          {{ labels.tab_active }} ({{ activeApps.length }})
        </button>
      </nav>

      <div class="apphub-dev-tools__body">
        <p v-if="loading" class="apphub-dev-tools__msg">{{ labels.loading_apps }}</p>

        <template v-else-if="tab === 'review'">
          <AppHubDevToolsCodeReview
            v-if="expandedSlug && expandedApp"
            :app="expandedApp"
            :labels="labels"
            :inspect="inspect[expandedSlug]"
            :file-content="fileContent[expandedSlug]"
            :selected-file="selectedFile[expandedSlug] ?? ''"
            :acting="actingSlug === expandedSlug"
            @close="closeCodeReview"
            @select-file="(path) => selectFile(expandedSlug, path)"
            @approve="approve(expandedSlug)"
            @reject="reject(expandedSlug)"
          />
          <template v-else>
            <p v-if="!draftApps.length" class="apphub-dev-tools__msg">{{ labels.review_empty }}</p>
            <ul v-else class="apphub-dev-tools__list">
              <li v-for="app in draftApps" :key="app.slug" class="apphub-dev-tools__item">
                <AppHubDevToolsReviewItem
                  :app="app"
                  :labels="labels"
                  :acting="actingSlug === app.slug"
                  @toggle="toggleExpand(app.slug)"
                  @approve="approve(app.slug)"
                  @reject="reject(app.slug)"
                />
              </li>
            </ul>
          </template>
        </template>

        <template v-else>
          <p v-if="!activeApps.length" class="apphub-dev-tools__msg">{{ labels.active_empty }}</p>
          <ul v-else class="apphub-dev-tools__list">
            <li v-for="app in activeApps" :key="app.slug" class="apphub-dev-tools__item">
              <div class="apphub-dev-tools__item-head">
                <span aria-hidden="true">{{ app.icon || '📦' }}</span>
                <div class="apphub-dev-tools__meta">
                  <strong>{{ app.name }}</strong>
                  <span class="apphub-dev-tools__slug">{{ app.slug }}</span>
                  <span v-if="app.version" class="apphub-dev-tools__slug">v{{ app.version }}</span>
                  <span v-if="app.pending_version" class="apphub-dev-tools__slug">→ v{{ app.pending_version }}</span>
                  <span class="apphub-dev-tools__badge apphub-dev-tools__badge--active">{{ labels.status_active }}</span>
                  <span v-if="app.pending_version" class="apphub-dev-tools__badge apphub-dev-tools__badge--draft">{{ labels.pending_version }}</span>
                </div>
                <div class="apphub-dev-tools__actions">
                  <button
                    type="button"
                    class="apphub-dev-tools__btn apphub-dev-tools__btn--danger"
                    :disabled="actingSlug === app.slug"
                    @click="disableApp(app.slug)"
                  >
                    {{ labels.disable }}
                  </button>
                </div>
              </div>
            </li>
          </ul>

          <p v-if="disabledApps.length" class="apphub-dev-tools__hint" style="margin-top: 16px ; margin-bottom: 16px">
            {{ labels.disabled_section }}
          </p>
          <ul v-if="disabledApps.length" class="apphub-dev-tools__list">
            <li v-for="app in disabledApps" :key="app.slug" class="apphub-dev-tools__item">
              <div class="apphub-dev-tools__item-head">
                <div class="apphub-dev-tools__meta">
                  <strong>{{ app.name }}</strong>
                  <span class="apphub-dev-tools__slug">{{ app.slug }}</span>
                  <span class="apphub-dev-tools__badge apphub-dev-tools__badge--disabled">{{ labels.status_disabled }}</span>
                </div>
                <div class="apphub-dev-tools__actions">
                  <button
                    type="button"
                    class="apphub-dev-tools__btn apphub-dev-tools__btn--ok"
                    :disabled="actingSlug === app.slug"
                    @click="enableApp(app.slug)"
                  >
                    {{ labels.enable }}
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </template>
      </div>
    </template>

    <AppHubConfirmDialog
      :open="confirmDialog.dialog.open"
      :title="confirmDialog.dialog.title"
      :message="confirmDialog.dialog.message"
      :hint="confirmDialog.dialog.hint"
      :confirm-label="confirmDialog.dialog.confirmLabel"
      :cancel-label="confirmDialog.dialog.cancelLabel"
      :busy-label="confirmDialog.dialog.busyLabel"
      :danger="confirmDialog.dialog.danger"
      :alert-only="confirmDialog.dialog.alertOnly"
      :busy="confirmDialog.dialog.busy"
      @confirm="onConfirmDialogConfirm"
      @cancel="confirmDialog.handleCancel"
    />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref } from 'vue'
import { AppHubConfirmDialog, useConfirmDialog } from '../../../components/confirm/index.js'
import { getHostApiForApp } from '../../../composables/useAppHubHostApi.js'
import { CATALOG_MAX_PER_PAGE } from '../../../utils/catalogPagination.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import AppHubDevToolsCodeReview from './AppHubDevToolsCodeReview.vue'
import AppHubDevToolsReviewItem from './AppHubDevToolsReviewItem.vue'

const props = defineProps({
  onCatalogChanged: { type: Function, default: null },
})

const rootApp = inject('apphubHostApp', null)
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  title: t('dev_tools_title', lang.value),
  hint: t('dev_tools_hint', lang.value),
  loading: t('dev_tools_loading', lang.value),
  forbidden: t('dev_tools_forbidden', lang.value),
  loading_apps: t('dev_tools_loading_apps', lang.value),
  tab_review: t('dev_tools_tab_review', lang.value),
  tab_active: t('dev_tools_tab_active', lang.value),
  review_empty: t('dev_review_empty', lang.value),
  active_empty: t('dev_tools_active_empty', lang.value),
  disabled_section: t('dev_tools_disabled_section', lang.value),
  status_active: t('app_store_status_active', lang.value),
  status_disabled: t('app_store_status_offline', lang.value),
  disable: t('app_store_settings_dev_disable', lang.value),
  enable: t('app_store_settings_dev_enable', lang.value),
  hash: t('dev_review_hash', lang.value),
  file_count: t('dev_review_file_count', lang.value),
  files_title: t('dev_review_files_title', lang.value),
  files_truncated: t('dev_review_files_truncated', lang.value),
  approve: t('dev_review_approve', lang.value),
  approving: t('dev_review_approving', lang.value),
  reject: t('dev_tools_reject', lang.value),
  view_code: t('dev_tools_view_code', lang.value),
  code_loading: t('dev_tools_code_loading', lang.value),
  code_empty: t('dev_tools_code_empty', lang.value),
  code_truncated: t('dev_tools_code_truncated', lang.value),
  hosted: t('runner_hosted_badge', lang.value),
  iframe: t('dev_review_iframe', lang.value),
  reject_confirm: t('dev_tools_reject_confirm', lang.value),
  reject_confirm_version: t('dev_tools_reject_confirm_version', lang.value),
  reject_done: t('dev_tools_reject_done', lang.value),
  reject_done_version: t('dev_tools_reject_done_version', lang.value),
  reject_nothing: t('dev_tools_reject_nothing', lang.value),
  reject_title: t('dev_tools_reject_title', lang.value),
  reject_error_failed: t('dev_tools_reject_error_failed', lang.value),
  reject_error_no_api: t('dev_tools_reject_error_no_api', lang.value),
  confirm_cancel: t('confirm_cancel', lang.value),
  confirm_ok: t('confirm_ok', lang.value),
  status_draft: t('app_store_status_draft', lang.value),
  pending_version: t('publisher_pending_version_badge', lang.value),
  back: t('dev_tools_code_back', lang.value),
  diff_against: t('dev_tools_diff_against', lang.value),
  changed_only: t('dev_tools_changed_only', lang.value),
  file_modified: t('dev_tools_file_modified', lang.value),
  file_added: t('dev_tools_file_added', lang.value),
  file_deleted: t('dev_tools_file_deleted', lang.value),
  file_unchanged: t('dev_tools_file_unchanged', lang.value),
  code_truncated_old: t('dev_tools_code_truncated_old', lang.value),
}))

const confirmDialog = useConfirmDialog()

const accessChecked = ref(false)
const isDev = ref(false)
const loading = ref(false)
const tab = ref('review')
const allApps = ref([])
const actingSlug = ref('')
const expandedSlug = ref('')
const inspect = reactive({})
const selectedFile = reactive({})
const fileContent = reactive({})

const draftApps = computed(() => allApps.value.filter((a) => a?.status === 'draft' || a?.pending_version))
const activeApps = computed(() => allApps.value.filter((a) => a?.status === 'active'))
const expandedApp = computed(() =>
  expandedSlug.value ? allApps.value.find((a) => a?.slug === expandedSlug.value) ?? null : null,
)
const disabledApps = computed(() => allApps.value.filter((a) => a?.status === 'disabled'))

function api() {
  return getHostApiForApp(rootApp)
}

async function checkAccess() {
  const client = api()
  if (!client?.bootstrap) {
    accessChecked.value = true
    isDev.value = false
    return
  }
  try {
    const boot = await client.bootstrap()
    isDev.value = boot?.data?.data?.is_dev_user === true
  } catch {
    isDev.value = false
  } finally {
    accessChecked.value = true
  }
}

async function loadApps() {
  const client = api()
  if (!client?.devApps || !isDev.value) {
    allApps.value = []
    return
  }
  loading.value = true
  try {
    const items = []
    let page = 1
    let lastPage = 1
    do {
      const res = await client.devApps({ per_page: CATALOG_MAX_PER_PAGE, page })
      items.push(...(res?.data?.data ?? []))
      lastPage = Number(res?.data?.meta?.last_page) || 1
      page += 1
    } while (page <= lastPage)
    allApps.value = items
  } catch {
    allApps.value = []
  } finally {
    loading.value = false
  }
}

function notifyCatalogChange(catalogApp) {
  props.onCatalogChanged?.(catalogApp)
}

function closeCodeReview() {
  const slug = expandedSlug.value
  expandedSlug.value = ''
  if (slug) {
    delete selectedFile[slug]
    delete fileContent[slug]
  }
}

async function toggleExpand(slug) {
  if (expandedSlug.value === slug) {
    closeCodeReview()
    return
  }
  expandedSlug.value = slug
  delete selectedFile[slug]
  delete fileContent[slug]

  const client = api()
  if (!client?.devInspectBundle) return
  try {
    const res = await client.devInspectBundle(slug)
    inspect[slug] = res?.data?.data ?? null
    const first = pickDefaultFile(inspect[slug])
    if (first) await selectFile(slug, first)
  } catch {
    inspect[slug] = { file_entries: [], files: [], file_count: 0 }
  }
}

function pickDefaultFile(inspectData) {
  const entries = inspectData?.file_entries
  if (Array.isArray(entries) && entries.length) {
    const changed = entries.find((e) => e?.status && e.status !== 'unchanged')
    return (changed ?? entries[0])?.path ?? ''
  }
  return inspectData?.files?.[0] ?? ''
}

async function selectFile(slug, path) {
  selectedFile[slug] = path
  const client = api()
  if (!client?.devReadBundleFile) {
    fileContent[slug] = { error: labels.value.code_empty }
    return
  }
  const compare = inspect[slug]?.has_baseline === true
  fileContent[slug] = { loading: true }
  try {
    const res = await client.devReadBundleFile(slug, path, { compare })
    fileContent[slug] = res?.data?.data ?? { content: '', truncated: false }
  } catch {
    fileContent[slug] = { error: labels.value.code_empty }
  }
}

async function approve(slug) {
  const client = api()
  if (!client?.devSetAppStatus) return
  actingSlug.value = slug
  try {
    const res = await client.devSetAppStatus(slug, 'active')
    const data = res?.data?.data
    if (data) notifyCatalogChange(data)
    await loadApps()
    expandedSlug.value = ''
    delete inspect[slug]
  } finally {
    actingSlug.value = ''
  }
}

function postRejectPending(client, slug) {
  if (typeof client?.devRejectPendingVersion === 'function') {
    return client.devRejectPendingVersion(slug)
  }
  if (typeof client?.post === 'function') {
    return client.post(`/dev/apps/${encodeURIComponent(slug)}/reject-pending`)
  }
  return null
}

async function executeRejectPending(slug) {
  const client = api()
  const request = client ? postRejectPending(client, slug) : null
  if (!request) {
    throw new Error(labels.value.reject_error_no_api)
  }

  actingSlug.value = slug
  try {
    const res = await request
    const data = res?.data?.data
    if (data) notifyCatalogChange(data)
    await loadApps()
    if (expandedSlug.value === slug) {
      expandedSlug.value = ''
      delete inspect[slug]
      delete selectedFile[slug]
      delete fileContent[slug]
    }
  } finally {
    actingSlug.value = ''
  }
}

function onConfirmDialogConfirm() {
  confirmDialog.handleConfirm()
}

async function reject(slug) {
  const app = allApps.value.find((a) => a?.slug === slug)
  if (!app) return

  if (app.pending_version) {
    const ok = await confirmDialog.confirm({
      title: labels.value.reject_title,
      message: labels.value.reject_confirm_version,
      confirmLabel: labels.value.reject,
      cancelLabel: labels.value.confirm_cancel,
      danger: true,
    })
    if (!ok) return

    try {
      await executeRejectPending(slug)
    } catch (err) {
      const message = err?.response?.data?.error ?? err?.message ?? labels.value.reject_error_failed
      await confirmDialog.alert({
        title: labels.value.reject_title,
        message,
        confirmLabel: labels.value.confirm_ok,
      })
    }
    return
  }

  if (app.status === 'draft') {
    await confirmDialog.confirm({
      title: labels.value.reject_title,
      message: labels.value.reject_confirm,
      hint: labels.value.reject_done,
      confirmLabel: labels.value.reject,
      cancelLabel: labels.value.confirm_cancel,
      danger: true,
    })
    return
  }

  await confirmDialog.alert({
    title: labels.value.reject_title,
    message: labels.value.reject_nothing,
    confirmLabel: labels.value.confirm_ok,
  })
}

async function disableApp(slug) {
  const client = api()
  if (!client?.devDisableApp) return
  actingSlug.value = slug
  try {
    const res = await client.devDisableApp(slug)
    const data = res?.data?.data
    if (data) notifyCatalogChange(data)
    await loadApps()
  } finally {
    actingSlug.value = ''
  }
}

async function enableApp(slug) {
  const client = api()
  if (!client?.devSetAppStatus) return
  actingSlug.value = slug
  try {
    const res = await client.devSetAppStatus(slug, 'active')
    const data = res?.data?.data
    if (data) notifyCatalogChange(data)
    await loadApps()
  } finally {
    actingSlug.value = ''
  }
}

onMounted(async () => {
  await checkAccess()
  if (isDev.value) await loadApps()
})
</script>
