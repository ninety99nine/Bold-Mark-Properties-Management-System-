// Shared option lists for the Manage Customers page — mirror the backend enums
// (App\Enums\CustomerType, PaymentType) and App\Helpers\BankHelper exactly.

export const CUSTOMER_TYPES = [
  { value: 'body_corporate',          label: 'Body Corporate' },
  { value: 'cash_only',               label: 'Cash Only' },
  { value: 'closed_corporation',      label: 'Closed Corporation (CC)' },
  { value: 'incorporated',            label: 'Incorporated' },
  { value: 'individual',              label: 'Individual' },
  { value: 'non_profit_organization', label: 'Non Profit Organization' },
  { value: 'partnership',             label: 'Partnership' },
  { value: 'private_company',         label: 'Private Company (Pty) Ltd' },
  { value: 'public_company',          label: 'Public Company Ltd' },
  { value: 'trust',                   label: 'Trust' },
]

export const PAYMENT_TYPES = [
  { value: 'not_specified', label: 'Not Specified' },
  { value: 'eft',           label: 'EFT' },
  { value: 'debit_order',   label: 'Debit Order' },
  { value: 'cash',          label: 'Cash' },
]

export const BANKS = [
  'ABSA',
  'African Bank',
  'Bank Windhoek',
  'Bidvest Bank',
  'Capitec Bank',
  'Capitec Business',
  'Discovery Bank',
  'First National Bank',
  'Investec Private Bank',
  'Mercantile Bank',
  'Merchant Bank',
  'Nedbank',
  'Other',
  'PayFast',
  'Sasfin Bank Ltd',
  'Standard Bank',
  'Standard Chartered Bank',
  'Tyme Bank',
]

export const ACCOUNT_TYPES = ['Current', 'Savings', 'Investment']

export const COUNTRIES = ['South Africa', 'Botswana', 'Namibia']

export const PDF_PASSWORD_OPTIONS = ['Community Default', 'ID Number', 'No Password']

// Map a payment_type value to its label (for the customer list column).
export function paymentTypeLabel(value) {
  return PAYMENT_TYPES.find(p => p.value === value)?.label ?? 'Not Specified'
}
