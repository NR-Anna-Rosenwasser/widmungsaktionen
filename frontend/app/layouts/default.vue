<script setup lang="ts">
const ctx = useStorefrontContext()
const { data } = ctx.value?.slug ? await useStorefront() : { data: ref(null) }
const tenant = computed(() => data.value?.tenant)
const text = useStorefrontText(tenant)
const requestUrl = useRequestURL()

// Shop colours, favicon and link preview for every page of the storefront.
useHead({
  htmlAttrs: { style: () => storefrontColorStyle(tenant.value) },
  link: () => tenant.value?.storefront.favicon_url
    ? [{ key: 'icon', rel: 'icon', href: tenant.value.storefront.favicon_url }]
    : [],
})

useSeoMeta({
  description: () => (tenant.value ? text('seo_description') : undefined),
  ogTitle: () => (tenant.value ? text('seo_title') : undefined),
  ogDescription: () => (tenant.value ? text('seo_description') : undefined),
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
