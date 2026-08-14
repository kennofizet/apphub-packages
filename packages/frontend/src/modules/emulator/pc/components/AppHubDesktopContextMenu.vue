<template>
  <div
    v-if="open"
    class="apphub-icon-menu apphub-desktop-context-menu"
    :style="{ left: `${x}px`, top: `${y}px` }"
    role="menu"
    @click.stop
    @contextmenu.prevent
  >
    <button type="button" class="apphub-icon-menu__item" role="menuitem" @click="emit('settings')">
      {{ settingsLabel }}
    </button>
    <div class="apphub-icon-menu__sep" role="separator" />
    <button
      type="button"
      class="apphub-icon-menu__item"
      :class="{ 'apphub-icon-menu__item--checked': snapToGrid }"
      role="menuitemcheckbox"
      :aria-checked="snapToGrid"
      @click="emit('toggle-snap')"
    >
      {{ snapLabel }}
    </button>
    <button
      v-if="showThemeToggle"
      type="button"
      class="apphub-icon-menu__item"
      :class="{ 'apphub-icon-menu__item--checked': lightMode }"
      role="menuitemcheckbox"
      :aria-checked="lightMode"
      @click="emit('toggle-theme')"
    >
      {{ themeLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  snapToGrid: { type: Boolean, default: false },
  lightMode: { type: Boolean, default: false },
  showThemeToggle: { type: Boolean, default: true },
  settingsLabel: { type: String, default: '' },
  snapLabel: { type: String, default: '' },
  themeLabel: { type: String, default: '' },
})

const emit = defineEmits(['settings', 'toggle-snap', 'toggle-theme', 'close'])
</script>
