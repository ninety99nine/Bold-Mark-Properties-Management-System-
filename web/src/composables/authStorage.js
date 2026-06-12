// Auth token persistence (BM-009 / BUG-004).
//
// A non-persistent login — "Keep me signed in" left unchecked — must NOT
// survive the browser being closed. sessionStorage is scoped to the browsing
// session and is cleared once the last tab for the origin is closed, so we use
// it for normal sessions and only fall back to localStorage when the user has
// explicitly opted into a persistent ("remember me") session.

const TOKEN_KEY    = 'auth_token'
const REMEMBER_KEY = 'auth_remember'

/** Read the active token from whichever store currently holds it. */
export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY) || null
}

/** Whether the current session was created as persistent ("remember me"). */
export function isPersistent() {
  return localStorage.getItem(REMEMBER_KEY) === '1'
}

/**
 * Persist a freshly issued token. Persistent sessions go to localStorage so
 * they survive a browser restart; non-persistent ones go to sessionStorage so
 * they do not.
 */
export function storeToken(token, remember) {
  // Drop any prior copy first so the token lives in exactly one store and the
  // remember flag stays authoritative.
  clearToken()
  if (remember) {
    localStorage.setItem(TOKEN_KEY, token)
    localStorage.setItem(REMEMBER_KEY, '1')
  } else {
    sessionStorage.setItem(TOKEN_KEY, token)
  }
}

/** Remove the token (and remember flag) from both stores. */
export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(REMEMBER_KEY)
  sessionStorage.removeItem(TOKEN_KEY)
}
