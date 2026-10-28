<script setup lang="ts">
import type { Address } from '#shared/types/api'
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors, tenant, subtotal, shippingCost, total } = props.form
const legal = computed(() => tenant.value.legal)
// Shown as it will be saved: options the shop doesn't offer are dropped.
const options = computed(() => tenant.value.dedication_options)
const { $text } = useNuxtApp()
// "Ich habe die {terms} und die {privacy} gelesen." with the two links in place.
const termsParts = computed(() => $text('order.summary.terms').split(/(\{terms\}|\{privacy\})/).filter(Boolean))
const lines = (a: Address) => [a.company, `${a.first_name} ${a.last_name}`, a.street, `${a.postal_code} ${a.town}`].filter(Boolean)
</script>

<template>
  <div>
    <OrderStepHeading :title="$text('order.summary.title')" :lead-html="$textHtml('order.summary.lead')" />

    <dl class="mt-8 divide-y divide-blush border-y border-blush">
      <div class="py-5">
        <div class="flex items-baseline justify-between">
          <dt class="font-display text-xl font-semibold">
            {{ $text('order.summary.books') }}
          </dt>
          <button type="button" class="text-sm font-semibold underline" @click="form.goTo('dedication')">
            {{ $text('order.summary.edit') }}
          </button>
        </div>
        <dd>
          <ul class="mt-3 space-y-3">
            <li v-for="(copy, i) in state.copies" :key="i">
              <p class="font-semibold">
                {{ form.bookTitle(copy.book_id) }}
              </p>
              <p class="text-navy-soft">
                <template v-if="copy.signature_only && options.signature_only">
                  {{ $text('order.summary.signatureOnly') }}
                </template>
                <template v-else>
                  {{ $text('order.summary.dedication') }}: {{ copy.recipient_name }}<template v-if="copy.wish && options.wish">
                    – «{{ copy.wish }}»
                  </template><template v-else>
                    – {{ $text('order.summary.surprise') }}
                  </template>
                </template>
              </p>
            </li>
          </ul>
        </dd>
      </div>

      <div class="grid gap-6 py-5 sm:grid-cols-2">
        <div>
          <dt class="flex items-baseline justify-between font-display text-xl font-semibold">
            {{ $text('order.summary.billing') }}
            <button type="button" class="font-sans text-sm font-semibold underline" @click="form.goTo('address')">
              {{ $text('order.summary.edit') }}
            </button>
          </dt>
          <dd class="mt-2">
            <p v-for="line in lines(state.billing)" :key="line">
              {{ line }}
            </p>
            <p class="text-navy-soft">
              {{ state.email }}
            </p>
          </dd>
        </div>
        <div>
          <dt class="font-display text-xl font-semibold">
            {{ $text('order.summary.shipping') }}
          </dt>
          <dd class="mt-2">
            <template v-if="state.is_gift">
              <p v-for="line in lines(state.shipping)" :key="line">
                {{ line }}
              </p>
              <p class="mt-2 text-navy-soft">
                {{ $text('order.summary.giftMessage') }}: «{{ state.gift_message }}»
              </p>
            </template>
            <p v-else class="text-navy-soft">
              {{ $text('order.summary.sameAsBilling') }}
            </p>
          </dd>
        </div>
      </div>

      <div class="flex items-baseline justify-between py-5">
        <dt class="font-display text-xl font-semibold">
          {{ $text('order.summary.payment') }}
        </dt>
        <dd>{{ state.payment_method ? $text(`order.payment.methods.${state.payment_method}.title`) : '' }}</dd>
      </div>

      <div class="space-y-1 py-5 tabular-nums">
        <div class="flex justify-between">
          <dt>{{ $text('order.summary.subtotal') }}</dt>
          <dd>{{ formatChf(subtotal) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt>{{ $text('order.summary.shippingCost') }}</dt>
          <dd>{{ formatChf(shippingCost) }}</dd>
        </div>
        <div class="flex justify-between pt-2 font-display text-2xl font-semibold">
          <dt>{{ $text('order.summary.total') }}</dt>
          <dd>{{ formatChf(total) }}</dd>
        </div>
      </div>
    </dl>

    <div class="mt-6 space-y-4">
      <label v-if="tenant.newsletter" class="flex cursor-pointer items-start gap-3">
        <input v-model="state.newsletter_opt_in" type="checkbox" class="mt-1 size-5 shrink-0 accent-navy">
        <span>{{ $text('order.summary.newsletter') }}</span>
      </label>
      <div v-if="legal.terms_url || legal.privacy_url">
        <label class="flex cursor-pointer items-start gap-3">
          <input v-model="state.terms" type="checkbox" class="mt-1 size-5 shrink-0 accent-navy" :aria-invalid="!!errors.terms">
          <span>
            <template v-for="(part, i) in termsParts" :key="i">
              <a v-if="part === '{terms}'" :href="legal.terms_url ?? '#'" target="_blank" rel="noopener" class="underline">{{ $text('order.summary.termsLink') }}</a>
              <a v-else-if="part === '{privacy}'" :href="legal.privacy_url ?? '#'" target="_blank" rel="noopener" class="underline">{{ $text('order.summary.privacyLink') }}</a>
              <template v-else>{{ part }}</template>
            </template>
          </span>
        </label>
        <OrderFieldError :message="errors.terms" />
      </div>
    </div>
  </div>
</template>
