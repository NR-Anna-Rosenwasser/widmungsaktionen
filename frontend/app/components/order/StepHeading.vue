<script setup lang="ts">
defineProps<{ title: string, lead?: string }>()
</script>

<template>
  <div>
    <h1 class="text-4xl sm:text-5xl">
      {{ title }}
    </h1>
    <p v-if="lead" class="mt-3 text-lg text-navy-soft">
      {{ lead }}
    </p>
  </div>
</template>
