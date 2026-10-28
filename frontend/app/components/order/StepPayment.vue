<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors, availableMethods, total } = props.form
</script>

<template>
  <div>
    <OrderStepHeading :title="$text('order.payment.title')" :lead-html="$textHtml('order.payment.lead', { total: formatChf(total) })" />

    <div class="mt-8 space-y-3" role="radiogroup">
      <label
        v-for="method in availableMethods"
        :key="method"
        class="flex items-start gap-4 rounded-md border-2 p-5 transition-colors"
        :class="[
          form.methodDisabled(method) ? 'cursor-not-allowed border-blush-light opacity-60'
          : state.payment_method === method ? 'cursor-pointer border-navy bg-blush-light'
            : 'cursor-pointer border-blush hover:bg-blush-light',
        ]"
      >
        <input
          v-model="state.payment_method"
          type="radio"
          name="payment_method"
          :value="method"
          :disabled="form.methodDisabled(method)"
          class="mt-1.5 size-5 accent-navy"
        >
        <span>
          <span class="font-display text-xl font-semibold">{{ $text(`order.payment.methods.${method}.title`) }}</span>
          <span class="mt-1 block text-navy-soft">
            {{ form.methodDisabled(method) ? $text('order.payment.giftNotPossible') : $text(`order.payment.methods.${method}.text`) }}
          </span>
        </span>
      </label>
    </div>
    <OrderFieldError :message="errors.payment_method" />
  </div>
</template>
