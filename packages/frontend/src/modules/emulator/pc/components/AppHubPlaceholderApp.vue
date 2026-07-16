<template>
  <div class="apphub-placeholder">
    <p class="apphub-placeholder__icon">{{ icon }}</p>
    <h3>{{ title }}</h3>
    <p class="apphub-placeholder__hint">Demo app shell — connect backend launch URL later.</p>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'App' },
  icon: { type: String, default: '📦' },
})
</script>
