<template>
  <div class="apphub-guide">
    <header class="apphub-guide__header">
      <h2 class="apphub-guide__title">{{ labels.title }}</h2>
      <p class="apphub-guide__subtitle">{{ labels.subtitle }}</p>
    </header>

    <nav class="apphub-guide__tabs" role="tablist">
      <button
        type="button"
        role="tab"
        class="apphub-guide__tab"
        :class="{ 'apphub-guide__tab--active': tab === 'user' }"
        :aria-selected="tab === 'user'"
        @click="tab = 'user'"
      >
        {{ labels.tab_user }}
      </button>
      <button
        type="button"
        role="tab"
        class="apphub-guide__tab"
        :class="{ 'apphub-guide__tab--active': tab === 'dev' }"
        :aria-selected="tab === 'dev'"
        @click="tab = 'dev'"
      >
        {{ labels.tab_dev }}
      </button>
    </nav>

    <div class="apphub-guide__body">
      <article v-show="tab === 'user'" class="apphub-guide__article">
        <section v-for="block in userSections" :key="block.title" class="apphub-guide__section">
          <h3>{{ block.title }}</h3>
          <ul>
            <li v-for="(line, i) in block.lines" :key="i">{{ line }}</li>
          </ul>
        </section>
      </article>

      <article v-show="tab === 'dev'" class="apphub-guide__article">
        <section v-for="block in devSections" :key="block.title" class="apphub-guide__section">
          <h3>{{ block.title }}</h3>
          <ul v-if="block.lines?.length">
            <li v-for="(line, i) in block.lines" :key="i">
              <button
                v-if="lineAction(line) === 'integration-docs'"
                type="button"
                class="apphub-guide__link"
                :disabled="integrationDocsLoading"
                @click="openIntegrationDocs"
              >
                {{ lineLabel(line) }}
              </button>
              <span v-else>{{ lineLabel(line) }}</span>
            </li>
          </ul>
          <pre v-if="block.code" class="apphub-guide__code"><code>{{ block.code }}</code></pre>
        </section>

        <section v-if="integrationDocsPanelOpen" ref="integrationDocsPanelRef" class="apphub-guide__section apphub-guide__docs-panel">
          <div class="apphub-guide__docs-head">
            <div>
              <h3 class="apphub-guide__docs-title">{{ labels.docs_panel_title }}</h3>
            </div>
            <button type="button" class="apphub-guide__docs-close" @click="closeIntegrationDocs">
              {{ labels.docs_close }}
            </button>
          </div>
          <p v-if="integrationDocsLoading" class="apphub-guide__docs-msg">{{ labels.docs_loading }}</p>
          <p v-else-if="integrationDocsError" class="apphub-guide__docs-msg apphub-guide__docs-msg--error">
            {{ integrationDocsError }}
          </p>
          <template v-else>
            <section
              v-for="block in publisherGuideSections"
              :key="block.title"
              class="apphub-guide__section apphub-guide__section--nested"
            >
              <h4 class="apphub-guide__subsection-title">{{ block.title }}</h4>
              <ul v-if="block.lines?.length">
                <li v-for="(line, i) in block.lines" :key="i">{{ line }}</li>
              </ul>
              <pre v-if="block.code" class="apphub-guide__code"><code>{{ block.code }}</code></pre>
            </section>
            <p class="apphub-guide__docs-ai-note">
              {{ labels.docs_ai_note_before }}
              <a
                v-if="integrationDocsUrl"
                :href="integrationDocsUrl"
                class="apphub-guide__docs-ai-link"
                target="_blank"
                rel="noopener noreferrer"
              >{{ integrationDocsUrl }}</a>
              <code v-else class="apphub-guide__docs-ai-fallback">GET …/integration-docs</code>
              {{ labels.docs_ai_note_after }}
            </p>
          </template>
        </section>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, nextTick, ref } from 'vue'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { t } from '../../../i18n/index.js'
import { useAppHubHostApi } from '../../../composables/useAppHubHostApi.js'
import { publisherGuideSectionsFromIntegrationDocs } from '../../../utils/publisherGuideFromIntegrationDocs.js'
import { resolveRuntimeApiBase } from '../../../utils/originSafety.js'

const tab = ref('user')
const moduleOptions = inject('apphubOptions', {})
const api = useAppHubHostApi()
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const integrationDocsRaw = ref(null)
const integrationDocsLoading = ref(false)
const integrationDocsError = ref('')
const integrationDocsPanelOpen = ref(false)
const integrationDocsPanelRef = ref(null)

const integrationDocsUrl = computed(() => {
  const base = resolveRuntimeApiBase({
    backendUrl: moduleOptions?.backendUrl,
    runtimePublicUrl: moduleOptions?.runtimePublicUrl,
  })
  if (!base) return ''
  return `${base.replace(/\/$/, '')}/integration-docs`
})

const refLabels = computed(() => ({
  overviewTitle: t('guide_ref_overview_title', lang.value),
  runtimeTitle: t('guide_ref_runtime_title', lang.value),
  hostedTitle: t('guide_ref_hosted_label', lang.value),
  iframeTitle: t('guide_ref_iframe_label', lang.value),
  storageTitle: t('guide_ref_storage_title', lang.value),
  launchTitle: t('guide_ref_launch_title', lang.value),
  permissionsTitle: t('guide_ref_permissions_title', lang.value),
  parentScopesTitle: t('guide_ref_parent_scopes_title', lang.value),
  parentActionLabel: t('guide_ref_parent_action_label', lang.value),
  apiTitle: t('guide_ref_api_title', lang.value),
  exampleTitle: t('guide_dev_bridge_code_title', lang.value),
  manifestTitle: t('guide_ref_manifest_title', lang.value),
  deployTitle: t('guide_ref_deploy_title', lang.value),
  stepsTitle: t('guide_ref_steps_title', lang.value),
  userInfoTitle: t('guide_dev_user_title', lang.value),
  whenUse: t('guide_ref_when_use', lang.value),
  manifestLine: t('guide_ref_manifest_line', lang.value),
  launchLine: t('guide_ref_launch_line', lang.value),
  storageLine: t('guide_ref_storage_line', lang.value),
  apiNeeds: t('guide_ref_api_needs', lang.value),
  uiOnly: t('guide_ref_ui_only', lang.value),
  uiDoNot: t('guide_ref_ui_do_not', lang.value),
  trustedBackend: t('guide_ref_trusted_backend', lang.value),
  hostedTroubleshootingTitle: t('guide_ref_hosted_troubleshooting_title', lang.value),
}))

const publisherGuideSections = computed(() => {
  if (!integrationDocsRaw.value) return []
  return publisherGuideSectionsFromIntegrationDocs(
    integrationDocsRaw.value,
    refLabels.value,
    lang.value,
  )
})

const labels = computed(() => ({
  title: t('guide_app_title', lang.value),
  subtitle: t('guide_app_subtitle', lang.value),
  tab_user: t('guide_tab_user', lang.value),
  tab_dev: t('guide_tab_dev', lang.value),
  docs_panel_title: t('guide_dev_docs_panel_title', lang.value),
  docs_close: t('guide_dev_docs_close', lang.value),
  docs_loading: t('guide_dev_docs_loading', lang.value),
  docs_ai_note_before: t('guide_dev_docs_ai_note_before', lang.value),
  docs_ai_note_after: t('guide_dev_docs_ai_note_after', lang.value),
}))

function lineAction(line) {
  return typeof line === 'object' && line?.action ? line.action : ''
}

function lineLabel(line) {
  return typeof line === 'object' && line?.label != null ? line.label : String(line ?? '')
}

function closeIntegrationDocs() {
  integrationDocsPanelOpen.value = false
  integrationDocsError.value = ''
  integrationDocsRaw.value = null
}

async function openIntegrationDocs() {
  if (!api?.integrationDocs) {
    integrationDocsError.value = t('guide_dev_docs_error_no_api', lang.value)
    integrationDocsPanelOpen.value = true
    return
  }

  integrationDocsPanelOpen.value = true
  integrationDocsLoading.value = true
  integrationDocsError.value = ''
  integrationDocsRaw.value = null

  try {
    const res = await api.integrationDocs()
    integrationDocsRaw.value = res?.data?.data ?? res?.data ?? res
  } catch {
    integrationDocsError.value = t('guide_dev_docs_error_fetch', lang.value)
  } finally {
    integrationDocsLoading.value = false
    await nextTick()
    integrationDocsPanelRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' })
  }
}

const userSections = computed(() => [
  {
    title: t('guide_user_welcome_title', lang.value),
    lines: [
      t('guide_user_welcome_1', lang.value),
      t('guide_user_welcome_2', lang.value),
    ],
  },
  {
    title: t('guide_user_desktop_title', lang.value),
    lines: [
      t('guide_user_desktop_1', lang.value),
      t('guide_user_desktop_2', lang.value),
      t('guide_user_desktop_3', lang.value),
    ],
  },
  {
    title: t('guide_user_windows_title', lang.value),
    lines: [
      t('guide_user_windows_1', lang.value),
      t('guide_user_windows_2', lang.value),
      t('guide_user_windows_3', lang.value),
    ],
  },
  {
    title: t('guide_user_install_title', lang.value),
    lines: [
      t('guide_user_install_1', lang.value),
      t('guide_user_install_2', lang.value),
      t('guide_user_install_3', lang.value),
    ],
  },
  {
    title: t('guide_user_start_title', lang.value),
    lines: [
      t('guide_user_start_1', lang.value),
      t('guide_user_start_2', lang.value),
    ],
  },
  {
    title: t('guide_user_tips_title', lang.value),
    lines: [
      t('guide_user_tips_1', lang.value),
      t('guide_user_tips_2', lang.value),
      t('guide_user_tips_3', lang.value),
    ],
  },
])

const devSections = computed(() => [
  {
    title: t('guide_dev_who_title', lang.value),
    lines: [
      t('guide_dev_who_1', lang.value),
      t('guide_dev_who_2', lang.value),
    ],
  },
  {
    title: t('guide_dev_runtime_title', lang.value),
    lines: [
      t('guide_dev_runtime_1', lang.value),
      t('guide_dev_runtime_2', lang.value),
      t('guide_dev_runtime_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_bridge_title', lang.value),
    lines: [
      t('guide_dev_bridge_1', lang.value),
      t('guide_dev_bridge_2', lang.value),
      t('guide_dev_bridge_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_storage_title', lang.value),
    lines: [
      t('guide_dev_storage_1', lang.value),
      t('guide_dev_storage_2', lang.value),
      t('guide_dev_storage_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_permissions_title', lang.value),
    lines: [
      t('guide_dev_permissions_1', lang.value),
      t('guide_dev_permissions_2', lang.value),
      t('guide_dev_permissions_3', lang.value),
      t('guide_dev_permissions_4', lang.value),
    ],
  },
  {
    title: t('guide_dev_bridge_code_title', lang.value),
    code: t('guide_dev_bridge_code', lang.value),
  },
  {
    title: t('guide_dev_user_title', lang.value),
    lines: [
      t('guide_dev_user_1', lang.value),
      t('guide_dev_user_2', lang.value),
      t('guide_dev_user_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_desktop_title', lang.value),
    lines: [
      t('guide_dev_desktop_1', lang.value),
      t('guide_dev_desktop_2', lang.value),
      t('guide_dev_desktop_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_deploy_title', lang.value),
    lines: [
      t('guide_dev_deploy_1', lang.value),
      t('guide_dev_deploy_2', lang.value),
      t('guide_dev_deploy_3', lang.value),
    ],
  },
  {
    title: t('guide_dev_docs_title', lang.value),
    lines: [
      t('guide_dev_docs_1', lang.value),
      { action: 'integration-docs', label: t('guide_dev_docs_link', lang.value) },
      t('guide_dev_docs_2', lang.value),
      t('guide_dev_docs_3', lang.value),
    ],
  },
])
</script>

<style scoped>
.apphub-guide {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  color: var(--ah-text-secondary, #cbd5e1);
  background: var(--ah-surface, #1e293b);
}

.apphub-guide__header {
  padding: 20px 24px 12px;
  border-bottom: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-guide__title {
  margin: 0;
  font-size: 1.35rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__subtitle {
  margin: 6px 0 0;
  font-size: 0.88rem;
  color: var(--ah-text-muted, #94a3b8);
}

.apphub-guide__tabs {
  display: flex;
  gap: 4px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-guide__tab {
  padding: 8px 16px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  color: var(--ah-text-muted, #94a3b8);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.apphub-guide__tab:hover {
  background: var(--ah-hover, rgba(255, 255, 255, 0.08));
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__tab--active {
  background: var(--ah-hover-strong, rgba(255, 255, 255, 0.12));
  border-color: var(--ah-border, rgba(255, 255, 255, 0.1));
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 24px 24px;
}

.apphub-guide__section {
  margin-bottom: 22px;
}

.apphub-guide__section h3 {
  margin: 0 0 10px;
  font-size: 1rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__section ul {
  margin: 0;
  padding-left: 1.25rem;
  line-height: 1.55;
  font-size: 0.875rem;
}

.apphub-guide__section li {
  margin-bottom: 6px;
}

.apphub-guide__code {
  margin: 10px 0 0;
  padding: 12px 14px;
  border-radius: 6px;
  background: var(--ah-hover, rgba(0, 0, 0, 0.25));
  border: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
  overflow-x: auto;
  font-size: 0.78rem;
  line-height: 1.5;
  color: var(--ah-text, #e2e8f0);
  white-space: pre-wrap;
  word-break: break-word;
}

.apphub-guide__link {
  padding: 0;
  border: none;
  background: none;
  color: #93c5fd;
  font: inherit;
  text-align: left;
  text-decoration: underline;
  cursor: pointer;
}

.apphub-guide__link:hover:not(:disabled) {
  color: #bfdbfe;
}

.apphub-guide__link:disabled {
  opacity: 0.6;
  cursor: wait;
}

.apphub-guide__docs-panel {
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
}

.apphub-guide__docs-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.apphub-guide__docs-title {
  margin: 0;
  font-size: 1rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__docs-close {
  flex-shrink: 0;
  padding: 6px 10px;
  border: 1px solid var(--ah-border, rgba(255, 255, 255, 0.1));
  border-radius: 6px;
  background: var(--ah-hover, rgba(255, 255, 255, 0.08));
  color: var(--ah-text, #f0f4fc);
  font-size: 0.8rem;
  cursor: pointer;
}

.apphub-guide__docs-close:hover {
  background: var(--ah-hover-strong, rgba(255, 255, 255, 0.12));
}

.apphub-guide__docs-msg {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ah-text-muted, #94a3b8);
}

.apphub-guide__docs-msg--error {
  color: #fca5a5;
}

.apphub-guide__section--nested {
  margin-bottom: 16px;
}

.apphub-guide__subsection-title {
  margin: 0 0 8px;
  font-size: 0.92rem;
  color: var(--ah-text, #f0f4fc);
}

.apphub-guide__docs-ai-note {
  margin: 16px 0 0;
  padding-top: 12px;
  border-top: 1px dashed var(--ah-border-subtle, rgba(255, 255, 255, 0.08));
  font-size: 0.75rem;
  color: var(--ah-text-muted, #64748b);
  line-height: 1.5;
}

.apphub-guide__docs-ai-link {
  color: #93c5fd;
  text-decoration: underline;
  word-break: break-all;
}

.apphub-guide__docs-ai-link:hover {
  color: #bfdbfe;
}

.apphub-guide__docs-ai-fallback {
  font-size: inherit;
  color: var(--ah-text-secondary, #cbd5e1);
}
</style>
