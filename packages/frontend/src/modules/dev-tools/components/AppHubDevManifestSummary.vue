<template>
  <dl v-if="permissions.length || apiUrls.length" class="apphub-dev-tools__dl apphub-dev-tools__manifest">
    <div v-if="permissions.length" class="apphub-dev-tools__row apphub-dev-tools__row--stack">
      <dt>{{ labels.permissions }}</dt>
      <dd>
        <ul class="apphub-dev-tools__scope-list">
          <li v-for="scope in permissions" :key="scope" class="apphub-dev-tools__scope-item">
            <code>{{ scope }}</code>
          </li>
        </ul>
      </dd>
    </div>
    <div v-if="apiUrls.length" class="apphub-dev-tools__row apphub-dev-tools__row--stack">
      <dt>{{ labels.api_urls }}</dt>
      <dd>
        <ul class="apphub-dev-tools__scope-list">
          <li v-for="url in apiUrls" :key="url" class="apphub-dev-tools__scope-item">
            <code class="apphub-dev-tools__mono">{{ url }}</code>
          </li>
        </ul>
      </dd>
    </div>
  </dl>
</template>

<script setup>
import { computed } from 'vue'
import { resolveAppPermissions } from '../../../utils/resolveAppPermissions.js'
import { resolveAppApiUrls } from '../../../utils/resolveAppApiUrls.js'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
})

const permissions = computed(() => resolveAppPermissions(props.app))
const apiUrls = computed(() => resolveAppApiUrls(props.app))
</script>
