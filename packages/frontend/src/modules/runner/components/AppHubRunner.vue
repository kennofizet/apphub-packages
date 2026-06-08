<template>
  <div class="apphub-runner">
    <p v-if="loading" class="apphub-runner__msg">{{ labels.loading }}</p>
    <p v-else-if="error" class="apphub-runner__error">{{ error }}</p>
    <iframe
      v-else-if="launchUrl"
      :src="launchUrl"
      class="apphub-runner__frame"
      :title="slug"
      sandbox="allow-scripts allow-forms allow-popups"
      referrerpolicy="strict-origin-when-cross-origin"
    />
    <AppHubPlaceholderApp v-else :title="slug" :icon="icon" />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { useAppHubHostApi } from '../../../composables/useAppHubHostApi.js'
import { t } from '../../../i18n/index.js'
import { isAllowedLaunchUrl, resolveLaunchUrl } from '../../../utils/launchUrl.js'
import AppHubPlaceholderApp from '../../desktop/components/AppHubPlaceholderApp.vue'

const props = defineProps({
  slug: { type: String, required: true },
  language: { type: String, default: 'vi' },
  icon: { type: String, default: '📦' },
})

const api = useAppHubHostApi()
const moduleOptions = inject('apphubOptions', {})
const allowedOrigins = computed(() => moduleOptions?.allowedRuntimeOrigins ?? [])

const loading = ref(false)
const error = ref('')
const launchUrl = ref('')

const labels = {
  loading: t('runner_loading', props.language),
}

onMounted(async () => {
  if (!api?.launch || !props.slug) return
  loading.value = true
  error.value = ''
  try {
    const res = await api.launch(props.slug)
    const candidate = resolveLaunchUrl(res?.data)
    if (!candidate) {
      error.value = t('error_generic', props.language)
      return
    }
    if (!isAllowedLaunchUrl(candidate, allowedOrigins.value)) {
      error.value = t('error_generic', props.language)
      return
    }
    launchUrl.value = candidate
  } catch {
    error.value = t('error_generic', props.language)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.apphub-runner {
  width: 100%;
  min-height: 400px;
  background: #0f172a;
}
.apphub-runner__frame {
  width: 100%;
  min-height: 70vh;
  border: none;
  background: #000;
}
.apphub-runner__msg,
.apphub-runner__error {
  padding: 24px;
  text-align: center;
  color: #94a3b8;
}
.apphub-runner__error {
  color: #f87171;
}
</style>
