<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { aktion, tenant, quantities, totalCopies, maxCopies, errors, subtotal, shippingCost, total } = props.form
const { t } = useI18n()

// "1 Buch: CHF 3.00 (Brief)", "2–4 Bücher: CHF 8.00 (Paket)" from the shop's tiers,
// limited to what can be ordered online.
const shippingTiers = computed(() => {
  const tiers: { key: string, label: string, to: number }[] = []
  let from = 1
  for (const tier of tenant.value.shipping_tiers) {
    if (from > maxCopies.value) break
    const to = Math.min(tier.max_copies, maxCopies.value)
    if (to < from) continue
    const range = from === to
      ? t(to === 1 ? 'order.books.shippingRangeOne' : 'order.books.shippingRangeExact', { count: to })
      : t('order.books.shippingRange', { from, to })
    tiers.push({
      key: `${from}-${to}`,
      label: t('order.books.shippingTier', { range, price: formatChf(tier.price), method: t(`order.books.method.${tier.method}`) }),
      to,
    })
    from = to + 1
  }
  return tiers
})
const activeTierTo = computed(() => shippingTiers.value.find(tier => totalCopies.value <= tier.to)?.to)
</script>

<template>
  <div>
    <OrderStepHeading :title="$t('order.books.title')" :lead="$t('order.books.lead', { max: maxCopies })" />

    <ul class="mt-8 space-y-4">
      <li v-for="book in aktion.books" :key="book.id" class="card flex items-center gap-5">
        <img v-if="book.cover_url" :src="book.cover_url" :alt="''" class="h-24 w-auto shrink-0 rounded-sm shadow">
        <div class="min-w-0 flex-1">
          <p class="font-display text-xl font-semibold leading-tight">
            {{ book.title }}
          </p>
          <p v-if="book.subtitle" class="text-sm text-navy-soft">
            {{ book.subtitle }}
          </p>
          <p class="mt-1 font-semibold">
            {{ formatChf(book.price) }}
          </p>
        </div>
        <div class="flex items-center gap-2" role="group" :aria-label="book.title">
          <button
            type="button"
            class="size-10 rounded-sm bg-white text-xl font-semibold shadow-sm disabled:opacity-40"
            :aria-label="$t('order.books.remove')"
            :disabled="!quantities[book.id]"
            @click="form.setQuantity(book.id, (quantities[book.id] ?? 0) - 1)"
          >
            −
          </button>
          <output class="w-8 text-center font-display text-2xl font-semibold" aria-live="polite">
            {{ quantities[book.id] ?? 0 }}
          </output>
          <button
            type="button"
            class="size-10 rounded-sm bg-navy text-xl font-semibold text-white shadow-sm disabled:opacity-40"
            :aria-label="$t('order.books.add')"
            :disabled="totalCopies >= maxCopies"
            @click="form.setQuantity(book.id, (quantities[book.id] ?? 0) + 1)"
          >
            +
          </button>
        </div>
      </li>
    </ul>

    <OrderFieldError :message="errors.copies" />

    <div class="mt-6 rounded-md border-2 border-blush p-4 text-sm">
      <p class="font-semibold">
        {{ $t('order.books.shippingTitle') }}
      </p>
      <ul class="mt-1 space-y-0.5">
        <li
          v-for="tier in shippingTiers"
          :key="tier.key"
          :class="totalCopies > 0 && tier.to === activeTierTo ? 'font-semibold text-navy' : 'text-navy-soft'"
        >
          {{ tier.label }}
        </li>
      </ul>
      <p v-if="totalCopies > 0" class="mt-3 border-t border-blush pt-3 font-display text-base font-semibold tabular-nums" aria-live="polite">
        {{ totalCopies === 1
          ? $t('order.books.runningTotalOne', { subtotal: formatChf(subtotal), shipping: formatChf(shippingCost), total: formatChf(total) })
          : $t('order.books.runningTotal', { books: totalCopies, subtotal: formatChf(subtotal), shipping: formatChf(shippingCost), total: formatChf(total) }) }}
      </p>
    </div>

    <p class="mt-6 text-sm text-navy-soft">
      {{ $t('order.books.more', { max: maxCopies, email: tenant.contact_email }) }}
    </p>
  </div>
</template>
