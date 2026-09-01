import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EmailTagsInput from './EmailTagsInput.vue'

describe('EmailTagsInput', () => {
  it('turns a comma-separated value into a tag on comma', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const input = wrapper.find('input')
    await input.setValue('john@doe.com')
    await input.trigger('keydown', { key: ',' })

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted[emitted.length - 1][0]).toEqual(['john@doe.com'])
    expect(wrapper.text()).toContain('john@doe.com')
  })

  it('creates a tag on Enter', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const input = wrapper.find('input')
    await input.setValue('jane@doe.com')
    await input.trigger('keydown', { key: 'Enter' })

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted[emitted.length - 1][0]).toEqual(['jane@doe.com'])
  })

  it('commits a valid address on blur', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const input = wrapper.find('input')
    await input.setValue('valid@x.com')
    await input.trigger('blur')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted[emitted.length - 1][0]).toEqual(['valid@x.com'])
  })

  it('marks an invalid address and reports invalid', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const input = wrapper.find('input')
    await input.setValue('not-an-email')
    await input.trigger('blur')

    const validEmits = wrapper.emitted('update:valid')
    expect(validEmits[validEmits.length - 1][0]).toBe(false)
    // Renders the red helper text
    expect(wrapper.text()).toContain('Please fix the highlighted')
  })

  it('reports valid=true when empty (optional field)', () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const validEmits = wrapper.emitted('update:valid')
    expect(validEmits[0][0]).toBe(true)
  })

  it('splits a pasted comma list into multiple tags', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: [] } })
    const input = wrapper.find('input')
    await input.trigger('paste', {
      clipboardData: { getData: () => 'a@b.com, c@d.com;e@f.com' },
    })

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted[emitted.length - 1][0]).toEqual(['a@b.com', 'c@d.com', 'e@f.com'])
  })

  it('removes a tag when its cancel button is clicked', async () => {
    const wrapper = mount(EmailTagsInput, { props: { modelValue: ['a@b.com', 'c@d.com'] } })
    await wrapper.findAll('span button')[0].trigger('click')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted[emitted.length - 1][0]).toEqual(['c@d.com'])
  })
})
