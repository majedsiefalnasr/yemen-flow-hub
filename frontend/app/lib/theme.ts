/**
 * Theme utilities for Shadcn-vue integration
 *
 * Use these utilities to create theme-aware components that automatically
 * respond to Shadcn-vue theme configuration changes.
 */

/**
 * Color palette - all colors are defined as Tailwind classes
 * The actual CSS variable values are defined in tailwind.css
 */
export const themeColors = {
  // Semantic colors
  background: 'bg-background',
  foreground: 'text-foreground',
  card: 'bg-card',
  cardForeground: 'text-card-foreground',
  popover: 'bg-popover',
  popoverForeground: 'text-popover-foreground',
  primary: 'bg-primary',
  primaryForeground: 'text-primary-foreground',
  secondary: 'bg-secondary',
  secondaryForeground: 'text-secondary-foreground',
  muted: 'bg-muted',
  mutedForeground: 'text-muted-foreground',
  accent: 'bg-accent',
  accentForeground: 'text-accent-foreground',
  destructive: 'bg-destructive',
  destructiveForeground: 'text-destructive-foreground',
  border: 'border-border',
  input: 'bg-input',
  ring: 'ring-ring',
  success: 'text-success',
  successForeground: 'text-success-foreground',
  warning: 'text-warning',
  warningForeground: 'text-warning-foreground',
  info: 'text-info',
} as const

/**
 * Border radius utilities
 */
export const themeRadii = {
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  '2xl': 'rounded-2xl',
  full: 'rounded-full',
} as const

/**
 * Shadow utilities
 */
export const themeShadows = {
  soft: 'shadow-soft',
  card: 'shadow-card',
  none: 'shadow-none',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
} as const

/**
 * Gradient utilities
 */
export const themeGradients = {
  hero: 'bg-gradient-hero',
} as const

/**
 * Text color aliases for common use cases
 */
export const themeTextColors = {
  default: 'text-foreground',
  muted: 'text-muted-foreground',
  primary: 'text-primary',
  secondary: 'text-secondary',
  accent: 'text-accent',
  destructive: 'text-destructive',
  success: 'text-success',
  warning: 'text-warning',
  info: 'text-info',
} as const

/**
 * Background color aliases
 */
export const themeBackgrounds = {
  default: 'bg-background',
  card: 'bg-card',
  muted: 'bg-muted',
  primary: 'bg-primary',
  secondary: 'bg-secondary',
  destructive: 'bg-destructive',
  success: 'bg-success',
  warning: 'bg-warning',
} as const

/**
 * Create variant classes for theme-aware components
 *
 * @example
 * const buttonVariants = defineVariants({
 *   default: themeColors.primary,
 *   secondary: themeColors.secondary,
 *   ghost: themeColors.muted,
 * })
 */
export const defineVariants = (variants: Record<string, string>) => variants

/**
 * Common component presets using Shadcn-vue theme
 */
export const themePresets = {
  card: 'bg-card text-card-foreground border border-border rounded-lg shadow-card',
  input: 'bg-input border border-input rounded-md px-3 py-2 text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring',
  button: {
    default: 'bg-primary text-primary-foreground rounded-md px-4 py-2 font-medium hover:bg-primary/90 transition-colors',
    secondary: 'bg-secondary text-secondary-foreground rounded-md px-4 py-2 font-medium hover:bg-secondary/90 transition-colors',
    ghost: 'text-foreground hover:bg-muted rounded-md px-4 py-2 transition-colors',
    destructive: 'bg-destructive text-destructive-foreground rounded-md px-4 py-2 font-medium hover:bg-destructive/90 transition-colors',
  },
  dialog: 'fixed inset-0 z-50 bg-background/80 backdrop-blur-sm',
  dialogContent: 'bg-card text-card-foreground border border-border rounded-lg shadow-card p-6',
} as const

/**
 * Color value helpers - for components that need the actual color values
 * These work with the CSS variables defined in tailwind.css
 * Use only for cases where Tailwind classes don't suffice (e.g., SVG fills, canvas)
 */
export const getThemeColorValue = (colorName: string): string => {
  if (typeof window === 'undefined') return ''
  return getComputedStyle(document.documentElement)
    .getPropertyValue(`--${colorName}`)
    .trim()
}
