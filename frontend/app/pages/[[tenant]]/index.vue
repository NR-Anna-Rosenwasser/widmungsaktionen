<script setup lang="ts">
const ctx = useStorefrontContext()
</script>

<template>
  <StorefrontHome v-if="ctx?.slug" />
  <TenantPicker v-else />
</template>
