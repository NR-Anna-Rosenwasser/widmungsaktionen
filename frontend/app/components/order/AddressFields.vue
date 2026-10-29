<script setup lang="ts">
import type { Address, AddressSuggestion } from '#shared/types/api'

const props = defineProps<{ prefix: 'billing' | 'shipping', errors: Record<string, string> }>()
const address = defineModel<Address>({ required: true })
const autocomplete = computed(() => props.prefix === 'billing' ? 'billing' : 'shipping')
const err = (field: string) => props.errors[`${props.prefix}.${field}`]

const search = useAddressSearch()
const streetInput = ref<{ focus: () => void }>()

function pickStreet(suggestion: AddressSuggestion) {
  // Without a house number, leave room for the customer to type it.
  address.value.street = suggestion.house_number
    ? `${suggestion.street} ${suggestion.house_number}`
    : `${suggestion.street} `
  pickPlace(suggestion)
  if (!suggestion.house_number) nextTick(() => streetInput.value?.focus())
}

function pickPlace(suggestion: AddressSuggestion) {
  if (suggestion.postal_code) address.value.postal_code = suggestion.postal_code
  if (suggestion.town) address.value.town = suggestion.town
  address.value.country = suggestion.country
}
</script>

<template>
  <div class="grid grid-cols-6 gap-4">
    <div class="col-span-6 sm:col-span-3">
      <label :for="`${prefix}-first`" class="field-label">{{ $text('order.address.firstName') }}</label>
      <input :id="`${prefix}-first`" v-model="address.first_name" class="field-input" :autocomplete="`${autocomplete} given-name`" :aria-invalid="!!err('first_name')">
      <OrderFieldError :message="err('first_name')" />
    </div>
    <div class="col-span-6 sm:col-span-3">
      <label :for="`${prefix}-last`" class="field-label">{{ $text('order.address.lastName') }}</label>
      <input :id="`${prefix}-last`" v-model="address.last_name" class="field-input" :autocomplete="`${autocomplete} family-name`" :aria-invalid="!!err('last_name')">
      <OrderFieldError :message="err('last_name')" />
    </div>
    <div class="col-span-6">
      <label :for="`${prefix}-company`" class="field-label">{{ $text('order.address.company') }}</label>
      <input :id="`${prefix}-company`" v-model="address.company" class="field-input" :autocomplete="`${autocomplete} organization`">
    </div>
    <div class="col-span-6">
      <label :id="`${prefix}-street-label`" :for="`${prefix}-street`" class="field-label">{{ $text('order.address.street') }}</label>
      <AutocompleteInput
        :id="`${prefix}-street`"
        ref="streetInput"
        v-model="address.street"
        :label-id="`${prefix}-street-label`"
        :search="search.streets"
        :autocomplete="`${autocomplete} street-address`"
        :aria-invalid="!!err('street')"
        @select="pickStreet"
      />
      <OrderFieldError :message="err('street')" />
    </div>
    <div class="col-span-2">
      <label :id="`${prefix}-zip-label`" :for="`${prefix}-zip`" class="field-label">{{ $text('order.address.postalCode') }}</label>
      <AutocompleteInput
        :id="`${prefix}-zip`"
        v-model="address.postal_code"
        :label-id="`${prefix}-zip-label`"
        :search="search.postcodes"
        :min-length="1"
        inputmode="numeric"
        maxlength="4"
        :autocomplete="`${autocomplete} postal-code`"
        :aria-invalid="!!err('postal_code')"
        @select="pickPlace"
      />
      <OrderFieldError :message="err('postal_code')" />
    </div>
    <div class="col-span-4">
      <label :for="`${prefix}-town`" class="field-label">{{ $text('order.address.town') }}</label>
      <input :id="`${prefix}-town`" v-model="address.town" class="field-input" :autocomplete="`${autocomplete} address-level2`" :aria-invalid="!!err('town')">
      <OrderFieldError :message="err('town')" />
    </div>
  </div>
</template>
