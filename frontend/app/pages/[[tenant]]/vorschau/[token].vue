<script setup lang="ts">
import type { StorefrontResponse } from '#shared/types/api'

// Secret link of an Aktion in testing mode: remember the token for this
// browser session, then continue to the shop (or ?weiter=, e.g. a draft).
const route = useRoute()
const path = useStorefrontPath()
const ctx = useStorefrontContext()
const api = useApi()
const preview = usePreviewCookie()

const token = String(route.params.token)
const { data } = await useAsyncData(`preview:${token}`, () =>
  api<StorefrontResponse>(`/api/tenants/${ctx.value!.slug}`, { headers: { 'x-widmungen-preview': token } }))

const valid = !!data.value?.preview
preview.value = valid ? token : null

if (valid) {
  const next = typeof route.query.weiter === 'string' && route.query.weiter.startsWith('/') ? route.query.weiter : '/'
  // External: a fresh page load, so everything is fetched with the preview.
  await navigateTo(path(next), { replace: true, external: true })
}

const { t } = useI18n()
useSeoMeta({ title: () => t('testMode.title'), robots: 'noindex' })
</script>

<template>
  <section class="mx-auto max-w-2xl px-5 pt-16 sm:px-8">
    <h1 class="text-4xl">
      {{ $t('testMode.title') }}
    </h1>
    <p class="mt-4 text-lg">
      {{ $t('testMode.invalid') }}
    </p>
    <NuxtLink :to="path('/')" class="arrow-link mt-8">
      {{ $t('testMode.toShop') }} <span aria-hidden="true">→</span>
    </NuxtLink>
  </section>
</template>
