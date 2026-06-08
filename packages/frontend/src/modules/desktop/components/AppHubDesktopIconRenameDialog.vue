<template>
  <div v-if="open" class="apphub-icon-rename" @click.self="emit('cancel')">
    <div class="apphub-icon-rename__panel" role="dialog" aria-modal="true">
      <h3 class="apphub-icon-rename__title">{{ title }}</h3>
      <label class="apphub-icon-rename__label">
        <span>{{ nameLabel }}</span>
        <input
          ref="inputRef"
          v-model="draft"
          type="text"
          class="apphub-icon-rename__input"
          maxlength="64"
          @keydown.enter="onSave"
          @keydown.esc="emit('cancel')"
        />
      </label>
      <p v-if="error" class="apphub-icon-rename__error">{{ error }}</p>
      <div class="apphub-icon-rename__actions">
        <button type="button" class="apphub-icon-rename__btn apphub-icon-rename__btn--primary" @click="onSave">
          {{ saveLabel }}
        </button>
        <button type="button" class="apphub-icon-rename__btn" @click="emit('cancel')">
          {{ cancelLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  nameLabel: { type: String, default: '' },
  initialName: { type: String, default: '' },
  saveLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['save', 'cancel'])

const draft = ref('')
const inputRef = ref(null)

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    draft.value = props.initialName
    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select()
  },
)

function onSave() {
  emit('save', draft.value.trim())
}
</script>
