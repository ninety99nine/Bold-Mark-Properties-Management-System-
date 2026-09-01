import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Mock the API + toast composables before importing the component.
const post = vi.fn()
const get = vi.fn()
vi.mock('@/composables/useApi', () => ({ default: { post: (...a) => post(...a), get: (...a) => get(...a) } }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))

import TakeOnUpload from './TakeOnUpload.vue'

const baseProps = {
  communityId: 'c-1',
  templatePath: '/communities/c-1/owner-sheet/template',
  importPath: '/communities/c-1/owner-sheet/import',
  notApplicablePath: '/communities/c-1/take-on/owner_sheet/not-applicable',
  downloadBase: '/communities/c-1/take-on/items',
  entries: [],
  instruction: 'Please upload the owner sheet in Excel file.',
  linkText: 'Click here',
  linkSuffix: 'to download the customer template.',
}

function fileEvent() {
  const file = new File(['x'], 'owners.xlsx', { type: 'application/vnd.ms-excel' })
  return { target: { files: [file], value: '' } }
}

describe('TakeOnUpload', () => {
  beforeEach(() => {
    post.mockReset()
    get.mockReset()
  })

  it('renders the instruction and the download link', () => {
    const wrapper = mount(TakeOnUpload, { props: baseProps })
    expect(wrapper.text()).toContain('Please upload the owner sheet in Excel file.')
    expect(wrapper.text()).toContain('Click here')
    expect(wrapper.text()).toContain('to download the customer template.')
  })

  it('uploads and imports immediately on file selection (one-click), then emits changed', async () => {
    post.mockResolvedValueOnce({ data: { message: '2 units and 3 owners imported.', error_count: 0 } })

    const wrapper = mount(TakeOnUpload, { props: baseProps })
    await wrapper.vm.onFileChosen(fileEvent())
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/communities/c-1/owner-sheet/import', expect.any(FormData))
    expect(post).toHaveBeenCalledTimes(1) // no separate parse call
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('marks the step Not Applicable and emits changed', async () => {
    post.mockResolvedValueOnce({ data: { status: 'not_applicable', message: 'Take-on file status changed.' } })

    const wrapper = mount(TakeOnUpload, { props: baseProps })
    await wrapper.vm.markNotApplicable()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/communities/c-1/take-on/owner_sheet/not-applicable')
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('renders every history entry — a dated Download per upload, NOT APPLICABLE per marker', () => {
    const wrapper = mount(TakeOnUpload, {
      props: {
        ...baseProps,
        entries: [
          { id: 'e1', status: 'uploaded', file_name: 'a.xlsx', date: '2026-09-01' },
          { id: 'e2', status: 'uploaded', file_name: 'b.xlsx', date: '2026-09-02' },
          { id: 'e3', status: 'not_applicable' },
        ],
      },
    })
    expect(wrapper.text()).toContain('2026-09-01')
    expect(wrapper.text()).toContain('2026-09-02')
    expect(wrapper.findAll('button').filter((b) => b.text() === 'Download')).toHaveLength(2)
    expect(wrapper.text()).toContain('NOT APPLICABLE')
  })

  it('downloads a specific entry by its id', async () => {
    get.mockResolvedValueOnce({ data: new Blob(['x']) })
    // Stub object URL APIs used by the download helper.
    global.URL.createObjectURL = vi.fn(() => 'blob:x')
    global.URL.revokeObjectURL = vi.fn()

    const wrapper = mount(TakeOnUpload, {
      props: { ...baseProps, entries: [{ id: 'e9', status: 'uploaded', file_name: 'a.xlsx', date: '2026-09-01' }] },
    })
    await wrapper.vm.downloadEntry({ id: 'e9', file_name: 'a.xlsx' })
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/communities/c-1/take-on/items/e9/file', { responseType: 'blob' })
  })
})
