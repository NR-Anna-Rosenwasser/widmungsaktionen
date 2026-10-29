<script setup lang="ts">
const ctx = useStorefrontContext()
const path = useStorefrontPath()
const { data } = ctx.value?.slug ? await useStorefront() : { data: ref(null) }
const { locale, locales, localeProperties, setLocale } = useI18n()
const cookie = useLanguageCookie()
const languages = useShopLanguages()

// The shop's languages: none to choose with one, buttons with two, a menu with more.
const offered = computed(() => locales.value.filter(l => languages.value.locales.includes(l.code)))

const open = ref(false)
const menu = ref<HTMLElement | null>(null)

async function choose(code: string) {
  open.value = false
  cookie.value = code
  await setLocale(code as typeof locale.value)
}

function onClickOutside(event: MouseEvent) {
  if (!menu.value?.contains(event.target as Node)) open.value = false
}

watch(open, (isOpen) => {
  if (isOpen) document.addEventListener('click', onClickOutside)
  else document.removeEventListener('click', onClickOutside)
})
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
  <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
    <NuxtLink :to="path('/')" class="font-display text-lg font-bold tracking-tight">
      <img
        v-if="data?.tenant.storefront.logo_url"
        :src="data.tenant.storefront.logo_url"
        :alt="data.tenant.name"
        class="h-11 w-auto max-w-[60vw] object-contain object-left sm:h-12"
      >
      <template v-else>
        {{ data?.tenant.name ?? $t('picker.title') }}
      </template>
    </NuxtLink>
    <nav
      v-if="offered.length === 2"
      :aria-label="$text('nav.language')"
      class="flex gap-1 text-sm font-semibold"
    >
      <button
        v-for="l in offered"
        :key="l.code"
        type="button"
        class="rounded-sm px-2 py-1 uppercase transition-colors"
        :class="l.code === locale ? 'bg-blush' : 'hover:bg-blush-light'"
        :aria-pressed="l.code === locale"
        :lang="l.language"
        :title="l.name"
        @click="choose(l.code)"
      >
        {{ l.code }}
      </button>
    </nav>
    <nav
      v-else-if="offered.length > 2"
      ref="menu"
      :aria-label="$text('nav.language')"
      class="relative text-sm font-semibold"
      @keydown.esc="open = false"
    >
      <button
        type="button"
        class="flex items-center gap-1 rounded-sm px-2 py-1 uppercase transition-colors hover:bg-blush-light"
        :class="{ 'bg-blush-light': open }"
        :aria-expanded="open"
        aria-controls="language-menu"
        :title="localeProperties.name"
        @click.stop="open = !open"
      >
        {{ locale }}
        <svg aria-hidden="true" viewBox="0 0 12 12" class="size-3 transition-transform" :class="{ 'rotate-180': open }">
          <path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <ul
        v-show="open"
        id="language-menu"
        class="absolute right-0 z-20 mt-1 min-w-36 rounded-md bg-white p-1 shadow-lg ring-1 ring-navy/10"
      >
        <li v-for="l in offered" :key="l.code">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-sm px-3 py-2 text-left transition-colors"
            :class="l.code === locale ? 'bg-blush' : 'hover:bg-blush-light'"
            :aria-current="l.code === locale ? 'true' : undefined"
            :lang="l.language"
            @click="choose(l.code)"
          >
            {{ l.name }}
            <span class="text-xs uppercase text-navy-soft">{{ l.code }}</span>
          </button>
        </li>
      </ul>
    </nav>
  </header>
</template>
