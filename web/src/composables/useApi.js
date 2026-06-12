import axios from 'axios'
import { getToken } from '@/composables/authStorage'

const api = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL || ''}/api/v1`,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Attach auth token to every request
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // When sending FormData, remove the default Content-Type so Axios can
  // set multipart/form-data with the correct boundary automatically.
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }
  return config
})

// Handle auth errors globally. On a 401 the session is over (expired through
// inactivity, revoked, or the token is invalid): clear it and bounce to the
// login page via the router — preserving the page the user was on so they
// land back on it after signing in (BM-006). We use the router rather than a
// hard `window.location` reload so the SPA stays warm and the redirect query
// survives. Imports are dynamic to avoid a circular dependency with the store.
let redirecting = false

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !redirecting) {
      redirecting = true
      try {
        const [{ useAuthStore }, { default: router }] = await Promise.all([
          import('@/stores/auth'),
          import('@/router'),
        ])
        useAuthStore().clearSession()

        const current = router.currentRoute.value
        if (current.name !== 'login') {
          await router.replace({
            name: 'login',
            query: { redirect: current.fullPath, expired: '1' },
          })
        }
      } finally {
        redirecting = false
      }
    }
    return Promise.reject(error)
  }
)

export default api
