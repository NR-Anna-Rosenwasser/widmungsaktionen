<script setup lang="ts">
import type { Order } from '#shared/types/api'

// Testing mode replaces the Stripe checkout with this page. Each button
// triggers what the matching Stripe webhook would (see PaymentService).
const route = useRoute()
const ctx = useStorefrontContext()
const api = useApi()

const token = String(route.params.token)
const { data: order } = await useAsyncData(`order:${token}`, () => api<Order>(`/api/tenants/${ctx.value!.slug}/orders/${token}`))
// The layout shows the testing-mode banner when the secret link is active.
const { data: storefront } = await useStorefront()
const available = computed(() => order.value?.is_test && order.value.can_pay_online)

const sending = ref(false)
async function choose(outcome: 'paid' | 'failed' | 'cancelled') {
  sending.value = true
  try {
    const { url } = await $fetch<{ url: string }>(`/api/tenants/${ctx.value!.slug}/orders/${token}/test-payment`, { method: 'POST', body: { outcome } })
    window.location.href = url
  }
  catch (error) {
    reportApiError(error, 'test-payment')
    sending.value = false
  }
}

const { t } = useI18n()
useSeoMeta({ title: () => t('testMode.paymentTitle'), robots: 'noindex' })
</script>

<template>
  <div>
    <TestModeBanner v-if="!storefront?.preview" :text="$t('testMode.bannerOrder')" />
    <section class="mx-auto max-w-2xl px-5 pt-12 sm:px-8">
      <h1 class="text-4xl sm:text-5xl">
        {{ $t('testMode.paymentTitle') }}
      </h1>
      <template v-if="order && available">
        <p class="mt-3 text-lg text-navy-soft">
          {{ $t('testMode.paymentLead') }}
        </p>
        <p class="mt-6 font-display text-2xl font-semibold">
          {{ $t('testMode.paymentAmount', { number: order.number ?? '', total: formatChf(order.total) }) }}
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:items-start">
          <button type="button" class="btn btn-primary" :disabled="sending" @click="choose('paid')">
            {{ $t('testMode.paid') }}
          </button>
          <button type="button" class="btn btn-secondary" :disabled="sending" @click="choose('failed')">
            {{ $t('testMode.failed') }}
          </button>
          <button type="button" class="font-semibold text-navy-soft hover:text-navy" :disabled="sending" @click="choose('cancelled')">
            {{ $t('testMode.cancel') }}
          </button>
        </div>
      </template>
      <p v-else class="mt-4 text-lg">
        {{ $t('testMode.paymentUnavailable') }}
      </p>
    </section>
  </div>
</template>
