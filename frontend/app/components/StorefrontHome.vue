<script setup lang="ts">
import type { StorefrontTextKey } from '#shared/types/api'

const { locale } = useI18n()
const path = useStorefrontPath()
const { data, error } = await useStorefront()

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Not found', fatal: true })
}

const tenant = computed(() => data.value!.tenant)
const aktion = computed(() => data.value!.aktion)
const next = computed(() => data.value!.next_aktion)
const books = computed(() => aktion.value?.books ?? next.value?.books ?? [])
const canOrder = computed(() => !!aktion.value?.accepting_orders)
const appearance = computed(() => tenant.value.storefront)
const hero = computed(() => appearance.value.hero)
const text = useStorefrontText(tenant)
const route = useRoute()
const optedOut = computed(() => route.query.abgemeldet === '1')

const stepText = (n: number, part: 'title' | 'text') => text(`how_${n}_${part}` as StorefrontTextKey)
const cheapestShipping = computed(() => Math.min(...tenant.value.shipping_tiers.map(tier => tier.price)))

useSeoMeta({
  title: () => text('seo_title'),
})
</script>

<template>
  <div>
    <p v-if="optedOut" class="bg-blush-light px-5 py-3 text-center font-semibold" role="status">
      {{ $t('home.optedOut') }}
    </p>

    <!-- Hero -->
    <section class="bg-rose">
      <div
        class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-14 sm:px-8 md:py-20"
        :class="hero.visual !== 'none' && 'md:grid-cols-[1.4fr_1fr]'"
      >
        <div>
          <p class="font-display text-sm font-semibold uppercase tracking-[0.18em] text-navy/80">
            {{ aktion?.name ?? text('eyebrow') }}
          </p>
          <h1 class="mt-3 text-5xl text-navy sm:text-7xl">
            {{ text('title') }}
          </h1>
          <p class="mt-6 max-w-xl whitespace-pre-line text-lg text-navy">
            {{ text('lead') }}
          </p>
          <p v-if="aktion?.description" class="mt-4 max-w-xl text-navy">
            {{ aktion.description }}
          </p>
          <div v-if="canOrder" class="mt-8">
            <NuxtLink :to="path('/bestellen')" class="arrow-link text-xl text-navy">
              {{ text('cta') }} <span aria-hidden="true">→</span>
            </NuxtLink>
          </div>
        </div>
        <img
          v-if="hero.visual === 'image' && hero.image_url"
          :src="hero.image_url"
          :alt="hero.image_alt"
          class="mx-auto max-h-112 w-full max-w-sm rounded-sm object-cover shadow-lg md:max-h-128"
        >
        <HeartText v-else-if="hero.visual === 'heart'" :text="hero.heart_text" :repeat="4" class="mx-auto w-56 text-navy sm:w-72" />
      </div>
    </section>

    <!-- Current Aktion -->
    <section class="mx-auto max-w-6xl px-5 sm:px-8">
      <div v-if="aktion" class="-mt-8 grid gap-px overflow-hidden rounded-md bg-blush shadow-sm sm:grid-cols-3">
        <div class="bg-white p-6">
          <p class="text-sm font-semibold text-navy-soft">
            {{ $t('home.deadline') }}
          </p>
          <p class="mt-1 font-display text-2xl font-semibold">
            {{ formatDate(aktion.order_deadline, locale) }}
          </p>
        </div>
        <div class="bg-white p-6">
          <p class="text-sm font-semibold text-navy-soft">
            {{ $t('home.signingDate') }}
          </p>
          <p class="mt-1 font-display text-2xl font-semibold">
            {{ formatDate(aktion.signing_date, locale) }}
          </p>
        </div>
        <div class="bg-white p-6">
          <p class="text-sm font-semibold text-navy-soft">
            {{ $t('home.shipping') }}
          </p>
          <p class="mt-1 font-display text-2xl font-semibold">
            {{ formatDate(aktion.estimated_shipping_date, locale) }}
          </p>
        </div>
      </div>
      <p v-if="aktion && !aktion.accepting_orders" class="mt-6 font-semibold">
        {{ $t('home.full') }}
      </p>

      <div v-if="!aktion" class="card mt-12 max-w-2xl">
        <h2 class="text-3xl">
          {{ text('closed_title') }}
        </h2>
        <p class="mt-3">
          <template v-if="next">
            {{ $t('home.closedText', { date: formatDate(next.orders_open_at, locale) }) }}
          </template>
          <template v-else>
            {{ $t('home.closedNoNext', { email: tenant.contact_email }) }}
          </template>
        </p>
      </div>
    </section>

    <!-- Books -->
    <section v-if="appearance.sections.books && books.length" class="mx-auto mt-24 max-w-6xl px-5 sm:px-8">
      <h2 class="text-4xl sm:text-5xl">
        {{ text('books_title') }}
      </h2>
      <ul class="mt-10 grid gap-10 sm:grid-cols-2">
        <li v-for="book in books" :key="book.id" class="flex gap-6">
          <img
            v-if="book.cover_url"
            :src="book.cover_url"
            :alt="book.title"
            class="h-44 w-auto shrink-0 rounded-sm shadow-md"
            loading="lazy"
          >
          <div
            v-else
            class="flex h-44 w-30 shrink-0 items-end rounded-sm bg-blush p-3 font-display text-lg font-semibold leading-tight shadow-md"
          >
            {{ book.title }}
          </div>
          <div>
            <h3 class="text-2xl">
              {{ book.title }}
            </h3>
            <p v-if="book.subtitle" class="mt-1 text-navy-soft">
              {{ book.subtitle }}
            </p>
            <p v-if="book.description" class="mt-3 text-[0.95rem]">
              {{ book.description }}
            </p>
            <p class="mt-3 font-display text-lg font-semibold">
              {{ formatChf(book.price) }}
            </p>
          </div>
        </li>
      </ul>
      <p class="mt-8 text-sm text-navy-soft">
        {{ $t('home.shippingFrom', { price: formatChf(cheapestShipping) }) }}
      </p>
      <NuxtLink v-if="canOrder" :to="path('/bestellen')" class="arrow-link mt-8 text-xl text-navy">
        {{ text('cta') }} <span aria-hidden="true">→</span>
      </NuxtLink>
    </section>

    <!-- How it works -->
    <section v-if="appearance.sections.how" class="mt-24 bg-blush-light">
      <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <h2 class="text-4xl sm:text-5xl">
          {{ text('how_title') }}
        </h2>
        <ol class="mt-10 grid gap-10 sm:grid-cols-3">
          <li v-for="n in 3" :key="n">
            <p class="font-display text-6xl font-bold text-rose">
              {{ n }}
            </p>
            <h3 class="mt-2 text-2xl">
              {{ stepText(n, 'title') }}
            </h3>
            <p class="mt-2">
              {{ stepText(n, 'text') }}
            </p>
          </li>
        </ol>
        <NuxtLink v-if="canOrder" :to="path('/bestellen')" class="btn btn-primary mt-12">
          {{ text('cta') }} <span aria-hidden="true">→</span>
        </NuxtLink>
      </div>
    </section>

    <!-- Lookup -->
    <section v-if="appearance.sections.lookup" class="mx-auto mt-24 max-w-6xl px-5 sm:px-8">
      <OrderLookup :slug="tenant.slug" :title="text('lookup_title')" :text="text('lookup_text')" />
    </section>
  </div>
</template>
