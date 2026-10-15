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

export interface Tenant {
  name: string
  slug: string
  contact_email: string | null
  intro: string | null
  legal: { terms_url: string | null, privacy_url: string | null, imprint_url: string | null }
  max_copies_per_order: number
  dedication_max_length: number
  payment_methods: PaymentMethod[]
  shipping_tiers: ShippingTier[]
  newsletter: boolean
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
  description: string | null
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
  aktion: { name: string, order_deadline: string, signing_date: string, estimated_shipping_date: string }
  timeline: TimelineStep[]
  shipments: { shipped_at: string, carrier: string | null, tracking_number: string | null }[]
}
