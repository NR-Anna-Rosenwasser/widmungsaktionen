<script setup lang="ts">
// leadHtml: a Markdown text from $textHtml.
defineProps<{ title: string, leadHtml?: string }>()
</script>

<template>
  <div>
    <h1 class="text-4xl sm:text-5xl">
      {{ title }}
    </h1>
    <RichText :html="leadHtml" class="mt-3 text-lg text-navy-soft" />
  </div>
</template>
