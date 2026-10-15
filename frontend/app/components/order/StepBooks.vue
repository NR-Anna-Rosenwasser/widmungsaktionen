<script setup lang="ts">
import type { OrderForm } from '~/composables/useOrderForm'

const props = defineProps<{ form: OrderForm }>()
const { aktion, tenant, quantities, totalCopies, maxCopies, errors } = props.form
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

    <p class="mt-6 text-sm text-navy-soft">
      {{ $t('order.books.more', { max: maxCopies, email: tenant.contact_email }) }}
    </p>
  </div>
</template>
