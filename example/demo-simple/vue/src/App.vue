<script setup>
import { computed, ref } from 'vue'

const hello = ref('')
const hasToken = computed(() => new URLSearchParams(window.location.search).has('launch_token'))

function onHello() {
  hello.value = 'Hello from demo-simple-vue!'
}
</script>

<template>
  <main class="app">
    <h1>Demo Simple (Vue)</h1>
    <p class="app__lead">Hosted bundle: Vue 3 + Vite.</p>
    <p class="app__status">
      {{ hasToken ? 'Launch token present in URL.' : 'Ready.' }}
    </p>
    <button type="button" class="app__btn" @click="onHello">Say hello</button>
    <p v-if="hello" class="app__hello">{{ hello }}</p>
  </main>
</template>
