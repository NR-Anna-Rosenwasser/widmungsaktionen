<script setup lang="ts">
const { t, locale } = useI18n()
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

useSeoMeta({
  title: () => `${tenant.value.name} · ${t('meta.title')}`,
  description: () => t('meta.description'),
})
</script>

<template>
  <div>
    <!-- Hero -->
    <section class="bg-rose">
      <div class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-14 sm:px-8 md:grid-cols-[1.4fr_1fr] md:py-20">
        <div>
          <p class="font-display text-sm font-semibold uppercase tracking-[0.18em] text-navy/80">
            {{ aktion?.name ?? $t('home.eyebrow') }}
          </p>
          <h1 class="mt-3 text-5xl text-navy sm:text-7xl">
            {{ $t('home.title') }}
          </h1>
          <p class="mt-6 max-w-xl text-lg text-navy">
            {{ tenant.intro || $t('home.lead', { name: tenant.name }) }}
          </p>
          <p v-if="aktion?.description" class="mt-4 max-w-xl text-navy">
            {{ aktion.description }}
          </p>
          <div v-if="canOrder" class="mt-8">
            <NuxtLink :to="path('/bestellen')" class="arrow-link text-xl text-navy">
              {{ $t('home.cta') }} <span aria-hidden="true">→</span>
            </NuxtLink>
          </div>
        </div>
        <HeartText :text="tenant.name" :repeat="4" class="mx-auto w-56 text-navy sm:w-72" />
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
          {{ $t('home.closedTitle') }}
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
    <section v-if="books.length" class="mx-auto mt-24 max-w-6xl px-5 sm:px-8">
      <h2 class="text-4xl sm:text-5xl">
        {{ $t('home.booksTitle') }}
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
    </section>

    <!-- How it works -->
    <section class="mt-24 bg-blush-light">
      <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <h2 class="text-4xl sm:text-5xl">
          {{ $t('home.howTitle') }}
        </h2>
        <ol class="mt-10 grid gap-10 sm:grid-cols-3">
          <li v-for="n in 3" :key="n">
            <p class="font-display text-6xl font-bold text-rose">
              {{ n }}
            </p>
            <h3 class="mt-2 text-2xl">
              {{ $t(`home.how.${n}.title`) }}
            </h3>
            <p class="mt-2">
              {{ $t(`home.how.${n}.text`) }}
            </p>
          </li>
        </ol>
        <NuxtLink v-if="canOrder" :to="path('/bestellen')" class="btn btn-primary mt-12">
          {{ $t('home.cta') }} <span aria-hidden="true">→</span>
        </NuxtLink>
      </div>
    </section>

    <!-- Lookup -->
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-8">
      <OrderLookup :slug="tenant.slug" />
    </section>
  </div>
</template>
