export default defineNuxtPlugin({
  name: "locale-cookie",
  dependsOn: ["i18n:plugin"],
  async setup(nuxtApp) {
    const i18n = nuxtApp.$i18n;
    const cookie = useCookie<string | null>("lang", {
      maxAge: 60 * 60 * 24 * 365,
      sameSite: "lax",
    });

    // Restore the saved choice (runs on the server too, so SSR renders the right language)
    if (
      cookie.value &&
      cookie.value !== i18n.locale.value &&
      i18n.availableLocales.includes(cookie.value)
    ) {
      await i18n.setLocale(cookie.value);
    }

    // Save every explicit switch
    nuxtApp.hook("i18n:localeSwitched", ({ newLocale }) => {
      cookie.value = newLocale;
    });
  },
});
