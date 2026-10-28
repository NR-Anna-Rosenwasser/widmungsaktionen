<script setup lang="ts">
// HTML from the API (Markdown rendered and sanitised there, values escaped by $textHtml).
// Plain elements on purpose: v-html on <component :is> renders empty during SSR.
defineProps<{ html: string | null | undefined, tag?: 'div' | 'span' }>()
</script>

<template>
  <span v-if="html && tag === 'span'" class="rich-text" v-html="html" />
  <div v-else-if="html" class="rich-text" v-html="html" />
</template>
