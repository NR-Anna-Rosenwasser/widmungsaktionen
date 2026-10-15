<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors } = props.form
</script>

<template>
  <div>
    <OrderStepHeading :title="$t('order.email.title')" :lead="$t('order.email.lead')" />
    <div class="mt-8">
      <label for="email" class="field-label">{{ $t('order.email.label') }}</label>
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
      <p id="email-hint" class="field-hint">
        {{ $t('order.email.privacy') }}
      </p>
    </div>
  </div>
</template>
