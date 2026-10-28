import type { Tenant } from '#shared/types/api'

/**
 * Shop colours as CSS variables on <html>. The derived shades mirror the
 * defaults in main.css, so any brand colour gets a matching palette.
 */
export function storefrontColorStyle(tenant: Tenant | undefined | null): string | undefined {
  const colors = tenant?.storefront.colors
  if (!colors) return undefined

  return [
    `--color-navy: ${colors.primary}`,
    `--color-ink: ${colors.primary}`,
    `--color-navy-soft: color-mix(in srgb, ${colors.primary} 82%, white)`,
    `--color-rose: ${colors.accent}`,
    `--color-rose-dark: color-mix(in srgb, ${colors.accent} 82%, black)`,
    `--color-blush: ${colors.accent_light}`,
    `--color-blush-light: color-mix(in srgb, ${colors.accent_light} 45%, white)`,
  ].join('; ')
}
