import { defineStore } from 'pinia'
import { useOrgStore } from '@/stores/org.store'

export type ThemeMode = 'system' | 'light' | 'dark'
export type FontFamily = string
export type LayoutMode = 'boxed' | 'full'
export type FontSource = 'google' | 'fallback'
export type AutoplayPreference = 'system' | 'enabled' | 'disabled'
export type RadiusPreference = 'none' | 'sm' | 'md' | 'lg' | 'xl'
export type SidebarVariant = 'sidebar' | 'floating' | 'inset'
export type SidebarCollapsible = 'offcanvas' | 'icon' | 'none'
export type DensityPreference = 'comfortable' | 'compact'
export type ReducedMotionPreference = 'system' | 'always'

export interface GoogleFontOption {
  value: string
  label: string
  category: string
  subsets: string[]
  variants: string[]
  source: FontSource
}

interface BrandingChannels {
  securityQuestionnaires: boolean
  emails: boolean
  vendorReports: boolean
}

interface BrandingSettings {
  brandColor?: string
  brandLogoName?: string | null
  brandLogoDataUrl?: string | null
  brandingPublished?: boolean
  brandingChannels?: Partial<BrandingChannels>
}

interface SystemSettings {
  version?: string
  general?: {
    platformName?: string
    authority?: string
  }
  branding?: BrandingSettings
}

interface ThemingState {
  mode: ThemeMode
  font: FontFamily
  layout: LayoutMode
  brandColor: string
  brandLogoName: string
  brandingPublished: boolean
  brandingChannels: BrandingChannels
  shortcutsRequireModifier: boolean
  highContrast: boolean
  autoplayVideos: AutoplayPreference
  openLinksInDesktop: boolean
  fontOptions: GoogleFontOption[]
  fontsLoading: boolean
  fontsError: string | null
  fontSource: FontSource
  isLoading: boolean
  radius: RadiusPreference
  sidebarVariant: SidebarVariant
  sidebarCollapsible: SidebarCollapsible
  density: DensityPreference
  reducedMotion: ReducedMotionPreference
}

type AppearanceSettings = Partial<Pick<
  ThemingState,
  'mode' | 'font' | 'layout' | 'radius' | 'sidebarVariant' | 'sidebarCollapsible' | 'density' | 'reducedMotion'
>>

const STORAGE_KEY = 'appearance-settings-cache'
const SETTINGS_SYNC_EVENT = 'yfh-system-settings-sync'
const USER_THEMING_DEBOUNCE_MS = 600
let syncListenerRegistered = false
let userThemingSaveTimer: ReturnType<typeof setTimeout> | null = null
const PINNED_FONT_FAMILIES = ['IBM Plex Sans Arabic', 'Cairo', 'Tajawal', 'Inter']

const LEGACY_FONT_KEYS: Record<string, string> = {
  inter: 'Inter',
  cairo: 'Cairo',
  almarai: 'Almarai',
  amiri: 'Amiri',
  'ibm-plex-sans-arabic': 'IBM Plex Sans Arabic',
  'noto-sans-arabic': 'Noto Sans Arabic',
  tajawal: 'Tajawal',
  lato: 'Lato',
  'josefin-sans': 'Josefin Sans',
  montserrat: 'Montserrat',
  nunito: 'Nunito',
  'open-sans': 'Open Sans',
  poppins: 'Poppins',
  raleway: 'Raleway',
  roboto: 'Roboto',
  'source-sans-3': 'Source Sans 3',
  lora: 'Lora',
  merriweather: 'Merriweather',
  'playfair-display': 'Playfair Display',
}

const FALLBACK_FONT_OPTIONS: GoogleFontOption[] = [
  { value: 'IBM Plex Sans Arabic', label: 'IBM Plex Sans Arabic', category: 'Arabic', subsets: ['arabic', 'latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Cairo', label: 'Cairo', category: 'Arabic', subsets: ['arabic', 'latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Tajawal', label: 'Tajawal', category: 'Arabic', subsets: ['arabic', 'latin'], variants: ['regular', '500', '700'], source: 'fallback' },
  { value: 'Inter', label: 'Inter', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Almarai', label: 'Almarai', category: 'Arabic', subsets: ['arabic'], variants: ['regular', '700'], source: 'fallback' },
  { value: 'Amiri', label: 'Amiri', category: 'Arabic Serif', subsets: ['arabic'], variants: ['regular', '700'], source: 'fallback' },
  { value: 'Noto Sans Arabic', label: 'Noto Sans Arabic', category: 'Arabic', subsets: ['arabic', 'latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Lato', label: 'Lato', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '700'], source: 'fallback' },
  { value: 'Josefin Sans', label: 'Josefin Sans', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '600', '700'], source: 'fallback' },
  { value: 'Montserrat', label: 'Montserrat', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Nunito', label: 'Nunito', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '600', '700'], source: 'fallback' },
  { value: 'Open Sans', label: 'Open Sans', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Poppins', label: 'Poppins', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Raleway', label: 'Raleway', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Roboto', label: 'Roboto', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '700'], source: 'fallback' },
  { value: 'Source Sans 3', label: 'Source Sans 3', category: 'Sans Serif', subsets: ['latin'], variants: ['regular', '500', '600', '700'], source: 'fallback' },
  { value: 'Lora', label: 'Lora', category: 'Serif', subsets: ['latin'], variants: ['regular', '600', '700'], source: 'fallback' },
  { value: 'Merriweather', label: 'Merriweather', category: 'Serif', subsets: ['latin'], variants: ['regular', '700'], source: 'fallback' },
  { value: 'Playfair Display', label: 'Playfair Display', category: 'Serif', subsets: ['latin'], variants: ['regular', '600', '700'], source: 'fallback' },
]

interface GoogleFontsApiItem {
  family: string
  category?: string
  subsets?: string[]
  variants?: string[]
}

function normalizeFontFamily(font: string | null | undefined): string {
  if (!font) return 'IBM Plex Sans Arabic'
  const trimmed = font.trim()
  const legacy = LEGACY_FONT_KEYS[trimmed.toLowerCase()]
  return legacy || trimmed
}

function uniqueByFamily(fonts: GoogleFontOption[]): GoogleFontOption[] {
  const seen = new Set<string>()
  return fonts.filter((font) => {
    const key = font.value.toLowerCase()
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

function categoryLabel(category: string | undefined, subsets: string[] = []): string {
  if (subsets.includes('arabic')) {
    return category === 'serif' ? 'Arabic Serif' : 'Arabic'
  }
  if (!category) return 'Sans Serif'
  return category
    .split(' ')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function sanitizeBrandColor(color: string): string {
  return /^#[0-9a-f]{6}$/i.test(color) ? color : '#0066cc'
}

function hasAuthenticatedSessionHint(): boolean {
  if (typeof localStorage === 'undefined') return false

  return localStorage.getItem('yfh-authenticated') === '1'
    || Boolean(localStorage.getItem('yfh-api-token'))
}

export const useThemingStore = defineStore('theming', {
  state: (): ThemingState => ({
    mode: 'system',
    font: 'IBM Plex Sans Arabic',
    layout: 'boxed',
    brandColor: '#0066cc',
    brandLogoName: '',
    brandingPublished: true,
    brandingChannels: {
      securityQuestionnaires: false,
      emails: true,
      vendorReports: true,
    },
    shortcutsRequireModifier: true,
    highContrast: false,
    autoplayVideos: 'system',
    openLinksInDesktop: true,
    fontOptions: FALLBACK_FONT_OPTIONS,
    fontsLoading: false,
    fontsError: null,
    fontSource: 'fallback',
    isLoading: false,
    radius: 'md',
    sidebarVariant: 'sidebar',
    sidebarCollapsible: 'icon',
    density: 'comfortable',
    reducedMotion: 'system',
  }),

  getters: {
    isDark: (state) => {
      if (state.mode === 'system') {
        return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      return state.mode === 'dark'
    },

    pinnedFonts: (state) => {
      const pinned = PINNED_FONT_FAMILIES
        .map((family) => state.fontOptions.find(font => font.value === family))
        .filter(Boolean) as GoogleFontOption[]
      return pinned.length ? pinned : state.fontOptions.slice(0, 4)
    },

    searchableFonts: (state) => {
      return state.fontOptions.filter(font => !PINNED_FONT_FAMILIES.includes(font.value))
    },

    selectedFontLabel: (state) => {
      const family = normalizeFontFamily(state.font)
      return state.fontOptions.find(font => font.value === family)?.label || family
    },

    prefersReducedMotion: (state): boolean => {
      if (state.reducedMotion === 'always') return true
      return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    },
  },

  actions: {
    async loadGoogleFonts() {
      if (this.fontsLoading) return

      const config = useRuntimeConfig()
      const apiKey = String(config.public.googleFontsApiKey || '')

      if (!apiKey) {
        this.fontOptions = FALLBACK_FONT_OPTIONS
        this.fontSource = 'fallback'
        this.fontsError = 'Google Fonts API key is not configured'
        return
      }

      this.fontsLoading = true
      this.fontsError = null

      try {
        const response = await fetch(`https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=${encodeURIComponent(apiKey)}`)
        if (!response.ok) {
          throw new Error(`Google Fonts API returned ${response.status}`)
        }

        const payload = await response.json() as { items?: GoogleFontsApiItem[] }
        const fonts = (payload.items || []).map<GoogleFontOption>((item) => ({
          value: item.family,
          label: item.family,
          category: categoryLabel(item.category, item.subsets),
          subsets: item.subsets || [],
          variants: item.variants || [],
          source: 'google',
        }))

        this.fontOptions = uniqueByFamily([
          ...FALLBACK_FONT_OPTIONS.map(font => ({ ...font, source: 'google' as FontSource })),
          ...fonts,
        ])
        this.fontSource = 'google'
      } catch (error) {
        console.error('Failed to load Google Fonts catalog:', error)
        this.fontOptions = FALLBACK_FONT_OPTIONS
        this.fontSource = 'fallback'
        this.fontsError = 'Failed to load Google Fonts catalog'
      } finally {
        this.fontsLoading = false
      }
    },

    setMode(mode: ThemeMode, event?: MouseEvent) {
      this.mode = mode

      if (
        typeof document !== 'undefined'
        && 'startViewTransition' in document
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
      ) {
        const x = event?.clientX ?? window.innerWidth / 2
        const y = event?.clientY ?? window.innerHeight / 2
        document.documentElement.style.setProperty('--vt-x', `${x}px`)
        document.documentElement.style.setProperty('--vt-y', `${y}px`)
        document.documentElement.classList.add('circular-transition-active')
        ;(document as any).startViewTransition(() => {
          this.applyTheme()
        })
        setTimeout(() => {
          document.documentElement.classList.remove('circular-transition-active')
        }, 600)
      } else {
        this.applyTheme()
      }

      this.persistToCache()
      this.queueUserThemingSave()
    },

    setFont(font: FontFamily) {
      this.font = normalizeFontFamily(font)
      this.applyFont()
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setLayout(layout: LayoutMode) {
      this.layout = layout
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setBrandColor(color: string) {
      this.brandColor = sanitizeBrandColor(color)
      this.applyBranding()
      this.persistToCache()
    },

    setBrandLogoName(name: string) {
      this.brandLogoName = name
      this.persistToCache()
    },

    setBrandingPublished(value: boolean) {
      this.brandingPublished = value
      this.persistToCache()
    },

    setBrandingChannel(channel: keyof BrandingChannels, value: boolean) {
      this.brandingChannels[channel] = value
      this.persistToCache()
    },

    setShortcutsRequireModifier(value: boolean) {
      this.shortcutsRequireModifier = value
      this.persistToCache()
    },

    setHighContrast(value: boolean) {
      this.highContrast = value
      this.applyHighContrast()
      this.persistToCache()
    },

    setAutoplayVideos(value: AutoplayPreference) {
      this.autoplayVideos = value
      this.persistToCache()
    },

    setOpenLinksInDesktop(value: boolean) {
      this.openLinksInDesktop = value
      this.persistToCache()
    },

    setRadius(value: RadiusPreference) {
      this.radius = value
      this.applyRadius()
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setSidebarVariant(value: SidebarVariant) {
      this.sidebarVariant = value
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setSidebarCollapsible(value: SidebarCollapsible) {
      this.sidebarCollapsible = value
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setDensity(value: DensityPreference) {
      this.density = value
      this.applyDensity()
      this.persistToCache()
      this.queueUserThemingSave()
    },

    setReducedMotion(value: ReducedMotionPreference) {
      this.reducedMotion = value
      this.persistToCache()
      this.queueUserThemingSave()
    },

    applyTheme() {
      if (typeof document === 'undefined') return

      const html = document.documentElement
      if (this.isDark) {
        html.classList.add('dark')
      } else {
        html.classList.remove('dark')
      }
    },

    applyFont() {
      if (typeof document === 'undefined') return

      this.font = normalizeFontFamily(this.font)

      const existingLink = document.getElementById('theming-font-link')
      if (existingLink) {
        existingLink.remove()
      }

      const fontStack = this.getFontStack()
      document.documentElement.style.setProperty('--font-sans', fontStack)
      document.documentElement.style.setProperty('--font-heading', fontStack)
      document.documentElement.style.setProperty('--font-section', fontStack)

      const link = document.createElement('link')
      link.id = 'theming-font-link'
      link.rel = 'stylesheet'
      link.href = `https://fonts.googleapis.com/css2?family=${this.getCss2FamilyParam()}&display=swap`
      document.head.appendChild(link)
    },

    applyBranding() {
      if (typeof document === 'undefined') return

      const color = sanitizeBrandColor(this.brandColor)
      const root = document.documentElement
      // Only set the base token — CSS derives --brand-color and --primary
      // automatically for both light and dark mode via color-mix()
      root.style.setProperty('--brand-color-base', color)
      root.style.removeProperty('--primary')
      root.style.removeProperty('--primary-foreground')
      root.style.setProperty('--ring', color)
    },

    applySystemSettings(settings: SystemSettings | null | undefined) {
      if (!settings) return

      const branding = settings.branding || {}
      if (typeof branding.brandColor === 'string') {
        this.brandColor = sanitizeBrandColor(branding.brandColor)
      }
      if (typeof branding.brandLogoName === 'string') {
        this.brandLogoName = branding.brandLogoName
      }
      if (typeof branding.brandingPublished === 'boolean') {
        this.brandingPublished = branding.brandingPublished
      }
      if (branding.brandingChannels) {
        this.brandingChannels = {
          securityQuestionnaires: branding.brandingChannels.securityQuestionnaires ?? false,
          emails: branding.brandingChannels.emails ?? true,
          vendorReports: branding.brandingChannels.vendorReports ?? true,
        }
      }

      useOrgStore().applySystemSettings(settings.general, branding, settings.version)
      this.applyBranding()
    },

    applyHighContrast() {
      if (typeof document === 'undefined') return
      document.documentElement.classList.toggle('high-contrast', this.highContrast)
    },

    applyRadius() {
      if (typeof document === 'undefined') return
      const radiusMap: Record<RadiusPreference, string> = {
        none: '0px',
        sm: '0.25rem',
        md: '0.5rem',
        lg: '0.75rem',
        xl: '1rem',
      }
      document.documentElement.style.setProperty('--radius', radiusMap[this.radius] ?? '0.5rem')
    },

    applyDensity() {
      if (typeof document === 'undefined') return
      document.documentElement.setAttribute('data-density', this.density)
    },

    getFontStack(): string {
      const family = normalizeFontFamily(this.font)
      const selected = this.fontOptions.find(font => font.value === family)
      const serifFallback = selected?.category.toLowerCase().includes('serif') ? 'serif' : 'sans-serif'
      return `'${family}', 'IBM Plex Sans Arabic', 'Inter Variable', system-ui, -apple-system, ${serifFallback}`
    },

    getCss2FamilyParam(): string {
      const family = normalizeFontFamily(this.font)
      const selected = this.fontOptions.find(font => font.value === family)
      const weights = (selected?.variants || [])
        .map(variant => variant === 'regular' ? '400' : variant)
        .filter(variant => /^\d+$/.test(variant))
      const supportedWeights = ['400', '500', '600', '700'].filter(weight => weights.length === 0 || weights.includes(weight))
      const encodedFamily = family.trim().replace(/\s+/g, '+')

      if (!supportedWeights.length) {
        return encodedFamily
      }

      return `${encodedFamily}:wght@${supportedWeights.join(';')}`
    },

    async loadSettings() {
      if (typeof window === 'undefined') return

      this.isLoading = true
      this.listenForSystemSettingsSync()

      try {
        this.loadFromCache()
        await this.loadFromServer()
        // loadGoogleFonts() is deferred — called lazily when the font combobox opens
        this.applyTheme()
        this.applyFont()
        this.applyBranding()
        this.applyHighContrast()
        this.applyRadius()
        this.applyDensity()
      } finally {
        this.isLoading = false
      }
    },

    applyAppearanceSettings(settings: AppearanceSettings | null | undefined) {
      if (!settings || typeof settings !== 'object') return

      if (['system', 'light', 'dark'].includes(settings.mode ?? '')) {
        this.mode = settings.mode as ThemeMode
      }
      if (typeof settings.font === 'string' && settings.font.trim()) {
        this.font = normalizeFontFamily(settings.font)
      }
      if (settings.layout === 'boxed' || settings.layout === 'full') {
        this.layout = settings.layout
      }
      if ((['none', 'sm', 'md', 'lg', 'xl'] as string[]).includes(settings.radius ?? '')) {
        this.radius = settings.radius as RadiusPreference
      }
      if ((['sidebar', 'floating', 'inset'] as string[]).includes(settings.sidebarVariant ?? '')) {
        this.sidebarVariant = settings.sidebarVariant as SidebarVariant
      }
      if ((['offcanvas', 'icon', 'none'] as string[]).includes(settings.sidebarCollapsible ?? '')) {
        this.sidebarCollapsible = settings.sidebarCollapsible as SidebarCollapsible
      }
      if ((['comfortable', 'compact'] as string[]).includes(settings.density ?? '')) {
        this.density = settings.density as DensityPreference
      }
      if ((['system', 'always'] as string[]).includes(settings.reducedMotion ?? '')) {
        this.reducedMotion = settings.reducedMotion as ReducedMotionPreference
      }
    },

    async loadFromServer() {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      const authenticated = hasAuthenticatedSessionHint()
      const endpoint = authenticated ? '/api/settings' : '/api/settings/public'

      try {
        const response = await $fetch<{ data?: { theming?: AppearanceSettings, system?: SystemSettings } | SystemSettings }>(endpoint, {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        if (authenticated && response.data && 'system' in response.data) {
          this.applyAppearanceSettings(response.data.theming)
          this.applySystemSettings(response.data.system)
        } else {
          this.applySystemSettings(response.data as SystemSettings)
        }
        this.persistToCache()
      } catch {
        // Unauthenticated first paint or temporary API failure: keep the local
        // cache fallback, but never treat cache as the source of truth.
      }
    },

    /**
     * Schedule a debounced write-through of the current user-level theming
     * preferences to the backend. The localStorage cache has already been
     * updated synchronously by the caller — this method is responsible for
     * keeping the database (the source of truth) in lockstep so that the same
     * preferences apply on every device the user signs in from.
     *
     * No-op when the user is unauthenticated (settings are local-only until a
     * session exists). Failures are silent: the local change still applies and
     * the next successful save will reconcile state.
     */
    queueUserThemingSave() {
      if (typeof window === 'undefined') return
      if (!hasAuthenticatedSessionHint()) return

      if (userThemingSaveTimer) clearTimeout(userThemingSaveTimer)
      userThemingSaveTimer = setTimeout(() => {
        userThemingSaveTimer = null
        void this.flushUserThemingSave()
      }, USER_THEMING_DEBOUNCE_MS)
    },

    async flushUserThemingSave() {
      if (typeof window === 'undefined') return
      if (!hasAuthenticatedSessionHint()) return

      const payload = {
        mode: this.mode,
        font: this.font,
        layout: this.layout,
        sidebarVariant: this.sidebarVariant,
        sidebarCollapsible: this.sidebarCollapsible,
        radius: this.radius,
        density: this.density,
        reducedMotion: this.reducedMotion,
      }

      try {
        const config = useRuntimeConfig()
        const baseURL = config.public.apiBase as string
        await $fetch(`${baseURL}/api/settings/save-section`, {
          method: 'POST',
          credentials: 'include',
          headers: { Accept: 'application/json' },
          body: { section: 'theming', subsection: null, data: payload },
        })
      } catch (error) {
        // The local cache still reflects the user's choice; the next
        // successful save (or page reload) will reconcile the backend. We
        // surface nothing here to avoid noisy toasts during rapid clicks.
        if (import.meta.dev) {
          console.warn('Failed to persist user theming preferences:', error)
        }
      }
    },

    persistToCache() {
      if (typeof localStorage === 'undefined') return

      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          mode: this.mode,
          font: this.font,
          layout: this.layout,
          brandColor: this.brandColor,
          brandLogoName: this.brandLogoName,
          brandingPublished: this.brandingPublished,
          brandingChannels: this.brandingChannels,
          shortcutsRequireModifier: this.shortcutsRequireModifier,
          highContrast: this.highContrast,
          autoplayVideos: this.autoplayVideos,
          openLinksInDesktop: this.openLinksInDesktop,
          radius: this.radius,
          sidebarVariant: this.sidebarVariant,
          sidebarCollapsible: this.sidebarCollapsible,
          density: this.density,
          reducedMotion: this.reducedMotion,
        }),
      )
    },

    loadFromCache() {
      if (typeof localStorage === 'undefined') return

      const cached = localStorage.getItem(STORAGE_KEY) || localStorage.getItem('theming-settings-cache')
      if (!cached) return

      try {
        const parsed = JSON.parse(cached)
        this.mode = parsed.mode || 'system'
        this.font = normalizeFontFamily(parsed.font || 'IBM Plex Sans Arabic')
        this.layout = parsed.layout === 'boxy' || parsed.layout === 'boxed' ? 'boxed' : 'full'
        this.brandColor = sanitizeBrandColor(parsed.brandColor || '#0066cc')
        this.brandLogoName = parsed.brandLogoName || ''
        this.brandingPublished = parsed.brandingPublished ?? true
        this.brandingChannels = {
          securityQuestionnaires: parsed.brandingChannels?.securityQuestionnaires ?? false,
          emails: parsed.brandingChannels?.emails ?? true,
          vendorReports: parsed.brandingChannels?.vendorReports ?? true,
        }
        this.shortcutsRequireModifier = parsed.shortcutsRequireModifier ?? true
        this.highContrast = parsed.highContrast ?? false
        this.autoplayVideos = parsed.autoplayVideos || 'system'
        this.openLinksInDesktop = parsed.openLinksInDesktop ?? true
        this.radius = (['none', 'sm', 'md', 'lg', 'xl'] as RadiusPreference[]).includes(parsed.radius) ? parsed.radius : 'md'
        this.sidebarVariant = (['sidebar', 'floating', 'inset'] as SidebarVariant[]).includes(parsed.sidebarVariant) ? parsed.sidebarVariant : 'sidebar'
        this.sidebarCollapsible = (['offcanvas', 'icon', 'none'] as SidebarCollapsible[]).includes(parsed.sidebarCollapsible) ? parsed.sidebarCollapsible : 'icon'
        this.density = (['comfortable', 'compact'] as DensityPreference[]).includes(parsed.density) ? parsed.density : 'comfortable'
        this.reducedMotion = (['system', 'always'] as ReducedMotionPreference[]).includes(parsed.reducedMotion) ? parsed.reducedMotion : 'system'
      } catch (error) {
        console.error('Failed to load appearance cache:', error)
      }
    },

    publishSystemSettingsSync() {
      if (typeof window === 'undefined') return

      const payload = JSON.stringify({ version: useOrgStore().systemVersion, at: Date.now() })
      localStorage.setItem(SETTINGS_SYNC_EVENT, payload)
      window.dispatchEvent(new StorageEvent('storage', { key: SETTINGS_SYNC_EVENT, newValue: payload }))
    },

    listenForSystemSettingsSync() {
      if (typeof window === 'undefined' || syncListenerRegistered) return
      syncListenerRegistered = true

      window.addEventListener('storage', async (event) => {
        if (event.key !== SETTINGS_SYNC_EVENT) return
        await this.loadFromServer()
        this.applyBranding()
      })
    },
  },
})
