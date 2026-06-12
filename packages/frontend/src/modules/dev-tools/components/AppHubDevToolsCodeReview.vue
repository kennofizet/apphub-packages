<template>
  <div class="apphub-dev-tools__code-review">
    <header class="apphub-dev-tools__code-review-head">
      <button type="button" class="apphub-dev-tools__btn" @click="emit('close')">
        {{ labels.back }}
      </button>
      <span aria-hidden="true">{{ app.icon || '📦' }}</span>
      <div class="apphub-dev-tools__meta">
        <strong>{{ app.name }}</strong>
        <span class="apphub-dev-tools__slug">{{ app.slug }}</span>
        <span v-if="app.pending_version" class="apphub-dev-tools__slug">
          · v{{ app.version }} → v{{ app.pending_version }}
        </span>
        <span v-else-if="app.version" class="apphub-dev-tools__slug"> · v{{ app.version }}</span>
        <span class="apphub-dev-tools__badge apphub-dev-tools__badge--draft">
          {{ app.pending_version ? labels.pending_version : labels.status_draft }}
        </span>
      </div>
      <div class="apphub-dev-tools__actions">
        <button
          type="button"
          class="apphub-dev-tools__btn apphub-dev-tools__btn--danger"
          :disabled="acting"
          @click="emit('reject')"
        >
          {{ labels.reject }}
        </button>
        <button
          type="button"
          class="apphub-dev-tools__btn apphub-dev-tools__btn--primary"
          :disabled="acting"
          @click="emit('approve')"
        >
          {{ acting ? labels.approving : labels.approve }}
        </button>
      </div>
    </header>

    <div v-if="inspect?.has_baseline" class="apphub-dev-tools__code-review-summary">
      <span>{{ labels.diff_against }} v{{ inspect.baseline_version }}</span>
      <span v-if="changeCounts.modified" class="apphub-dev-tools__diff-stat apphub-dev-tools__diff-stat--modified">
        {{ changeCounts.modified }} {{ labels.file_modified }}
      </span>
      <span v-if="changeCounts.added" class="apphub-dev-tools__diff-stat apphub-dev-tools__diff-stat--added">
        {{ changeCounts.added }} {{ labels.file_added }}
      </span>
      <span v-if="changeCounts.deleted" class="apphub-dev-tools__diff-stat apphub-dev-tools__diff-stat--deleted">
        {{ changeCounts.deleted }} {{ labels.file_deleted }}
      </span>
    </div>

    <div class="apphub-dev-tools__code-review-body">
      <aside class="apphub-dev-tools__code-review-sidebar">
        <div class="apphub-dev-tools__code-review-sidebar-head">
          <span>{{ labels.files_title }}</span>
          <label v-if="inspect?.has_baseline" class="apphub-dev-tools__filter-changed">
            <input v-model="changedOnly" type="checkbox" />
            {{ labels.changed_only }}
          </label>
        </div>
        <ul class="apphub-dev-tools__tree apphub-dev-tools__tree--scroll">
          <li
            v-for="entry in visibleFiles"
            :key="entry.path"
            class="apphub-dev-tools__tree-item"
            :class="[
              `apphub-dev-tools__tree-item--${entry.status}`,
              { 'apphub-dev-tools__tree-item--active': selectedFile === entry.path },
            ]"
            :title="entry.path"
            @click="emit('select-file', entry.path)"
          >
            <span class="apphub-dev-tools__file-status" :class="`apphub-dev-tools__file-status--${entry.status}`">
              {{ statusLetter(entry.status) }}
            </span>
            <span class="apphub-dev-tools__file-path">{{ entry.path }}</span>
          </li>
        </ul>
      </aside>

      <main class="apphub-dev-tools__code-review-main">
        <p v-if="fileContent?.loading" class="apphub-dev-tools__code-empty">{{ labels.code_loading }}</p>
        <p v-else-if="!selectedFile" class="apphub-dev-tools__code-empty">{{ labels.code_empty }}</p>
        <p v-else-if="fileContent?.error" class="apphub-dev-tools__code-empty">{{ fileContent.error }}</p>
        <template v-else>
          <div class="apphub-dev-tools__code-review-file-head">
            <strong>{{ selectedFile }}</strong>
            <span
              v-if="fileContent?.change_status"
              class="apphub-dev-tools__file-status apphub-dev-tools__file-status--badge"
              :class="`apphub-dev-tools__file-status--${fileContent.change_status}`"
            >
              {{ statusLabel(fileContent.change_status) }}
            </span>
          </div>
          <AppHubDevToolsCodeDiff
            :old-content="fileContent?.old_content ?? ''"
            :new-content="fileContent?.content ?? ''"
            :change-status="fileContent?.change_status ?? 'unchanged'"
            :empty-label="labels.code_empty"
            :truncated-hint="truncatedHint"
          />
        </template>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import AppHubDevToolsCodeDiff from './AppHubDevToolsCodeDiff.vue'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  inspect: { type: Object, default: null },
  fileContent: { type: Object, default: null },
  selectedFile: { type: String, default: '' },
  acting: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'select-file', 'approve', 'reject'])

const changedOnly = ref(true)

const fileEntries = computed(() => {
  if (Array.isArray(props.inspect?.file_entries) && props.inspect.file_entries.length) {
    return props.inspect.file_entries
  }
  return (props.inspect?.files ?? []).map((path) => ({ path, status: 'unchanged' }))
})

const changeCounts = computed(() => {
  const counts = { modified: 0, added: 0, deleted: 0, unchanged: 0 }
  for (const entry of fileEntries.value) {
    if (entry.status in counts) counts[entry.status] += 1
  }
  return counts
})

const visibleFiles = computed(() => {
  if (!changedOnly.value || !props.inspect?.has_baseline) {
    return fileEntries.value
  }
  return fileEntries.value.filter((entry) => entry.status !== 'unchanged')
})

const truncatedHint = computed(() => {
  const parts = []
  if (props.fileContent?.truncated) parts.push(props.labels.code_truncated)
  if (props.fileContent?.old_truncated) parts.push(props.labels.code_truncated_old)
  return parts.join(' ')
})

function statusLetter(status) {
  if (status === 'modified') return 'M'
  if (status === 'added') return 'A'
  if (status === 'deleted') return 'D'
  return 'U'
}

function statusLabel(status) {
  const map = {
    modified: props.labels.file_modified,
    added: props.labels.file_added,
    deleted: props.labels.file_deleted,
    unchanged: props.labels.file_unchanged,
  }
  return map[status] ?? status
}
</script>
