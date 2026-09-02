/**
 * debounce — delay invoking `fn` until `delay` ms have elapsed since the last
 * call. Used for search-as-you-type inputs so the API is only hit once the user
 * pauses. The returned function exposes `.cancel()` to drop a pending call.
 *
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function & { cancel: () => void }}
 */
export function debounce(fn, delay = 300) {
  let timer = null

  const debounced = (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }

  debounced.cancel = () => clearTimeout(timer)

  return debounced
}
