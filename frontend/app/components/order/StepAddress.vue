<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors } = props.form
const senderName = computed(() => `${state.billing.first_name ?? ''} ${state.billing.last_name ?? ''}`.trim() || '…')
</script>

<template>
  <div>
    <OrderStepHeading :title="$t('order.address.title')" :lead="$t('order.address.lead')" />

    <div class="mt-8">
      <OrderAddressFields v-model="state.billing" prefix="billing" :errors="errors" />
    </div>

    <label class="mt-8 flex cursor-pointer items-start gap-3 rounded-md border-2 p-4 transition-colors" :class="state.is_gift ? 'border-navy bg-blush-light' : 'border-blush hover:bg-blush-light'">
      <input v-model="state.is_gift" type="checkbox" class="mt-1 size-5 accent-navy">
      <span>
        <span class="font-semibold">{{ $t('order.address.gift') }}</span>
        <span class="mt-1 block text-sm text-navy-soft">{{ $t('order.address.giftHint') }}</span>
      </span>
    </label>

    <div v-if="state.is_gift" class="mt-8 space-y-8">
      <p class="rounded-sm bg-blush-light p-4 text-sm">
        {{ $t('order.address.multipleRecipients') }}
      </p>
      <div>
        <h2 class="mb-4 text-2xl">
          {{ $t('order.address.shippingTitle') }}
        </h2>
        <OrderAddressFields v-model="state.shipping" prefix="shipping" :errors="errors" />
      </div>
      <div>
        <label for="gift-message" class="field-label">{{ $t('order.address.giftMessage') }}</label>
        <textarea
          id="gift-message"
          v-model="state.gift_message"
          rows="3"
          maxlength="500"
          class="field-input resize-y"
          :placeholder="$t('order.address.giftMessagePlaceholder')"
          :aria-invalid="!!errors.gift_message"
        />
        <OrderFieldError :message="errors.gift_message" />
        <p class="field-hint">
          {{ $t('order.address.giftMessageHint', { name: senderName }) }}
        </p>
      </div>
    </div>
  </div>
</template>
