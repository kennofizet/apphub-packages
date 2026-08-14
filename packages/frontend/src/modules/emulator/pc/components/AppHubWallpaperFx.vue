<script setup>
defineProps({
  skin: {
    type: String,
    default: 'classic',
  },
})
</script>

<template>
  <div class="apphub-desktop__wallpaper-fill" aria-hidden="true" />

  <!-- classic: sketch + three orbs -->
  <template v-if="skin === 'classic'">
    <span class="apphub-desktop__fx-sketch" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- light: soft clouds + sunbeams + sheen (distinct from classic) -->
  <template v-else-if="skin === 'light'">
    <div class="apphub-desktop__fx-clouds" aria-hidden="true">
      <span v-for="n in 5" :key="'cloud' + n" />
    </div>
    <span class="apphub-desktop__fx-beam apphub-desktop__fx-beam--a" aria-hidden="true" />
    <span class="apphub-desktop__fx-beam apphub-desktop__fx-beam--b" aria-hidden="true" />
    <span class="apphub-desktop__fx-sheen" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
  </template>

  <!-- cyber: grid rails + scanline + neon bars -->
  <template v-else-if="skin === 'cyber'">
    <div class="apphub-desktop__fx-grid" aria-hidden="true">
      <span v-for="n in 8" :key="'cv' + n" class="apphub-desktop__fx-grid-v" />
      <span v-for="n in 6" :key="'ch' + n" class="apphub-desktop__fx-grid-h" />
    </div>
    <span class="apphub-desktop__fx-scan" aria-hidden="true" />
    <span class="apphub-desktop__fx-bar apphub-desktop__fx-bar--a" aria-hidden="true" />
    <span class="apphub-desktop__fx-bar apphub-desktop__fx-bar--b" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- solar: ray spokes + sun core -->
  <template v-else-if="skin === 'solar'">
    <div class="apphub-desktop__fx-rays" aria-hidden="true">
      <i v-for="n in 12" :key="'ray' + n" />
    </div>
    <span class="apphub-desktop__fx-sun" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- ink: ruled paper + gutter + margin + ink blots -->
  <template v-else-if="skin === 'ink'">
    <div class="apphub-desktop__fx-ruled" aria-hidden="true">
      <span v-for="n in 16" :key="'rule' + n" />
    </div>
    <span class="apphub-desktop__fx-gutter" aria-hidden="true" />
    <span class="apphub-desktop__fx-margin" aria-hidden="true" />
    <div class="apphub-desktop__fx-blots" aria-hidden="true">
      <span v-for="n in 5" :key="'blot' + n" />
    </div>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
  </template>

  <!-- ember: mesa bands -->
  <template v-else-if="skin === 'ember'">
    <div class="apphub-desktop__fx-bands" aria-hidden="true">
      <span v-for="n in 5" :key="'band' + n" />
    </div>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- ocean: wave layers -->
  <template v-else-if="skin === 'ocean'">
    <div class="apphub-desktop__fx-waves" aria-hidden="true">
      <span v-for="n in 4" :key="'wave' + n" />
    </div>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- forest: canopy blobs -->
  <template v-else-if="skin === 'forest'">
    <div class="apphub-desktop__fx-canopy" aria-hidden="true">
      <span v-for="n in 6" :key="'leaf' + n" />
    </div>
    <span class="apphub-desktop__fx-sketch" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
  </template>

  <!-- dusk: horizon band -->
  <template v-else-if="skin === 'dusk'">
    <span class="apphub-desktop__fx-horizon" aria-hidden="true" />
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>

  <!-- aurora: ribbon arcs -->
  <template v-else>
    <div class="apphub-desktop__fx-ribbons" aria-hidden="true">
      <span v-for="n in 3" :key="'rib' + n" />
    </div>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--a" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--b" aria-hidden="true"><i /></span>
    <span class="apphub-desktop__fx-orb apphub-desktop__fx-orb--c" aria-hidden="true"><i /></span>
  </template>
</template>
