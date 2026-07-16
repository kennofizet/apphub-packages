<template>
  <div v-if="apps.length" class="apphub-taskbar-pins" role="toolbar" :aria-label="ariaLabel">
    <button
      v-for="app in apps"
      :key="app.id"
      type="button"
      class="apphub-taskbar-pins__btn"
      :title="app.name"
      @click="emit('open-app', app)"
    >
      <span class="apphub-taskbar-pins__icon" aria-hidden="true">{{ app.icon }}</span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  apps: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: '' },
})

const emit = defineEmits(['open-app'])
</script>
