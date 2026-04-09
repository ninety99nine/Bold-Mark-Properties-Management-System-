import { unref } from 'vue'
import { useRouter } from 'vue-router'

/**
 * useBack — smart back-navigation composable.
 *
 * Uses router.back() when Vue Router has recorded a previous in-app page
 * (window.history.state.back is set by Vue Router on every push/replace).
 * Falls back to `fallbackRoute` when the user arrived directly (e.g. typed
 * the URL, refreshed, or opened a link) so they never land on an unvisited page.
 *
 * @param {string|object} fallbackRoute  Route to push when no history exists.
 */
export function useBack(fallbackRoute) {
  const router = useRouter()

  function goBack() {
    if (window.history.state?.back) {
      router.back()
    } else {
      router.push(unref(fallbackRoute))
    }
  }

  return { goBack }
}
