<template>
  <div class="apphub-drop-layer">
    <div class="apphub-drop-layer__glow" />
    <div class="apphub-drop-layer__hint">
      <span class="apphub-drop-layer__hint-icon">⬇</span>
      <p>{{ hint }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  hint: { type: String, default: '' },
})
</script>
