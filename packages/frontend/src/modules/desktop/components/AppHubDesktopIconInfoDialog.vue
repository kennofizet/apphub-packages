<template>
  <div v-if="open" class="apphub-icon-info" @click.self="emit('close')">
    <div class="apphub-icon-info__panel" role="dialog" aria-modal="true">
      <header class="apphub-icon-info__header">
        <span class="apphub-icon-info__icon">{{ app?.icon }}</span>
        <h3 class="apphub-icon-info__title">{{ title }}</h3>
      </header>
      <dl class="apphub-icon-info__list">
        <div v-for="row in rows" :key="row.label" class="apphub-icon-info__row">
          <dt>{{ row.label }}</dt>
          <dd>{{ row.value }}</dd>
        </div>
      </dl>
      <footer class="apphub-icon-info__actions">
        <button type="button" class="apphub-icon-info__btn" @click="emit('close')">
          {{ closeLabel }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  app: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  closeLabel: { type: String, default: '' },
})

const emit = defineEmits(['close'])
</script>
