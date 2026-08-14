<script setup>
/**
 * Per-skin structural chrome around theme mix glyphs (or catalog icons when skin is empty).
 * Each skin uses a different DOM tree so themes read as distinct UI systems.
 */
defineProps({
  skin: { type: String, default: '' },
  /** desktop | tray | pin */
  tone: { type: String, default: 'desktop' },
})
</script>

<template>
  <span
    v-if="skin"
    class="apphub-skin-chrome"
    :class="[`apphub-skin-chrome--${skin}`, `apphub-skin-chrome--tone-${tone}`]"
    aria-hidden="true"
  >
    <!-- cyber: HUD plate + corner ticks + scan lip -->
    <template v-if="skin === 'cyber'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--cyber">
        <i class="apphub-skin-chrome__tick apphub-skin-chrome__tick--tl" />
        <i class="apphub-skin-chrome__tick apphub-skin-chrome__tick--tr" />
        <i class="apphub-skin-chrome__tick apphub-skin-chrome__tick--bl" />
        <i class="apphub-skin-chrome__tick apphub-skin-chrome__tick--br" />
        <i class="apphub-skin-chrome__scanlip" />
        <span class="apphub-skin-chrome__face"><slot /></span>
        <i class="apphub-skin-chrome__hud-tag">FX</i>
      </span>
    </template>

    <!-- solar: outer ring + rays + round well -->
    <template v-else-if="skin === 'solar'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--solar">
        <i class="apphub-skin-chrome__ring" />
        <i class="apphub-skin-chrome__ray apphub-skin-chrome__ray--n" />
        <i class="apphub-skin-chrome__ray apphub-skin-chrome__ray--e" />
        <i class="apphub-skin-chrome__ray apphub-skin-chrome__ray--s" />
        <i class="apphub-skin-chrome__ray apphub-skin-chrome__ray--w" />
        <span class="apphub-skin-chrome__face apphub-skin-chrome__face--round"><slot /></span>
      </span>
    </template>

    <!-- light: frosted card + soft rim glow -->
    <template v-else-if="skin === 'light'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--light">
        <i class="apphub-skin-chrome__soft-glow" />
        <i class="apphub-skin-chrome__glass-rim" />
        <span class="apphub-skin-chrome__face apphub-skin-chrome__face--light"><slot /></span>
      </span>
    </template>

    <!-- ink: stamp plate + dashed rule + caption bar -->
    <template v-else-if="skin === 'ink'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--ink">
        <span class="apphub-skin-chrome__stamp">
          <i class="apphub-skin-chrome__blot" />
          <span class="apphub-skin-chrome__face"><slot /></span>
        </span>
        <i class="apphub-skin-chrome__rule" />
        <i class="apphub-skin-chrome__caption">INK</i>
      </span>
    </template>

    <!-- ember: asymmetric bevel + heat bar -->
    <template v-else-if="skin === 'ember'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--ember">
        <i class="apphub-skin-chrome__bevel" />
        <i class="apphub-skin-chrome__heat" />
        <span class="apphub-skin-chrome__face apphub-skin-chrome__face--ember"><slot /></span>
      </span>
    </template>

    <!-- ocean: soft capsule shell + foam lip -->
    <template v-else-if="skin === 'ocean'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--ocean">
        <span class="apphub-skin-chrome__shell">
          <i class="apphub-skin-chrome__foam" />
          <span class="apphub-skin-chrome__face"><slot /></span>
        </span>
      </span>
    </template>

    <!-- forest: leaf notches + moss pad -->
    <template v-else-if="skin === 'forest'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--forest">
        <i class="apphub-skin-chrome__notch apphub-skin-chrome__notch--a" />
        <i class="apphub-skin-chrome__notch apphub-skin-chrome__notch--b" />
        <span class="apphub-skin-chrome__face"><slot /></span>
      </span>
    </template>

    <!-- dusk: serif plaque -->
    <template v-else-if="skin === 'dusk'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--dusk">
        <span class="apphub-skin-chrome__plaque">
          <span class="apphub-skin-chrome__face"><slot /></span>
        </span>
      </span>
    </template>

    <!-- aurora: dual glow rails -->
    <template v-else-if="skin === 'aurora'">
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--aurora">
        <i class="apphub-skin-chrome__rail apphub-skin-chrome__rail--l" />
        <i class="apphub-skin-chrome__rail apphub-skin-chrome__rail--r" />
        <span class="apphub-skin-chrome__face"><slot /></span>
      </span>
    </template>

    <!-- classic / fallback -->
    <template v-else>
      <span class="apphub-skin-chrome__plate apphub-skin-chrome__plate--classic">
        <span class="apphub-skin-chrome__face"><slot /></span>
      </span>
    </template>
  </span>

  <span v-else class="apphub-skin-chrome apphub-skin-chrome--none">
    <slot />
  </span>
</template>
