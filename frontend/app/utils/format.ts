// Swiss formatting: CHF 30.00, 15.10.2026, 15. Oktober 2026

export function formatChf(rappen: number): string {
  return `CHF ${(rappen / 100).toFixed(2)}`
}

function localeTag(locale: string): string {
  return ['de', 'fr', 'it', 'en'].includes(locale) ? `${locale}-CH` : 'de-CH'
}

export function formatDate(iso: string | null | undefined, locale = 'de', style: 'long' | 'short' = 'long'): string {
  if (!iso) return ''
  const date = new Date(iso.length === 10 ? `${iso}T12:00:00` : iso)
  return new Intl.DateTimeFormat(localeTag(locale), style === 'long'
    ? { day: 'numeric', month: 'long', year: 'numeric' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' },
  ).format(date)
}

export function formatDateTime(iso: string | null | undefined, locale = 'de'): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat(localeTag(locale), {
    day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(iso))
}

export function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' })[c]!)
}
