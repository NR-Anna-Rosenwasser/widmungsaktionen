<script setup lang="ts">
const { localeProperties } = useI18n()

useHead({ htmlAttrs: { lang: () => localeProperties.value.language } })
</script>

<template>
  <NuxtRouteAnnouncer />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
