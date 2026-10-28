<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors } = props.form
const senderName = computed(() => `${state.billing.first_name ?? ''} ${state.billing.last_name ?? ''}`.trim() || '…')
</script>

<template>
  <div>
    <OrderStepHeading :title="$text('order.address.title')" :lead-html="$textHtml('order.address.lead')" />

    <div class="mt-8">
      <OrderAddressFields v-model="state.billing" prefix="billing" :errors="errors" />
    </div>

    <label class="mt-8 flex cursor-pointer items-start gap-3 rounded-md border-2 p-4 transition-colors" :class="state.is_gift ? 'border-navy bg-blush-light' : 'border-blush hover:bg-blush-light'">
      <input v-model="state.is_gift" type="checkbox" class="mt-1 size-5 accent-navy">
      <span>
        <span class="font-semibold">{{ $text('order.address.gift') }}</span>
        <RichText :html="$textHtml('order.address.giftHint')" tag="span" class="mt-1 block text-sm text-navy-soft" />
      </span>
    </label>

    <div v-if="state.is_gift" class="mt-8 space-y-8">
      <RichText :html="$textHtml('order.address.multipleRecipients')" class="rounded-sm bg-blush-light p-4 text-sm" />
      <div>
        <h2 class="mb-4 text-2xl">
          {{ $text('order.address.shippingTitle') }}
        </h2>
        <OrderAddressFields v-model="state.shipping" prefix="shipping" :errors="errors" />
      </div>
      <div>
        <label for="gift-message" class="field-label">{{ $text('order.address.giftMessage') }}</label>
        <textarea
          id="gift-message"
          v-model="state.gift_message"
          rows="3"
          maxlength="500"
          class="field-input resize-y"
          :placeholder="$text('order.address.giftMessagePlaceholder')"
          :aria-invalid="!!errors.gift_message"
        />
        <OrderFieldError :message="errors.gift_message" />
        <RichText :html="$textHtml('order.address.giftMessageHint', { name: senderName })" class="field-hint" />
      </div>
    </div>
  </div>
</template>
