<script setup lang="ts">
import type { NuxtError } from '#app'

defineProps<{ error: NuxtError }>()
</script>

<template>
  <NuxtLayout>
    <section class="mx-auto max-w-2xl px-5 pt-16 sm:px-8">
      <HeartText text="404" :repeat="12" class="w-40 text-rose" />
      <h1 class="mt-8 text-5xl">
        {{ error.statusCode === 404 ? $t('notFound.title') : error.statusMessage }}
      </h1>
      <p class="mt-4 text-lg">
        {{ $t('notFound.text') }}
      </p>
      <NuxtLink to="/" class="arrow-link mt-8" @click="clearError()">
        {{ $t('notFound.back') }} <span aria-hidden="true">→</span>
      </NuxtLink>
    </section>
  </NuxtLayout>
</template>
