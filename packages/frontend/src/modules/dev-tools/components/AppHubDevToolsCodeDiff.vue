<template>
  <div class="apphub-code-diff">
    <div v-if="!rows.length" class="apphub-code-diff__empty">{{ emptyLabel }}</div>
    <div v-else class="apphub-code-diff__table" role="table">
      <div
        v-for="(row, index) in rows"
        :key="index"
        class="apphub-code-diff__row"
        :class="`apphub-code-diff__row--${row.type}`"
        role="row"
      >
        <span class="apphub-code-diff__gutter apphub-code-diff__gutter--old" aria-hidden="true">
          {{ row.oldLine ?? '' }}
        </span>
        <span class="apphub-code-diff__gutter apphub-code-diff__gutter--new" aria-hidden="true">
          {{ row.newLine ?? '' }}
        </span>
        <span class="apphub-code-diff__sign" aria-hidden="true">{{ signFor(row.type) }}</span>
        <code class="apphub-code-diff__code">{{ row.text || ' ' }}</code>
      </div>
    </div>
    <p v-if="truncatedHint" class="apphub-code-diff__hint">{{ truncatedHint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { diffLines } from '../../../utils/lineDiff.js'

const props = defineProps({
  oldContent: { type: String, default: '' },
  newContent: { type: String, default: '' },
  changeStatus: { type: String, default: 'unchanged' },
  emptyLabel: { type: String, default: '' },
  truncatedHint: { type: String, default: '' },
})

const rows = computed(() => {
  if (props.changeStatus === 'added') {
    return splitWithNumbers(props.newContent, 'insert')
  }
  if (props.changeStatus === 'deleted') {
    return splitWithNumbers(props.oldContent, 'delete')
  }
  if (props.changeStatus === 'unchanged') {
    return splitWithNumbers(props.newContent, 'equal')
  }
  return diffLines(props.oldContent, props.newContent)
})

function splitWithNumbers(text, type) {
  const normalized = String(text ?? '').replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
  if (normalized === '') return []

  const lines = normalized.split('\n')
  if (lines.length > 1 && lines[lines.length - 1] === '') lines.pop()

  return lines.map((line, index) => ({
    type,
    text: line,
    oldLine: type === 'delete' || type === 'equal' ? index + 1 : null,
    newLine: type === 'insert' || type === 'equal' ? index + 1 : null,
  }))
}

function signFor(type) {
  if (type === 'insert') return '+'
  if (type === 'delete') return '-'
  return ' '
}
</script>
