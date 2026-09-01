// Community Entity Type — the single classification of a community (merged from
// the former "Community Type" billing axis + "Entity Type" legal axis). Mirrors
// the backend App\Enums\CommunityEntityType. The billing behaviour (levy vs rent
// vs mixed) is DERIVED from the entity type via billingBasis().

// Selectable entity types — matches WeConnectU exactly (the five levy-billed
// ownership schemes). The rental / mixed values are a legacy Bold Mark
// extension: no longer offered in the dropdown, but still label-resolvable via
// ENTITY_TYPE_LABELS so any pre-existing rental community renders correctly.
export const ENTITY_TYPE_OPTIONS = [
  { value: 'body_corporate',              label: 'Body Corporate'             },
  { value: 'home_owners_association',     label: 'Home Owners Association'    },
  { value: 'property_owners_association', label: 'Property Owners Association' },
  { value: 'full_title',                  label: 'Full Title'                 },
  { value: 'company',                     label: 'Company'                    },
]

const ENTITY_TYPE_LABELS = {
  body_corporate:              'Body Corporate',
  home_owners_association:     'Home Owners Association',
  property_owners_association: 'Property Owners Association',
  full_title:                  'Full Title',
  company:                     'Company',
  residential_rental:          'Residential Rental',
  commercial_rental:           'Commercial Rental',
  mixed:                       'Mixed',
}

const RENT_TYPES = ['residential_rental', 'commercial_rental']

/**
 * Derive the billing basis from an entity type: 'levy' | 'rent' | 'mixed'.
 * The five ownership-scheme types are all levy-billed.
 */
export function billingBasis(entityType) {
  if (RENT_TYPES.includes(entityType)) return 'rent'
  if (entityType === 'mixed') return 'mixed'
  return 'levy'
}

/** Raises levies (admin / reserve / CSOS) — levy or mixed. */
export function isLevyBilled(entityType) {
  return ['levy', 'mixed'].includes(billingBasis(entityType))
}

/** Raises rent — rent or mixed. */
export function isRentBilled(entityType) {
  return ['rent', 'mixed'].includes(billingBasis(entityType))
}

/** Pure levy/owner-billed scheme (no occupant billing) — the old "sectional_title" case. */
export function isLevyOnly(entityType) {
  return billingBasis(entityType) === 'levy'
}

/** Human-readable label for an entity type value. */
export function entityTypeLabel(entityType) {
  return ENTITY_TYPE_OPTIONS.find(o => o.value === entityType)?.label || entityType || '—'
}

// Tailwind badge classes per entity type (levy schemes share the brand-navy chip;
// rentals are green/amber; mixed is muted).
const ENTITY_TYPE_BADGE = {
  body_corporate:              'bg-primary/10 text-primary border-primary/20',
  home_owners_association:     'bg-primary/10 text-primary border-primary/20',
  property_owners_association: 'bg-primary/10 text-primary border-primary/20',
  full_title:                  'bg-primary/10 text-primary border-primary/20',
  company:                     'bg-primary/10 text-primary border-primary/20',
  residential_rental:          'bg-success/10 text-success border-success/20',
  commercial_rental:           'bg-warning/10 text-amber-dark border-warning/20',
  mixed:                       'bg-muted text-muted-foreground border-border',
}

export function entityTypeBadgeClass(entityType) {
  return ENTITY_TYPE_BADGE[entityType] || 'bg-muted text-muted-foreground border-border'
}

// Financial year-end month options (shared by the Add Community modal and the
// community General settings tab). Values are 1–12; the day is implied as the
// month's last day.
export const YEAR_END_MONTH_OPTIONS = [
  { value: '1', label: 'January' },   { value: '2', label: 'February' },
  { value: '3', label: 'March' },     { value: '4', label: 'April' },
  { value: '5', label: 'May' },       { value: '6', label: 'June' },
  { value: '7', label: 'July' },      { value: '8', label: 'August' },
  { value: '9', label: 'September' }, { value: '10', label: 'October' },
  { value: '11', label: 'November' }, { value: '12', label: 'December' },
]

// Hex colours for analytics charts (donut "by entity type").
export const ENTITY_TYPE_COLOR = {
  body_corporate:              '#1F3A5C',
  home_owners_association:     '#2E5074',
  property_owners_association: '#3E668C',
  full_title:                  '#5A7CA0',
  company:                     '#7C97B4',
  residential_rental:          '#16A34A',
  commercial_rental:           '#D89B4B',
  mixed:                       '#9AA3B2',
}
