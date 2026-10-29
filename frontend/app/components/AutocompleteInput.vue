<script setup lang="ts" generic="T extends { label: string }">
/**
 * Text input with a suggestion list (ARIA combobox). Typing always works on
 * its own; picking a suggestion emits `select` and the parent fills fields.
 * Extra attributes (autocomplete, inputmode, aria-invalid …) go to the input.
 */
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  id: string
  /** id of the visible label, which also names the list. */
  labelId: string
  search: (q: string, signal: AbortSignal) => Promise<T[]>
  minLength?: number
}>(), { minLength: 3 })

const emit = defineEmits<{ select: [item: T] }>()
const model = defineModel<string | null>({ required: true })

const input = ref<HTMLInputElement>()
const items = ref<T[]>([]) as Ref<T[]>
const open = ref(false)
const active = ref(-1)
const listId = computed(() => `${props.id}-list`)
const optionId = (i: number) => `${props.id}-option-${i}`

let timer: ReturnType<typeof setTimeout> | undefined
let controller: AbortController | undefined

function close() {
  open.value = false
  active.value = -1
}

function cancel() {
  clearTimeout(timer)
  controller?.abort()
}

function onInput(event: Event) {
  // Read from the event: model.value still holds the parent's old value until it re-renders.
  const value = (event.target as HTMLInputElement).value
  model.value = value
  cancel()

  const q = value.trim()
  if (q.length < props.minLength) {
    close()
    return
  }

  timer = setTimeout(async () => {
    const request = controller = new AbortController()
    try {
      const results = await props.search(q, request.signal)
      // A newer keystroke may have started another lookup meanwhile.
      if (request.signal.aborted) return
      items.value = results
      open.value = results.length > 0
      active.value = -1
    }
    catch {
      // Aborted by newer input, or the lookup failed: no suggestions, typing goes on.
      if (!request.signal.aborted) close()
    }
  }, 250)
}

function choose(item: T) {
  cancel()
  close()
  emit('select', item)
}

function onKeydown(event: KeyboardEvent) {
  if (!open.value) return

  const count = items.value.length
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = (active.value + 1) % count
  }
  else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = (active.value - 1 + count) % count
  }
  else if (event.key === 'Enter' && active.value >= 0) {
    event.preventDefault()
    choose(items.value[active.value]!)
  }
  else if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

function onBlur() {
  cancel()
  close()
}

onBeforeUnmount(cancel)

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <div class="relative">
    <input
      :id="id"
      ref="input"
      v-bind="$attrs"
      :value="model"
      class="field-input"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-activedescendant="active >= 0 ? optionId(active) : undefined"
      @input="onInput"
      @keydown="onKeydown"
      @blur="onBlur"
    >
    <ul
      v-show="open"
      :id="listId"
      role="listbox"
      :aria-labelledby="labelId"
      class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-auto rounded border-2 border-navy bg-white py-1 shadow-lg"
    >
      <li
        v-for="(item, i) in items"
        :id="optionId(i)"
        :key="item.label"
        role="option"
        :aria-selected="i === active"
        class="cursor-pointer px-3.5 py-2.5"
        :class="i === active ? 'bg-blush-light' : 'hover:bg-blush-light'"
        @mousedown.prevent="choose(item)"
      >
        {{ item.label }}
      </li>
    </ul>
  </div>
</template>
