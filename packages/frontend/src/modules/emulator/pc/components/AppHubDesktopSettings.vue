<template>
  <div class="apphub-desktop-settings">
    <label v-if="showThemeToggle" class="apphub-desktop-settings__row">
      <input
        type="checkbox"
        :checked="theme === 'light'"
        @change="emit('update:theme', $event.target.checked ? 'light' : 'dark')"
      />
      <span>{{ themeLabel }}</span>
    </label>
    <label class="apphub-desktop-settings__row">
      <input type="checkbox" :checked="snapToGrid" @change="emit('update:snapToGrid', $event.target.checked)" />
      <span>{{ snapLabel }}</span>
    </label>
  </div>
</template>

<script setup>
defineProps({
  theme: { type: String, default: 'dark' },
  themeLabel: { type: String, default: '' },
  showThemeToggle: { type: Boolean, default: true },
  snapToGrid: { type: Boolean, default: true },
  snapLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:theme', 'update:snapToGrid'])
</script>
