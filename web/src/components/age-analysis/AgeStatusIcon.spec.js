import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AgeStatusIcon from '@/components/age-analysis/AgeStatusIcon.vue'

// ──────────────────────────────────────────────────────────────────────────────
// AgeStatusIcon — WeConnectU collection-status marker mapping.
// ──────────────────────────────────────────────────────────────────────────────

describe('AgeStatusIcon', () => {
  it('renders no marker for status "none"', () => {
    const w = mount(AgeStatusIcon, { props: { status: 'none', label: '' } })
    expect(w.find('span').exists()).toBe(false)
  })

  it('renders a coloured dot with tooltip for 1st Notice', () => {
    const w = mount(AgeStatusIcon, { props: { status: 'first_notice', label: '1st Notice' } })
    expect(w.text()).toContain('1st Notice')
  })

  it('appends the customer name to the Handed Over tooltip', () => {
    const w = mount(AgeStatusIcon, {
      props: { status: 'handed_over', label: 'Handed over to Attorneys', customerName: 'R Lefakane' },
    })
    expect(w.text()).toContain('Handed over to Attorneys (R Lefakane)')
  })

  it('renders a document marker for Letter of Demand sent', () => {
    const w = mount(AgeStatusIcon, { props: { status: 'letter_of_demand', label: 'Letter of Demand sent' } })
    expect(w.findAll('svg').length).toBeGreaterThanOrEqual(1)
    expect(w.text()).toContain('Letter of Demand sent')
  })

  it('renders the transfer icon when transferActive even with no status', () => {
    const w = mount(AgeStatusIcon, { props: { status: 'none', label: '', transferActive: true } })
    expect(w.findAll('svg').length).toBeGreaterThanOrEqual(1)
  })
})
