<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="apphub-install-perm-dialog"
      :class="{ 'apphub-install-perm-dialog--light': theme === 'light' }"
      @click.self="onRefuse"
    >
      <div
        class="apphub-install-perm-dialog__panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <header class="apphub-install-perm-dialog__header">
          <span class="apphub-install-perm-dialog__icon" aria-hidden="true">🛡️</span>
          <h3 :id="titleId" class="apphub-install-perm-dialog__title">{{ title }}</h3>
        </header>

        <p class="apphub-install-perm-dialog__message">{{ message }}</p>

        <p v-if="permissionSectionTitle" class="apphub-install-perm-dialog__section-title">
          {{ permissionSectionTitle }}
        </p>

        <ul v-if="permissionLabels.length" class="apphub-install-perm-dialog__list">
          <li
            v-for="(label, index) in permissionLabels"
            :key="permissionScopes[index]"
            class="apphub-install-perm-dialog__item"
          >
            <span class="apphub-install-perm-dialog__item-dot" aria-hidden="true" />
            <div class="apphub-install-perm-dialog__item-body">
              <p class="apphub-install-perm-dialog__item-text">{{ label }}</p>
              <div class="apphub-install-perm-dialog__item-meta">
                <code class="apphub-install-perm-dialog__item-scope">{{ permissionScopes[index] }}</code>
                <span
                  v-if="permissionPending[index]"
                  class="apphub-install-perm-dialog__item-badge"
                >
                  {{ pendingDevLabel }}
                </span>
              </div>
            </div>
          </li>
        </ul>

        <p v-if="apiUrls.length && apiUrlsSectionTitle" class="apphub-install-perm-dialog__section-title">
          {{ apiUrlsSectionTitle }}
        </p>

        <ul v-if="apiUrls.length" class="apphub-install-perm-dialog__list apphub-install-perm-dialog__list--urls">
          <li
            v-for="url in apiUrls"
            :key="url"
            class="apphub-install-perm-dialog__item apphub-install-perm-dialog__item--url"
          >
            <span class="apphub-install-perm-dialog__item-dot" aria-hidden="true" />
            <code class="apphub-install-perm-dialog__item-url">{{ url }}</code>
          </li>
        </ul>

        <p v-if="apiUrls.length && apiUrlsHint" class="apphub-install-perm-dialog__hint apphub-install-perm-dialog__hint--urls">
          {{ apiUrlsHint }}
        </p>

        <p class="apphub-install-perm-dialog__hint">{{ hint }}</p>

        <div class="apphub-install-perm-dialog__actions">
          <button
            type="button"
            class="apphub-install-perm-dialog__btn apphub-install-perm-dialog__btn--primary"
            @click="emit('accept')"
          >
            {{ acceptLabel }}
          </button>
          <button
            type="button"
            class="apphub-install-perm-dialog__btn apphub-install-perm-dialog__btn--ghost"
            @click="onRefuse"
          >
            {{ refuseLabel }}
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
  theme: { type: String, default: 'dark' },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  hint: { type: String, default: '' },
  acceptLabel: { type: String, default: '' },
  refuseLabel: { type: String, default: '' },
  permissionScopes: { type: Array, default: () => [] },
  permissionLabels: { type: Array, default: () => [] },
  permissionPending: { type: Array, default: () => [] },
  pendingDevLabel: { type: String, default: '' },
  permissionSectionTitle: { type: String, default: '' },
  apiUrls: { type: Array, default: () => [] },
  apiUrlsSectionTitle: { type: String, default: '' },
  apiUrlsHint: { type: String, default: '' },
})

const emit = defineEmits(['accept', 'refuse'])

const titleId = computed(() => `apphub-install-perm-title-${Math.random().toString(36).slice(2, 9)}`)

function onRefuse() {
  emit('refuse')
}
</script>
