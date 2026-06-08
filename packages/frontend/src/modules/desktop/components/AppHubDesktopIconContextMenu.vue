<template>
  <div
    v-if="open"
    class="apphub-icon-menu"
    :style="{ left: `${x}px`, top: `${y}px` }"
    role="menu"
    @click.stop
    @contextmenu.prevent
  >
    <button type="button" class="apphub-icon-menu__item" role="menuitem" @click="emit('open')">
      {{ openLabel }}
    </button>
    <button
      v-if="canRename"
      type="button"
      class="apphub-icon-menu__item"
      role="menuitem"
      @click="emit('rename')"
    >
      {{ renameLabel }}
    </button>
    <div class="apphub-icon-menu__sep" role="separator" />
    <button type="button" class="apphub-icon-menu__item" role="menuitem" @click="emit('info')">
      {{ propertiesLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  canRename: { type: Boolean, default: true },
  openLabel: { type: String, default: '' },
  renameLabel: { type: String, default: '' },
  propertiesLabel: { type: String, default: '' },
})

const emit = defineEmits(['open', 'rename', 'info', 'close'])
</script>
