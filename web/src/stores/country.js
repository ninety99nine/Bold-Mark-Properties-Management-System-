import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

/**
 * Country-to-currency mapping (mirrors backend CountryHelper).
 */
const COUNTRY_MAP = {
  ZA: { name: 'South Africa', currencyCode: 'ZAR', symbol: 'R', flag: '\u{1F1FF}\u{1F1E6}' },
  BW: { name: 'Botswana',     currencyCode: 'BWP', symbol: 'P', flag: '\u{1F1E7}\u{1F1FC}' },
}

export const useCountryStore = defineStore('country', () => {
  /** All countries that have at least one estate */
  const countries = ref([])

  /** The tenant's default country (from company settings) */
  const defaultCountry = ref(null)

  /** The currently selected country code (e.g. 'ZA', 'BW') */
  const selected = ref(localStorage.getItem('selected_country') || null)

  /** Whether data has been fetched */
  const loaded = ref(false)

  /** Whether the tenant has estates in more than one country */
  const isMultiCountry = computed(() => countries.value.length > 1)

  /** The active country code — resolved with fallback logic */
  const activeCountry = computed(() => {
    // 1. Use explicitly selected country if it exists in available countries
    if (selected.value && countries.value.some(c => c.code === selected.value)) {
      return selected.value
    }
    // 2. Fall back to tenant default if it exists in available countries
    if (defaultCountry.value && countries.value.some(c => c.code === defaultCountry.value)) {
      return defaultCountry.value
    }
    // 3. Fall back to first available country
    return countries.value[0]?.code || null
  })

  /** Full details of the active country */
  const activeCountryInfo = computed(() => {
    if (!activeCountry.value) return null
    return COUNTRY_MAP[activeCountry.value] || null
  })

  /** Currency symbol for the active country (e.g. 'R', 'P') */
  const currencySymbol = computed(() => activeCountryInfo.value?.symbol || 'R')

  /** Currency code for the active country (e.g. 'ZAR', 'BWP') */
  const currencyCode = computed(() => activeCountryInfo.value?.currencyCode || 'ZAR')

  /**
   * Fetch available countries from the API.
   */
  async function fetch() {
    try {
      const { data } = await api.get('/dashboard/countries')
      countries.value = (data.countries || []).map(c => ({
        code: c.code,
        name: c.name || COUNTRY_MAP[c.code]?.name || c.code,
        flag: c.flag || COUNTRY_MAP[c.code]?.flag || '',
        currencyCode: c.currency_code || COUNTRY_MAP[c.code]?.currencyCode,
        symbol: c.currency_symbol || COUNTRY_MAP[c.code]?.symbol,
        estateCount: c.estate_count ?? 0,
      }))
      defaultCountry.value = data.default_country || null
      loaded.value = true
    } catch {
      // Silently fail — countries will be empty, UI works as single-country
    }
  }

  /**
   * Select a country and persist to localStorage.
   */
  function select(countryCode) {
    selected.value = countryCode
    localStorage.setItem('selected_country', countryCode)
  }

  /**
   * Format a currency amount using the active country's symbol.
   * e.g. formatCurrency(50000) → 'R\u00a050\u00a0000' or 'P\u00a050\u00a0000'
   */
  function formatCurrency(amount) {
    if (amount == null) return `${currencySymbol.value}\u00a00.00`
    const num = Number(amount)
    if (isNaN(num)) return `${currencySymbol.value}\u00a00.00`
    const [intPart, decPart] = num.toFixed(2).split('.')
    const formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
    return `${currencySymbol.value}\u00a0${formatted}.${decPart}`
  }

  /**
   * Compact currency format for chart axes (e.g. 'R 50k', 'P 2.5M').
   */
  function formatCurrencyCompact(amount) {
    const sym = currencySymbol.value
    if (amount >= 1_000_000) return `${sym} ${(amount / 1_000_000).toFixed(1)}M`
    if (amount >= 1_000) return `${sym} ${Math.round(amount / 1_000)}k`
    return `${sym} ${amount}`
  }

  return {
    countries,
    defaultCountry,
    selected,
    loaded,
    isMultiCountry,
    activeCountry,
    activeCountryInfo,
    currencySymbol,
    currencyCode,
    fetch,
    select,
    formatCurrency,
    formatCurrencyCompact,
    COUNTRY_MAP,
  }
})
