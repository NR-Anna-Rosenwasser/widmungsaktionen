<script setup lang="ts">
const ctx = useStorefrontContext()
const { data } = ctx.value?.slug ? await useStorefront() : { data: ref(null) }
const tenant = computed(() => data.value?.tenant)
const { $text: text } = useNuxtApp()
const requestUrl = useRequestURL()

// Shop colours, favicon and link preview for every page of the storefront.
useHead({
  htmlAttrs: { style: () => storefrontColorStyle(tenant.value) },
  link: () => tenant.value?.storefront.favicon_url
    ? [{ key: 'icon', rel: 'icon', href: tenant.value.storefront.favicon_url }]
    : [],
})

useSeoMeta({
  description: () => (tenant.value ? text('home.seoDescription') : undefined),
  ogTitle: () => (tenant.value ? text('home.seoTitle') : undefined),
  ogDescription: () => (tenant.value ? text('home.seoDescription') : undefined),
  ogImage: () => tenant.value?.storefront.seo.image_url ?? undefined,
  ogUrl: () => requestUrl.href,
  ogType: 'website',
  ogSiteName: () => tenant.value?.name,
  twitterCard: () => (tenant.value?.storefront.seo.image_url ? 'summary_large_image' : 'summary'),
})
</script>

<template>
  <div class="flex min-h-dvh flex-col">
    <AppHeader />
    <main class="flex-1">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
