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
      v-if="showPin"
      type="button"
      class="apphub-icon-menu__item"
      role="menuitem"
      @click="emit('pin')"
    >
      {{ pinLabel }}
    </button>
    <button
      v-if="showFavorite"
      type="button"
      class="apphub-icon-menu__item"
      role="menuitem"
      @click="emit('favorite')"
    >
      {{ favoriteLabel }}
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
    <button
      v-if="showUninstall"
      type="button"
      class="apphub-icon-menu__item apphub-icon-menu__item--danger"
      role="menuitem"
      @click="emit('uninstall')"
    >
      {{ uninstallLabel }}
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
  showPin: { type: Boolean, default: false },
  showFavorite: { type: Boolean, default: false },
  showUninstall: { type: Boolean, default: false },
  openLabel: { type: String, default: '' },
  pinLabel: { type: String, default: '' },
  favoriteLabel: { type: String, default: '' },
  renameLabel: { type: String, default: '' },
  uninstallLabel: { type: String, default: '' },
  propertiesLabel: { type: String, default: '' },
})

const emit = defineEmits(['open', 'pin', 'favorite', 'uninstall', 'rename', 'info', 'close'])
</script>
