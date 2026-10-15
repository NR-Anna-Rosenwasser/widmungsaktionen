<script setup lang="ts">
import type { Order } from '#shared/types/api'
import { PREFILL_KEY } from '~/composables/useOrderForm'

const { t, locale } = useI18n()
const route = useRoute()
const path = useStorefrontPath()
const ctx = useStorefrontContext()
const api = useApi()

const token = computed(() => String(route.params.token))
const { data: order, error, refresh } = await useAsyncData(
  () => `order:${token.value}`,
  () => api<Order>(`/api/tenants/${ctx.value!.slug}/orders/${token.value}`),
)
const { data: storefront } = await useStorefront()

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: t('tracking.notFound'), fatal: true })
}

// Drafts belong in the form, not on the tracking page.
if (order.value?.status === 'draft') {
  await navigateTo({ path: path('/bestellen'), query: { t: order.value.token } }, { replace: true })
}

const isNew = computed(() => route.query.neu === '1')
const returnedFromCheckout = computed(() => route.query.bezahlt === '1')
const isCancelled = computed(() => order.value?.status === 'cancelled')
const isPaid = computed(() => !!order.value && order.value.payment_status !== 'unpaid')

// Stripe sends the customer back before its webhook may have arrived: poll briefly.
let poll: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  if (!returnedFromCheckout.value || isPaid.value) return
  let tries = 0
  poll = setInterval(async () => {
    tries++
    await refresh()
    if (isPaid.value || tries >= 15) clearInterval(poll)
  }, 2000)
})
onBeforeUnmount(() => clearInterval(poll))

const paying = ref(false)
const payError = ref('')
async function payNow() {
  paying.value = true
  payError.value = ''
  try {
    const { url } = await $fetch<{ url: string }>(`/api/tenants/${ctx.value!.slug}/orders/${token.value}/pay`, { method: 'POST' })
    window.location.href = url
  }
  catch {
    payError.value = t('tracking.paymentFailed')
    paying.value = false
  }
}
const canOrderAgain = computed(() => !!storefront.value?.aktion?.accepting_orders)

const steps = computed(() => order.value?.timeline ?? [])
const currentIndex = computed(() => steps.value.findLastIndex(step => step.done))

function expectedFor(key: string) {
  if (!order.value) return ''
  if (key === 'signed') return t('tracking.expected.signed', { date: formatDate(order.value.aktion.signing_date, locale.value) })
  if (key === 'shipped') return t('tracking.expected.shipped', { date: formatDate(order.value.aktion.estimated_shipping_date, locale.value) })
  return ''
}

function orderAgain() {
  if (!order.value) return
  try {
    sessionStorage.setItem(PREFILL_KEY, JSON.stringify({ email: order.value.email, billing: order.value.billing, is_gift: true }))
  }
  catch {
    // Private mode: start without prefill.
  }
  navigateTo(path('/bestellen'))
}

const lines = computed(() => {
  const o = order.value
  if (!o) return []
  const a = o.is_gift && o.shipping ? o.shipping : o.billing
  return [a.company, `${a.first_name} ${a.last_name}`, a.street, `${a.postal_code} ${a.town}`].filter(Boolean)
})

useSeoMeta({ title: () => `${t('tracking.title')} ${order.value?.number ?? ''}`, robots: 'noindex' })
</script>

<template>
  <div v-if="order" class="mx-auto max-w-3xl px-5 pt-6 sm:px-8 sm:pt-12">
    <div v-if="isNew" class="mb-12 rounded-md bg-rose p-6 sm:p-8">
      <h1 class="text-4xl text-navy sm:text-5xl">
        {{ $t('tracking.thanksTitle') }}
      </h1>
      <p class="mt-3 text-lg text-navy">
        {{ $t('tracking.thanksText', { email: order.email }) }}
      </p>
      <button v-if="canOrderAgain" type="button" class="arrow-link mt-6 text-navy" @click="orderAgain">
        {{ $t('tracking.another') }} <span aria-hidden="true">→</span>
      </button>
    </div>

    <p v-if="returnedFromCheckout" class="mb-8 rounded-sm p-4 font-semibold" :class="isPaid ? 'bg-blush' : 'bg-blush-light'" role="status" aria-live="polite">
      {{ isPaid ? $t('tracking.paymentConfirmed') : $t('tracking.paymentProcessing') }}
    </p>

    <p class="font-display text-sm font-semibold uppercase tracking-[0.18em] text-navy-soft">
      {{ $t('tracking.number', { number: order.number }) }}
    </p>
    <h2 v-if="!isNew" class="mt-2 text-5xl">
      {{ $t('tracking.title') }}
    </h2>

    <p v-if="isCancelled" class="mt-8 rounded-sm border-2 border-danger p-4 font-semibold text-danger" role="status">
      {{ $t('tracking.cancelledNotice', { email: storefront?.tenant.contact_email ?? '' }) }}
    </p>

    <!-- Timeline -->
    <ol class="mt-10 space-y-0">
      <li v-for="(step, i) in steps" :key="step.key" class="relative flex gap-5 pb-8 last:pb-0">
        <span
          v-if="i < steps.length - 1"
          class="absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5"
          :class="step.done && steps[i + 1]?.done ? 'bg-navy' : 'bg-blush'"
          aria-hidden="true"
        />
        <span
          class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold"
          :class="step.done ? 'border-navy bg-navy text-white' : i === currentIndex + 1 ? 'border-rose bg-white text-rose' : 'border-blush bg-white text-blush'"
          aria-hidden="true"
        >
          {{ step.done ? '✓' : i + 1 }}
        </span>
        <div class="pt-0.5">
          <p class="font-display text-xl font-semibold" :class="step.done ? '' : 'text-navy-soft'">
            {{ $t(`tracking.steps.${step.key}`) }}
            <span class="sr-only">{{ step.done ? '✓' : '' }}</span>
          </p>
          <p v-if="step.done && step.at" class="text-sm text-navy-soft">
            {{ formatDate(step.at, locale) }}
          </p>
          <p v-else-if="!step.done && step.key === 'paid' && order.payment_method && !isCancelled" class="text-sm text-navy-soft">
            {{ $t(`tracking.paymentPending.${order.payment_method}`, { date: formatDate(order.aktion.order_deadline, locale) }) }}
          </p>
          <p v-else-if="!step.done && !isCancelled" class="text-sm text-navy-soft">
            {{ expectedFor(step.key) }}
          </p>
          <p v-if="step.key === 'shipped' && order.shipments[0]?.tracking_number" class="text-sm">
            {{ $t('tracking.trackingNumber', { number: order.shipments[0].tracking_number }) }}
          </p>
        </div>
      </li>
    </ol>

    <div v-if="order.can_pay_online && !returnedFromCheckout" class="card mt-10 flex flex-wrap items-center justify-between gap-4">
      <p>{{ $t('tracking.payNowHint') }}</p>
      <button type="button" class="btn btn-primary" :disabled="paying" @click="payNow">
        {{ $t('tracking.payNow') }} <span aria-hidden="true">→</span>
      </button>
      <p v-if="payError" class="field-error w-full" role="alert">
        {{ payError }}
      </p>
    </div>

    <!-- Details -->
    <div class="mt-14 grid gap-6 sm:grid-cols-2">
      <div class="card">
        <h3 class="text-xl">
          {{ $t('tracking.copies') }}
        </h3>
        <ul class="mt-3 space-y-3">
          <li v-for="(copy, i) in order.copies" :key="i">
            <p class="font-semibold">
              {{ copy.title }} <span v-if="copy.signed" class="text-rose-dark">✓</span>
            </p>
            <p class="text-sm text-navy-soft">
              <template v-if="copy.signature_only">
                {{ $t('order.summary.signatureOnly') }}
              </template>
              <template v-else>
                {{ copy.recipient_name }}<template v-if="copy.wish">
                  – «{{ copy.wish }}»
                </template>
              </template>
            </p>
          </li>
        </ul>
      </div>
      <div class="card">
        <h3 class="text-xl">
          {{ $t('tracking.delivery') }}
        </h3>
        <p v-for="line in lines" :key="line" class="mt-1 first-of-type:mt-3">
          {{ line }}
        </p>
        <h3 class="mt-6 text-xl">
          {{ $t('order.summary.total') }}
        </h3>
        <p class="mt-1">
          {{ formatChf(order.total) }} · {{ order.payment_method ? $t(`order.payment.methods.${order.payment_method}.title`) : '' }}
        </p>
        <p class="mt-1 text-sm text-navy-soft">
          {{ $t('tracking.aktion') }}: {{ order.aktion.name }}
        </p>
        <a
          v-if="order.has_invoice"
          :href="`/api/tenants/${ctx!.slug}/orders/${order.token}/invoice`"
          target="_blank"
          rel="noopener"
          class="arrow-link mt-5 text-sm"
        >
          {{ $t('tracking.invoice') }} <span aria-hidden="true">↓</span>
        </a>
      </div>
    </div>
  </div>
</template>
