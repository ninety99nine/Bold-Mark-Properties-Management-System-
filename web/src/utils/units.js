// Helpers for rendering the WeConnectU-style Units list.
//
// WeConnectU shows plural "Owner/s", "E-Mail/s" and "Cellphone/s" columns because a
// unit can carry a primary owner plus a secondary contact ("Contact 2"). These pure
// helpers collapse a raw owner object (as returned by OwnerResource) into de-duplicated,
// blank-free arrays so each value can be rendered on its own line.

/**
 * Trim, drop blanks, and de-duplicate a list of strings (case-insensitive).
 * @param {Array<unknown>} values
 * @returns {string[]}
 */
function cleanList(values) {
  const seen = new Set()
  const out = []
  for (const v of values) {
    const s = (v ?? '').toString().trim()
    if (!s) continue
    const key = s.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    out.push(s)
  }
  return out
}

/**
 * Owner name(s): primary owner + Contact 2 name.
 * @param {object|null|undefined} owner
 * @returns {string[]}
 */
export function ownerNames(owner) {
  if (!owner) return []
  return cleanList([owner.full_name, owner.contact2_name])
}

/**
 * Owner e-mail(s): primary + secondary emails + Contact 2 email.
 * @param {object|null|undefined} owner
 * @returns {string[]}
 */
export function ownerEmails(owner) {
  if (!owner) return []
  return cleanList([owner.email, ...(owner.secondary_emails ?? []), owner.contact2_email])
}

/**
 * Owner cellphone(s): primary phone + Contact 2 cellphone.
 * @param {object|null|undefined} owner
 * @returns {string[]}
 */
export function ownerPhones(owner) {
  if (!owner) return []
  return cleanList([owner.phone, owner.contact2_phone])
}
