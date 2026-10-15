<script setup lang="ts">
import type { TenantSummary } from '#shared/types/api'

const { t, locale } = useI18n()
const api = useApi()
const { data: tenants } = await useAsyncData('tenants', () => api<TenantSummary[]>('/api/tenants'))

useSeoMeta({ title: () => t('picker.title') })
</script>

<template>
  <section class="mx-auto max-w-6xl px-5 pt-10 sm:px-8 sm:pt-16">
    <h1 class="text-5xl sm:text-7xl">
      {{ $t('picker.title') }}
    </h1>
    <p class="mt-4 max-w-xl text-lg text-navy-soft">
      {{ $t('picker.lead') }}
    </p>

    <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="tenant in tenants" :key="tenant.slug">
        <NuxtLink :to="`/${tenant.slug}`" class="card group block h-full transition-colors hover:bg-blush">
          <p class="font-display text-2xl font-semibold">
            {{ tenant.name }}
          </p>
          <p v-if="tenant.authors.length && tenant.authors.join() !== tenant.name" class="mt-1 text-navy-soft">
            {{ tenant.authors.join(', ') }}
          </p>
          <p class="mt-6 text-sm font-semibold">
            <template v-if="tenant.open_aktion">
              {{ $t('picker.openUntil', { date: formatDate(tenant.open_aktion.order_deadline, locale) }) }}
            </template>
            <template v-else>
              {{ $t('picker.noAktion') }}
            </template>
            <span class="ml-1 inline-block transition-transform group-hover:translate-x-1">→</span>
          </p>
        </NuxtLink>
      </li>
    </ul>
  </section>
</template>
