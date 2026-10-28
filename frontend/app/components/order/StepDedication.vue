<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { state, errors, tenant } = props.form
const max = computed(() => tenant.value.dedication_max_length)
</script>

<template>
  <div>
    <OrderStepHeading
      :title="state.copies.length > 1 ? $text('order.dedication.titleMany') : $text('order.dedication.title')"
      :lead-html="state.copies.length > 1 ? $textHtml('order.dedication.lead') : undefined"
    />

    <div class="mt-8 space-y-6">
      <fieldset v-for="(copy, i) in state.copies" :key="i" class="card">
        <legend class="sr-only">
          {{ $text('order.dedication.copy', { n: i + 1 }) }}: {{ form.bookTitle(copy.book_id) }}
        </legend>
        <p class="font-display text-lg font-semibold" aria-hidden="true">
          <span v-if="state.copies.length > 1">{{ $text('order.dedication.copy', { n: i + 1 }) }} · </span>{{ form.bookTitle(copy.book_id) }}
        </p>

        <div class="mt-4 inline-flex rounded-sm bg-white p-1 shadow-sm" role="radiogroup">
          <label
            v-for="option in [false, true]"
            :key="String(option)"
            class="cursor-pointer rounded-sm px-4 py-2 text-sm font-semibold transition-colors"
            :class="copy.signature_only === option ? 'bg-navy text-white' : 'hover:bg-blush-light'"
          >
            <input v-model="copy.signature_only" type="radio" :value="option" class="sr-only">
            {{ option ? $text('order.dedication.signatureOnly') : $text('order.dedication.withDedication') }}
          </label>
        </div>

        <div v-if="!copy.signature_only" class="mt-5 space-y-4">
          <div>
            <label :for="`recipient-${i}`" class="field-label">{{ $text('order.dedication.recipient') }}</label>
            <input
              :id="`recipient-${i}`"
              v-model="copy.recipient_name"
              class="field-input"
              maxlength="100"
              :placeholder="$text('order.dedication.recipientPlaceholder')"
              :aria-invalid="!!errors[`copies.${i}.recipient_name`]"
            >
            <OrderFieldError :message="errors[`copies.${i}.recipient_name`]" />
          </div>
          <div>
            <label :for="`wish-${i}`" class="field-label">{{ $text('order.dedication.wish') }}</label>
            <textarea
              :id="`wish-${i}`"
              v-model="copy.wish"
              rows="3"
              class="field-input resize-y"
              :maxlength="max"
              :placeholder="$text('order.dedication.wishPlaceholder')"
              :aria-invalid="!!errors[`copies.${i}.wish`]"
              @keydown.enter.ctrl.prevent="form.next()"
            />
            <div class="flex justify-between gap-4">
              <RichText :html="$textHtml('order.dedication.wishHint')" class="field-hint" />
              <p class="field-hint shrink-0 tabular-nums">
                {{ $text('order.dedication.chars', { count: (copy.wish ?? '').length, max }) }}
              </p>
            </div>
            <OrderFieldError :message="errors[`copies.${i}.wish`]" />
          </div>
        </div>
      </fieldset>
    </div>
  </div>
</template>
