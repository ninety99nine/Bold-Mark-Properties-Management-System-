import api from '@/composables/useApi.js'

/**
 * Composable for triggering authenticated file exports.
 * Calls the API endpoint with all current filter params + _format and _limit,
 * then triggers a browser file download from the blob response.
 */
export function useExport() {
  async function downloadExport(endpoint, params, fallbackFilename) {
    try {
      const { data, headers } = await api.get(endpoint, {
        params,
        responseType: 'blob',
      })

      let filename = fallbackFilename
      const cd = headers['content-disposition']
      if (cd) {
        const m = cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
        if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
      }

      const url  = URL.createObjectURL(new Blob([data]))
      const link = document.createElement('a')
      link.href     = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    } catch (e) {
      console.error('Export failed:', e)
    }
  }

  return { downloadExport }
}
