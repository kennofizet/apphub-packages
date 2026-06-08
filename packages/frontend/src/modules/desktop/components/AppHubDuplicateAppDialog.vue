<template>
  <div v-if="open" class="apphub-dup-dialog" @click.self="onCancel">
    <div class="apphub-dup-dialog__panel" role="dialog" aria-modal="true">
      <h3 class="apphub-dup-dialog__title">{{ title }}</h3>
      <p class="apphub-dup-dialog__message">{{ message }}</p>
      <p class="apphub-dup-dialog__hint">{{ hint }}</p>
      <div class="apphub-dup-dialog__actions">
        <button type="button" class="apphub-dup-dialog__btn apphub-dup-dialog__btn--primary" @click="emit('replace')">
          {{ replaceLabel }}
        </button>
        <button type="button" class="apphub-dup-dialog__btn" @click="emit('keep')">
          {{ keepLabel }}
        </button>
        <button type="button" class="apphub-dup-dialog__btn apphub-dup-dialog__btn--ghost" @click="onCancel">
          {{ cancelLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  hint: { type: String, default: '' },
  replaceLabel: { type: String, default: '' },
  keepLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
})

const emit = defineEmits(['replace', 'keep', 'cancel'])

function onCancel() {
  emit('cancel')
}
</script>
