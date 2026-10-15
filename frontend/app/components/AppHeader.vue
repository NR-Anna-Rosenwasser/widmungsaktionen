<script setup lang="ts">
const ctx = useStorefrontContext()
const path = useStorefrontPath()
const { data } = ctx.value?.slug ? await useStorefront() : { data: ref(null) }
const { locale, locales, setLocale } = useI18n()
</script>

<template>
  <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
    <NuxtLink :to="path('/')" class="font-display text-lg font-bold tracking-tight">
      {{ data?.tenant.name ?? $t('picker.title') }}
    </NuxtLink>
    <nav :aria-label="$t('nav.language')" class="flex gap-1 text-sm font-semibold">
      <button
        v-for="l in locales"
        :key="l.code"
        type="button"
        class="rounded-sm px-2 py-1 uppercase transition-colors"
        :class="l.code === locale ? 'bg-blush' : 'hover:bg-blush-light'"
        :aria-pressed="l.code === locale"
        :lang="l.language"
        :title="l.name"
        @click="setLocale(l.code)"
      >
        {{ l.code }}
      </button>
    </nav>
  </header>
</template>
