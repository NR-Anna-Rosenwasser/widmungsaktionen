<script setup lang="ts">
// Shown on every page while testing an Aktion, so nobody mistakes it for the real shop.
defineProps<{ text: string; leave?: boolean }>();

const path = useStorefrontPath();
const preview = usePreviewCookie();

function leaveTestMode() {
  preview.value = null;
  // Full reload: every page fetched with the preview has to be fetched again.
  window.location.href = path("/");
}
</script>

<template>
  <div
    class="bg-[repeating-linear-gradient(-45deg,#fde68a_0_14px,#fcd34d_14px_28px)] px-5 py-2.5 text-sm font-semibold text-ink sticky top-0 left-0 w-full"
    role="status"
  >
    <div
      class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-1"
    >
      <span>🧪 {{ text }}</span>
      <button
        v-if="leave"
        type="button"
        class="underline"
        @click="leaveTestMode"
      >
        {{ $t("testMode.leave") }}
      </button>
    </div>
  </div>
</template>
