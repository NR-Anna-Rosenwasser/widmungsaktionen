// Shapes returned by the Laravel storefront API. Money is in Rappen.

export type PaymentMethod = 'online' | 'invoice_advance' | 'invoice_in_parcel'
export type OrderStatus = 'draft' | 'pending_payment' | 'confirmed' | 'signed' | 'shipped' | 'cancelled'
export type PaymentStatus = 'unpaid' | 'paid' | 'partially_refunded' | 'refunded'

export interface ResolveResponse {
  mode: 'custom_domain' | 'redirect' | 'picker'
  tenant: string | null
}

export interface TenantSummary {
  name: string
  slug: string
  authors: string[]
  open_aktion: { name: string, order_deadline: string } | null
}

export interface ShippingTier {
  max_copies: number
  method: 'letter' | 'parcel'
  price: number
}

/** locale => key => text. Markdown texts arrive as HTML. */
export type Texts<K extends string = string> = Record<string, Record<K, string>>

/** An Aktion's optional extra texts as HTML: locale => name => html|null. */
export type ExtraTexts<K extends string> = Record<string, Record<K, string | null>>

export interface StorefrontAppearance {
  colors: { primary: string, accent: string, accent_light: string }
  logo_url: string | null
  favicon_url: string | null
  hero: { visual: 'image' | 'heart' | 'none', image_url: string | null, image_alt: string, heart_text: string }
  sections: { books: boolean, how: boolean, lookup: boolean }
  seo: { image_url: string | null }
}

export interface Tenant {
  name: string
  slug: string
  contact_email: string | null
  legal: { terms_url: string | null, privacy_url: string | null, imprint_url: string | null }
  max_copies_per_order: number
  dedication_max_length: number
  /** Which options customers get per copy; a name is always possible. */
  dedication_options: { signature_only: boolean, wish: boolean }
  payment_methods: PaymentMethod[]
  shipping_tiers: ShippingTier[]
  newsletter: boolean
  storefront: StorefrontAppearance
  /** Edited in the admin (Einstellungen → Texte); read via $text / $textHtml. */
  texts: Texts
}

export interface Book {
  id: number
  title: string
  subtitle: string | null
  author_name: string
  description: string | null
  cover_url: string | null
  price: number
}

export interface Aktion {
  id: number
  name: string
  texts: ExtraTexts<'home' | 'order'>
  orders_open_at: string
  order_deadline: string
  signing_date: string
  estimated_shipping_date: string
  accepting_orders: boolean
  remaining_capacity: number | null
  books: Book[]
}

export interface StorefrontResponse {
  tenant: Tenant
  aktion: Aktion | null
  next_aktion: Aktion | null
  /** Set while the visitor uses an Aktion's secret test link. */
  preview: { name: string } | null
}

export interface Address {
  first_name: string | null
  last_name: string | null
  company: string | null
  street: string | null
  postal_code: string | null
  town: string | null
  country: string | null
}

export interface Copy {
  book_id: number
  title?: string
  unit_price?: number
  signature_only: boolean
  recipient_name: string | null
  wish: string | null
  signed?: boolean
}

export interface TimelineStep {
  key: 'submitted' | 'paid' | 'signed' | 'shipped' | 'cancelled'
  done: boolean
  at: string | null
}

export interface Order {
  token: string
  is_test: boolean
  number: string | null
  status: OrderStatus
  payment_status: PaymentStatus
  payment_method: PaymentMethod | null
  current_step: string | null
  email: string
  locale: string
  billing: Address
  is_gift: boolean
  shipping: Address | null
  gift_message: string | null
  newsletter_opt_in: boolean
  copies: Copy[]
  subtotal: number
  shipping_cost: number
  total: number
  submitted_at: string | null
  can_pay_online: boolean
  has_invoice: boolean
  aktion: { name: string, texts: ExtraTexts<'tracking'>, order_deadline: string, signing_date: string, estimated_shipping_date: string }
  timeline: TimelineStep[]
  shipments: { shipped_at: string, carrier: string | null, tracking_number: string | null }[]
}
