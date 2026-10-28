<script setup lang="ts">
import { ORDER_STEPS } from '~/composables/useOrderForm'

const { $text } = useNuxtApp()
const path = useStorefrontPath()
const { data } = await useStorefront()

if (!data.value?.aktion?.accepting_orders) {
  await navigateTo(path('/'), { replace: true })
}

const storefront = computed(() => data.value!)
const form = useOrderForm(storefront)
const { step, stepIndex, saving, formError } = form

onMounted(() => form.restore())

const heading = useTemplateRef<HTMLElement>('heading')
watch(step, async () => {
  await nextTick()
  heading.value?.focus()
  window.scrollTo({ top: 0, behavior: 'smooth' })
})

useSeoMeta({ title: () => `${$text('nav.order')} · ${storefront.value.tenant.name}` })
</script>

<template>
  <div class="mx-auto max-w-2xl px-5 pb-10 pt-4 sm:px-8 sm:pt-10">
    <!-- Progress -->
    <div class="flex items-center justify-between text-sm font-semibold text-navy-soft">
      <span>{{ $text('order.stepOf', { current: stepIndex + 1, total: ORDER_STEPS.length }) }}</span>
      <span v-if="saving" aria-live="polite">{{ $text('order.saving') }}</span>
    </div>
    <div class="mt-2 h-1 overflow-hidden rounded-full bg-blush-light" aria-hidden="true">
      <div class="h-full bg-rose transition-[width] duration-300" :style="{ width: `${((stepIndex + 1) / ORDER_STEPS.length) * 100}%` }" />
    </div>

    <form class="mt-10" novalidate @submit.prevent="form.next()">
      <Transition name="step" mode="out-in">
        <div :key="step">
          <div ref="heading" tabindex="-1" class="outline-none">
            <OrderStepBooks v-if="step === 'books'" :form="form" />
            <OrderStepEmail v-else-if="step === 'email'" :form="form" />
            <OrderStepDedication v-else-if="step === 'dedication'" :form="form" />
            <OrderStepAddress v-else-if="step === 'address'" :form="form" />
            <OrderStepPayment v-else-if="step === 'payment'" :form="form" />
            <OrderStepSummary v-else :form="form" />
          </div>
        </div>
      </Transition>

      <p v-if="formError" class="mt-6 rounded-sm bg-danger/10 p-4 font-semibold text-danger" role="alert">
        {{ formError }}
      </p>

      <div class="mt-10 flex flex-wrap items-center gap-4">
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ step === 'summary' ? $text('order.summary.submit') : $text('order.next') }}
          <span aria-hidden="true">→</span>
        </button>
        <span v-if="step !== 'summary'" class="hidden text-sm text-navy-soft sm:inline">{{ $text('order.pressEnter') }}</span>
        <button v-if="stepIndex > 0" type="button" class="ml-auto font-semibold text-navy-soft hover:text-navy" @click="form.back()">
          ← {{ $text('order.back') }}
        </button>
      </div>
    </form>
  </div>
</template>
