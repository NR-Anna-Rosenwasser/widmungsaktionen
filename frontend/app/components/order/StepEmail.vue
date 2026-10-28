<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors } = props.form
</script>

<template>
  <div>
    <OrderStepHeading :title="$text('order.email.title')" :lead-html="$textHtml('order.email.lead')" />
    <div class="mt-8">
      <label for="email" class="field-label">{{ $text('order.email.label') }}</label>
      <input
        id="email"
        v-model="state.email"
        type="email"
        autocomplete="email"
        inputmode="email"
        class="field-input text-xl"
        :aria-invalid="!!errors.email"
        aria-describedby="email-error email-hint"
        autofocus
      >
      <OrderFieldError id="email-error" :message="errors.email" />
      <RichText id="email-hint" :html="$textHtml('order.email.privacy')" class="field-hint" />
    </div>
  </div>
</template>
