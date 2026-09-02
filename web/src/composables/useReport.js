import api from '@/composables/useApi'

/**
 * Shared helpers for the GL report pages (Trial Balance, General Ledger,
 * Income Statement, Actual vs Budget, VAT 201, Cash Movement).
 */

// WeConnectU money format: plain 2dp, space thousands, no currency symbol.
export function fmtMoney(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

/**
 * Fetch a report's xlsx export twin (?export=1) and trigger a browser download.
 *
 * @param {string} path        e.g. `/communities/1/reports/trial-balance`
 * @param {object} params      query params (export=1 is added automatically)
 * @param {string} fallbackName filename used when the server omits one
 */
export async function downloadReportExcel(path, params, fallbackName) {
  const res = await api.get(path, {
    params: { ...params, export: 1 },
    responseType: 'blob',
  })
  let filename = fallbackName
  const cd = res.headers['content-disposition'] || ''
  const m = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
  if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
  const url = URL.createObjectURL(res.data)
  const a = Object.assign(document.createElement('a'), { href: url, download: filename })
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
