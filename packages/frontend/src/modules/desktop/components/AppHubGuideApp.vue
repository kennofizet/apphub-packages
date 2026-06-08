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
            <li v-for="(line, i) in block.lines" :key="i">{{ line }}</li>
          </ul>
          <pre v-if="block.code" class="apphub-guide__code"><code>{{ block.code }}</code></pre>
        </section>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { t } from '../../../i18n/index.js'

const tab = ref('user')
const lang = computed(() => resolveLang(inject('apphubOptions', {})?.language, 'vi'))

const labels = computed(() => ({
  title: t('guide_app_title', lang.value),
  subtitle: t('guide_app_subtitle', lang.value),
  tab_user: t('guide_tab_user', lang.value),
  tab_dev: t('guide_tab_dev', lang.value),
}))

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
    title: t('guide_dev_bridge_title', lang.value),
    lines: [
      t('guide_dev_bridge_1', lang.value),
      t('guide_dev_bridge_2', lang.value),
      t('guide_dev_bridge_3', lang.value),
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
    ],
  },
  {
    title: t('guide_dev_docs_title', lang.value),
    lines: [
      t('guide_dev_docs_1', lang.value),
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
  min-height: 100%;
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
</style>
