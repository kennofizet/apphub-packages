<script setup>
import { computed } from 'vue'
import { useAppHubModuleStore } from '../composables/useAppHubHostApi.js'
import { resolveCatalogIconEmoji, resolveCatalogIconSrc } from '../utils/catalogIcon.js'

const props = defineProps({
  app: { type: Object, required: true },
  apiBase: { type: String, default: '' },
  imgClass: { type: String, default: '' },
  emojiClass: { type: String, default: '' },
})

const hubStore = useAppHubModuleStore()
const resolvedApiBase = computed(() => props.apiBase || hubStore?.credentials?.backendUrl || '')
const src = computed(() => resolveCatalogIconSrc(props.app, resolvedApiBase.value))
const emoji = computed(() => resolveCatalogIconEmoji(props.app))
const alt = computed(() => (typeof props.app?.name === 'string' && props.app.name.trim()) || props.app?.slug || 'App')
</script>

<template>
  <img
    v-if="src"
    :src="src"
    :alt="alt"
    class="apphub-catalog-icon apphub-catalog-icon--img"
    :class="imgClass"
  >
  <span
    v-else
    class="apphub-catalog-icon apphub-catalog-icon--emoji"
    :class="emojiClass"
  >{{ emoji }}</span>
</template>
