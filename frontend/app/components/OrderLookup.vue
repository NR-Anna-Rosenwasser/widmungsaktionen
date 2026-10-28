<script setup lang="ts">
const props = defineProps<{ slug: string }>()
const { $text } = useNuxtApp()
const path = useStorefrontPath()

const number = ref('')
const email = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const { token } = await $fetch<{ token: string }>(`/api/tenants/${props.slug}/orders/lookup`, {
      method: 'POST',
      body: { number: number.value, email: email.value },
    })
    await navigateTo(path(`/bestellung/${token}`))
  }
  catch (e) {
    // Not found (404) and invalid input (422) are expected here.
    if (![404, 422].includes((e as { statusCode?: number }).statusCode ?? 0)) reportApiError(e, 'order-lookup')
    error.value = $text('home.lookupNotFound')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h2 class="text-3xl">
      {{ $text('home.lookupTitle') }}
    </h2>
    <RichText :html="$textHtml('home.lookupText')" class="mt-2 text-navy-soft" />
    <form class="mt-6 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="submit">
      <div>
        <label for="lookup-number" class="field-label">{{ $text('home.lookupNumber') }}</label>
        <input id="lookup-number" v-model="number" required class="field-input" placeholder="AR-2026-0042" autocomplete="off">
      </div>
      <div>
        <label for="lookup-email" class="field-label">{{ $text('home.lookupEmail') }}</label>
        <input id="lookup-email" v-model="email" required type="email" class="field-input" autocomplete="email">
      </div>
      <button type="submit" class="btn btn-secondary" :disabled="loading">
        {{ $text('home.lookupSubmit') }}
      </button>
    </form>
    <p v-if="error" class="field-error" role="alert">
      {{ error }}
    </p>
  </div>
</template>
