import type { Address, Copy, Order, PaymentMethod, StorefrontResponse } from '#shared/types/api'

export const ORDER_STEPS = ['books', 'email', 'dedication', 'address', 'payment', 'summary'] as const
export type OrderStep = typeof ORDER_STEPS[number]

const emptyAddress = (): Address => ({
  first_name: '', last_name: '', company: '', street: '', postal_code: '', town: '', country: 'CH',
})

const draftKey = (slug: string) => `widmungen:draft:${slug}`
export const PREFILL_KEY = 'widmungen:prefill'

function storage(kind: 'local' | 'session') {
  try {
    return kind === 'local' ? window.localStorage : window.sessionStorage
  }
  catch {
    return null
  }
}

/**
 * State and persistence of the multi-step order form. A draft is created on
 * the server as soon as the email address is known; every later step is saved.
 */
export function useOrderForm(storefront: Ref<StorefrontResponse>) {
  const { t, locale } = useI18n()
  const route = useRoute()
  const router = useRouter()
  const path = useStorefrontPath()
  const { track } = useTracking()

  const tenant = computed(() => storefront.value.tenant)
  const aktion = computed(() => storefront.value.aktion!)
  const slug = computed(() => tenant.value.slug)

  const step = ref<OrderStep>('books')
  const stepIndex = computed(() => ORDER_STEPS.indexOf(step.value))
  const saving = ref(false)
  const errors = ref<Record<string, string>>({})
  const formError = ref('')
  const token = ref<string | null>(null)

  const quantities = reactive<Record<number, number>>({})
  const state = reactive({
    email: '',
    copies: [] as Copy[],
    billing: emptyAddress(),
    is_gift: false,
    shipping: emptyAddress(),
    gift_message: '',
    payment_method: null as PaymentMethod | null,
    newsletter_opt_in: false,
    terms: false,
  })

  const totalCopies = computed(() => Object.values(quantities).reduce((sum, n) => sum + n, 0))
  const maxCopies = computed(() => tenant.value.max_copies_per_order)

  const subtotal = computed(() => aktion.value.books.reduce((sum, book) => sum + book.price * (quantities[book.id] ?? 0), 0))
  const shippingCost = computed(() => {
    const tier = tenant.value.shipping_tiers.find(tier => totalCopies.value <= tier.max_copies)
    return totalCopies.value ? (tier?.price ?? 0) : 0
  })
  const total = computed(() => subtotal.value + shippingCost.value)

  const availableMethods = computed(() => tenant.value.payment_methods)
  const methodDisabled = (method: PaymentMethod) => state.is_gift && method === 'invoice_in_parcel'

  /** Rebuild the per-copy list from the quantities, keeping existing dedications. */
  function syncCopiesFromQuantities() {
    const previous = [...state.copies]
    const copies: Copy[] = []
    for (const book of aktion.value.books) {
      for (let i = 0; i < (quantities[book.id] ?? 0); i++) {
        const index = previous.findIndex(copy => copy.book_id === book.id)
        copies.push(index >= 0
          ? previous.splice(index, 1)[0]!
          : { book_id: book.id, signature_only: false, recipient_name: '', wish: '' })
      }
    }
    state.copies = copies
  }

  function bookTitle(bookId: number) {
    return aktion.value.books.find(book => book.id === bookId)?.title ?? ''
  }

  function setQuantity(bookId: number, quantity: number) {
    const others = totalCopies.value - (quantities[bookId] ?? 0)
    quantities[bookId] = Math.max(0, Math.min(quantity, maxCopies.value - others))
    if (!token.value && totalCopies.value === 1 && quantity === 1) {
      track('order_started')
    }
  }

  // --- Validation (client side, mirrors the API) ---

  function validate(current: OrderStep): boolean {
    const e: Record<string, string> = {}
    const required = (key: string, value: unknown) => {
      if (!String(value ?? '').trim()) e[key] = t('order.required')
    }
    const address = (prefix: 'billing' | 'shipping') => {
      const a = state[prefix]
      required(`${prefix}.first_name`, a.first_name)
      required(`${prefix}.last_name`, a.last_name)
      required(`${prefix}.street`, a.street)
      required(`${prefix}.town`, a.town)
      if (!/^\d{4}$/.test(String(a.postal_code ?? '').trim())) e[`${prefix}.postal_code`] = t('order.invalidPostalCode')
    }

    if (current === 'books') {
      if (totalCopies.value < 1) e.copies = t('order.books.empty')
      if (totalCopies.value > maxCopies.value) e.copies = t('order.books.max', { max: maxCopies.value })
    }
    if (current === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email.trim())) {
      e.email = t('order.invalidEmail')
    }
    if (current === 'dedication') {
      state.copies.forEach((copy, i) => {
        if (!copy.signature_only && !String(copy.recipient_name ?? '').trim()) {
          e[`copies.${i}.recipient_name`] = t('order.dedication.recipientRequired')
        }
      })
    }
    if (current === 'address') {
      address('billing')
      if (state.is_gift) {
        address('shipping')
        required('gift_message', state.gift_message)
      }
    }
    if (current === 'payment') {
      if (!state.payment_method || methodDisabled(state.payment_method)) e.payment_method = t('order.required')
    }
    if (current === 'summary' && !state.terms && (tenant.value.legal.terms_url || tenant.value.legal.privacy_url)) {
      e.terms = t('order.summary.termsRequired')
    }

    errors.value = e
    return Object.keys(e).length === 0
  }

  // --- Persistence ---

  function payloadFor(current: OrderStep): Record<string, unknown> {
    const copies = () => state.copies.map(copy => ({
      book_id: copy.book_id,
      signature_only: copy.signature_only,
      recipient_name: copy.signature_only ? null : copy.recipient_name,
      wish: copy.signature_only ? null : copy.wish,
    }))

    switch (current) {
      case 'books':
      case 'dedication':
        return { copies: copies() }
      case 'email':
        return { email: state.email.trim(), locale: locale.value }
      case 'address':
        return {
          billing: state.billing,
          is_gift: state.is_gift,
          shipping: state.is_gift ? state.shipping : null,
          gift_message: state.is_gift ? state.gift_message : null,
        }
      case 'payment':
        return { payment_method: state.payment_method }
      case 'summary':
        return { newsletter_opt_in: state.newsletter_opt_in }
    }
  }

  function applyServerErrors(error: unknown) {
    const serverErrors = apiValidationErrors(error)
    const mapped: Record<string, string> = {}
    for (const [key, messages] of Object.entries(serverErrors)) {
      mapped[key] = messages[0] ?? ''
    }
    errors.value = mapped

    // Jump back to the first step with an error.
    const stepFor = (key: string): OrderStep =>
      key.startsWith('copies.') && /recipient|wish/.test(key) ? 'dedication'
        : key.startsWith('copies') ? 'books'
          : key === 'email' ? 'email'
            : /^(billing|shipping|gift_message|is_gift)/.test(key) ? 'address'
              : key === 'payment_method' ? 'payment'
                : 'summary'
    const steps = Object.keys(mapped).map(stepFor)
    const first = ORDER_STEPS.find(s => steps.includes(s))
    if (first) step.value = first
    if (!Object.keys(mapped).length) {
      formError.value = apiErrorMessage(error, t('order.errors.generic'))
    }
  }

  function rememberToken(value: string) {
    token.value = value
    storage('local')?.setItem(draftKey(slug.value), value)
    router.replace({ query: { ...route.query, t: value } })
  }

  function forgetDraft() {
    storage('local')?.removeItem(draftKey(slug.value))
  }

  async function persist(current: OrderStep, nextStep: OrderStep | null) {
    const base = `/api/tenants/${slug.value}/orders`

    if (!token.value) {
      if (current !== 'email') return
      syncCopiesFromQuantities()
      const order = await $fetch<Order>(base, {
        method: 'POST',
        body: { email: state.email.trim(), locale: locale.value, ...payloadFor('books') },
      })
      rememberToken(order.token)
      track('email_entered')
      return
    }

    await $fetch<Order>(`${base}/${token.value}`, {
      method: 'PATCH',
      body: { ...payloadFor(current), current_step: nextStep },
    })
  }

  async function next() {
    formError.value = ''
    if (step.value === 'books') syncCopiesFromQuantities()
    if (!validate(step.value)) return

    const nextStep = ORDER_STEPS[stepIndex.value + 1] ?? null
    saving.value = true
    try {
      await persist(step.value, nextStep)
      track('step_completed', { step: step.value })
      if (nextStep) {
        step.value = nextStep
      }
      else {
        await submit()
      }
    }
    catch (error) {
      reportApiError(error, 'order-form', { step: step.value })
      applyServerErrors(error)
    }
    finally {
      saving.value = false
    }
  }

  function back() {
    errors.value = {}
    formError.value = ''
    const previous = ORDER_STEPS[stepIndex.value - 1]
    if (previous) step.value = previous
  }

  function goTo(target: OrderStep) {
    if (ORDER_STEPS.indexOf(target) < stepIndex.value) {
      errors.value = {}
      step.value = target
    }
  }

  async function submit() {
    const res = await $fetch<{ order: Order, next: { type: 'confirmation' } | { type: 'redirect', url: string } }>(
      `/api/tenants/${slug.value}/orders/${token.value}/submit`,
      { method: 'POST' },
    )
    track('order_submitted', {
      payment_method: res.order.payment_method ?? '',
      copies: res.order.copies.length,
      total: res.order.total / 100,
    })
    forgetDraft()
    if (res.next.type === 'redirect') {
      // Stripe Checkout; it returns to the tracking page afterwards.
      await navigateTo(res.next.url, { external: true })
      return
    }
    await navigateTo({ path: path(`/bestellung/${res.order.token}`), query: { neu: '1' } })
  }

  // --- Resume a draft or prefill from a previous order ---

  function hydrate(order: Order) {
    token.value = order.token
    state.email = order.email
    state.copies = order.copies.map(copy => ({
      book_id: copy.book_id,
      signature_only: copy.signature_only,
      recipient_name: copy.recipient_name ?? '',
      wish: copy.wish ?? '',
    }))
    for (const key of Object.keys(quantities)) delete quantities[Number(key)]
    for (const copy of order.copies) quantities[copy.book_id] = (quantities[copy.book_id] ?? 0) + 1
    state.billing = { ...emptyAddress(), ...Object.fromEntries(Object.entries(order.billing).filter(([, v]) => v !== null)) }
    state.is_gift = order.is_gift
    state.shipping = { ...emptyAddress(), ...Object.fromEntries(Object.entries(order.shipping ?? {}).filter(([, v]) => v !== null)) }
    state.gift_message = order.gift_message ?? ''
    state.payment_method = order.payment_method
    state.newsletter_opt_in = order.newsletter_opt_in
    const resumeAt = order.current_step as OrderStep | null
    step.value = resumeAt && ORDER_STEPS.includes(resumeAt) ? resumeAt : 'dedication'
  }

  async function restore() {
    const fromUrl = typeof route.query.t === 'string' ? route.query.t : null
    const candidate = fromUrl ?? storage('local')?.getItem(draftKey(slug.value))

    if (candidate) {
      try {
        const order = await $fetch<Order>(`/api/tenants/${slug.value}/orders/${candidate}`)
        if (order.status === 'draft') {
          hydrate(order)
          if (!fromUrl) router.replace({ query: { ...route.query, t: order.token } })
          return
        }
        if (fromUrl) {
          // Already submitted: show the tracking page instead.
          return navigateTo(path(`/bestellung/${order.token}`), { replace: true })
        }
      }
      catch (error) {
        // Unknown or foreign token: start fresh.
        if ((error as { statusCode?: number }).statusCode !== 404) reportApiError(error, 'order-restore')
      }
      forgetDraft()
    }

    const prefill = storage('session')?.getItem(PREFILL_KEY)
    if (prefill) {
      storage('session')?.removeItem(PREFILL_KEY)
      try {
        const data = JSON.parse(prefill) as { email: string, billing: Address, is_gift?: boolean }
        state.email = data.email
        state.billing = { ...emptyAddress(), ...data.billing }
        state.is_gift = data.is_gift ?? true
      }
      catch {
        // ignore malformed prefill
      }
    }
  }

  return {
    tenant, aktion, step, stepIndex, saving, errors, formError, token, state, quantities,
    totalCopies, maxCopies, subtotal, shippingCost, total, availableMethods,
    methodDisabled, bookTitle, setQuantity, next, back, goTo, restore,
  }
}

export type OrderForm = ReturnType<typeof useOrderForm>
