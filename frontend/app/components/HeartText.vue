<script setup lang="ts">
/** Text running along a heart outline, as on the book covers. */
const props = withDefaults(defineProps<{ text: string, repeat?: number }>(), { repeat: 3 })
const id = useId()
const content = computed(() => Array.from({ length: props.repeat }, () => props.text).join(' · '))

// Stretch the text to one lap around the heart, starting and ending at the top dip
// where glyphs would otherwise collide.
const path = useTemplateRef<SVGPathElement>('path')
const length = ref<number>()
onMounted(() => {
  length.value = path.value?.getTotalLength()
})
</script>

<template>
  <svg viewBox="0 0 220 200" aria-hidden="true" class="overflow-visible">
    <path
      :id="id"
      ref="path"
      d="M110 45 A 47 47 0 0 1 205 68 C 205 110, 165 145, 110 185 C 55 145, 15 110, 15 68 A 47 47 0 0 1 110 45 Z"
      fill="none"
    />
    <text
      class="font-display"
      font-size="15"
      font-weight="600"
      fill="currentColor"
    >
      <textPath
        :href="`#${id}`"
        startOffset="10"
        :textLength="length ? length - 22 : undefined"
        lengthAdjust="spacing"
      >{{ content }}</textPath>
    </text>
  </svg>
</template>
