<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="apphub-confirm"
      @click.self="onBackdrop"
    >
      <div
        class="apphub-confirm__panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <h3 :id="titleId" class="apphub-confirm__title">{{ title }}</h3>
        <p v-if="message" class="apphub-confirm__message">{{ message }}</p>
        <p v-if="hint" class="apphub-confirm__hint">{{ hint }}</p>
        <div class="apphub-confirm__actions">
          <button
            v-if="!alertOnly"
            type="button"
            class="apphub-confirm__btn apphub-confirm__btn--ghost"
            :disabled="busy"
            @click="emit('cancel')"
          >
            {{ cancelLabel }}
          </button>
          <button
            type="button"
            class="apphub-confirm__btn"
            :class="confirmButtonClass"
            :disabled="busy"
            @click="emit('confirm')"
          >
            {{ busy ? busyLabel : confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  hint: { type: String, default: '' },
  confirmLabel: { type: String, default: 'OK' },
  cancelLabel: { type: String, default: 'Cancel' },
  busyLabel: { type: String, default: '…' },
  danger: { type: Boolean, default: false },
  alertOnly: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const titleId = `apphub-confirm-title-${Math.random().toString(36).slice(2, 9)}`

const confirmButtonClass = computed(() => {
  if (props.alertOnly) return 'apphub-confirm__btn--primary'
  if (props.danger) return 'apphub-confirm__btn--danger'
  return 'apphub-confirm__btn--primary'
})

function onBackdrop() {
  if (!props.busy) emit('cancel')
}
</script>
