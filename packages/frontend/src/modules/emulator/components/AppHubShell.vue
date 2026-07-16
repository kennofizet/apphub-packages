<template>
  <AppHubMobile v-if="deviceMode.state.mode === 'mobile'" v-bind="$attrs" />
  <AppHubDesktop v-else v-bind="$attrs" />
</template>

<script setup>
import { useDeviceMode } from '../../responsive/index.js'
import AppHubDesktop from '../pc/components/AppHubDesktop.vue'
import AppHubMobile from '../mobile/components/AppHubMobile.vue'

const deviceMode = useDeviceMode()
</script>
