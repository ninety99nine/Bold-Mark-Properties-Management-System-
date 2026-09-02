import { describe, it, expect } from 'vitest'
import { ownerNames, ownerEmails, ownerPhones } from './units'

describe('units owner helpers', () => {
  it('returns empty arrays for a null owner', () => {
    expect(ownerNames(null)).toEqual([])
    expect(ownerEmails(undefined)).toEqual([])
    expect(ownerPhones(null)).toEqual([])
  })

  it('joins primary owner + Contact 2 name', () => {
    const owner = { full_name: 'A Tlowana', contact2_name: 'B Tlowana' }
    expect(ownerNames(owner)).toEqual(['A Tlowana', 'B Tlowana'])
  })

  it('joins email, secondary emails and Contact 2 email, de-duplicated', () => {
    const owner = {
      email: 'a@x.com',
      secondary_emails: ['b@x.com', 'a@x.com'],
      contact2_email: 'c@x.com',
    }
    expect(ownerEmails(owner)).toEqual(['a@x.com', 'b@x.com', 'c@x.com'])
  })

  it('joins primary + Contact 2 cellphone, dropping blanks', () => {
    const owner = { phone: '0721', contact2_phone: '', }
    expect(ownerPhones(owner)).toEqual(['0721'])
  })

  it('de-duplicates case-insensitively', () => {
    const owner = { full_name: 'Smith', contact2_name: 'smith' }
    expect(ownerNames(owner)).toEqual(['Smith'])
  })
})
