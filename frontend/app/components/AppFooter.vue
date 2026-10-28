<script setup lang="ts">
const ctx = useStorefrontContext()
const { data } = ctx.value?.slug ? await useStorefront() : { data: ref(null) }
const consentEnabled = useState('consent-enabled', () => false)
const legal = computed(() => data.value?.tenant.legal)

async function openCookieSettings() {
  const CookieConsent = await import('vanilla-cookieconsent')
  CookieConsent.showPreferences()
}
</script>

<template>
  <footer class="mt-24 bg-navy text-blush">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-10 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8">
      <p class="font-display text-base font-semibold text-white">
        {{ data?.tenant.name }}
      </p>
      <ul class="flex flex-wrap gap-x-6 gap-y-2">
        <li v-if="data?.tenant.contact_email">
          <a :href="`mailto:${data.tenant.contact_email}`" class="hover:text-white">{{ $text('footer.contact') }}</a>
        </li>
        <li v-if="legal?.terms_url">
          <a :href="legal.terms_url" class="hover:text-white" target="_blank" rel="noopener">{{ $text('footer.terms') }}</a>
        </li>
        <li v-if="legal?.privacy_url">
          <a :href="legal.privacy_url" class="hover:text-white" target="_blank" rel="noopener">{{ $text('footer.privacy') }}</a>
        </li>
        <li v-if="legal?.imprint_url">
          <a :href="legal.imprint_url" class="hover:text-white" target="_blank" rel="noopener">{{ $text('footer.imprint') }}</a>
        </li>
        <li v-if="consentEnabled">
          <button type="button" class="hover:text-white" @click="openCookieSettings">
            {{ $text('footer.cookies') }}
          </button>
        </li>
      </ul>
    </div>
  </footer>
</template>
