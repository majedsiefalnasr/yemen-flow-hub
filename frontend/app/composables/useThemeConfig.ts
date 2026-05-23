/**
 * useThemeConfig - Access Shadcn-vue theme CSS variables
 *
 * This composable provides type-safe access to all theme colors and variables
 * defined in the Shadcn-vue tailwind.css configuration.
 *
 * Usage:
 * const { colors, getColor, radii, shadows } = useThemeConfig()
 * const primaryColor = getColor('primary') // returns oklch value
 *
 * All color values are OKLch format: oklch(lightness saturation hue)
 */

export const useThemeConfig = () => {
  const getCSSVariable = (varName: string): string => {
    if (typeof window === 'undefined') return ''
    return getComputedStyle(document.documentElement)
      .getPropertyValue(`--${varName}`)
      .trim()
  }

  const colors = {
    // Semantic colors
    background: () => getCSSVariable('background'),
    foreground: () => getCSSVariable('foreground'),
    card: () => getCSSVariable('card'),
    cardForeground: () => getCSSVariable('card-foreground'),
    popover: () => getCSSVariable('popover'),
    popoverForeground: () => getCSSVariable('popover-foreground'),
    primary: () => getCSSVariable('primary'),
    primaryForeground: () => getCSSVariable('primary-foreground'),
    secondary: () => getCSSVariable('secondary'),
    secondaryForeground: () => getCSSVariable('secondary-foreground'),
    muted: () => getCSSVariable('muted'),
    mutedForeground: () => getCSSVariable('muted-foreground'),
    accent: () => getCSSVariable('accent'),
    accentForeground: () => getCSSVariable('accent-foreground'),
    destructive: () => getCSSVariable('destructive'),
    destructiveForeground: () => getCSSVariable('destructive-foreground'),
    border: () => getCSSVariable('border'),
    input: () => getCSSVariable('input'),
    ring: () => getCSSVariable('ring'),
    success: () => getCSSVariable('success'),
    successForeground: () => getCSSVariable('success-foreground'),
    warning: () => getCSSVariable('warning'),
    warningForeground: () => getCSSVariable('warning-foreground'),
    info: () => getCSSVariable('info'),
    // Chart colors
    chart1: () => getCSSVariable('chart-1'),
    chart2: () => getCSSVariable('chart-2'),
    chart3: () => getCSSVariable('chart-3'),
    chart4: () => getCSSVariable('chart-4'),
    chart5: () => getCSSVariable('chart-5'),
    // Sidebar colors
    sidebar: () => getCSSVariable('sidebar'),
    sidebarForeground: () => getCSSVariable('sidebar-foreground'),
    sidebarPrimary: () => getCSSVariable('sidebar-primary'),
    sidebarPrimaryForeground: () => getCSSVariable('sidebar-primary-foreground'),
    sidebarAccent: () => getCSSVariable('sidebar-accent'),
    sidebarAccentForeground: () => getCSSVariable('sidebar-accent-foreground'),
    sidebarBorder: () => getCSSVariable('sidebar-border'),
    sidebarRing: () => getCSSVariable('sidebar-ring'),
  }

  const getColor = (colorName: keyof typeof colors): string => {
    const colorFn = colors[colorName]
    return colorFn ? colorFn() : ''
  }

  const radii = {
    sm: () => getCSSVariable('radius-sm'),
    md: () => getCSSVariable('radius-md'),
    lg: () => getCSSVariable('radius-lg'),
    xl: () => getCSSVariable('radius-xl'),
    '2xl': () => getCSSVariable('radius-2xl'),
  }

  const shadows = {
    soft: () => getCSSVariable('shadow-soft'),
    card: () => getCSSVariable('shadow-card'),
  }

  const gradients = {
    hero: () => getCSSVariable('gradient-hero'),
  }

  const fonts = {
    sans: () => getCSSVariable('font-sans'),
    display: () => getCSSVariable('font-display'),
    heading: () => getCSSVariable('font-heading'),
  }

  return {
    colors,
    getColor,
    radii,
    shadows,
    gradients,
    fonts,
    getCSSVariable,
  }
}
