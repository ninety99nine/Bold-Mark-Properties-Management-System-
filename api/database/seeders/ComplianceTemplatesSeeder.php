<?php

namespace Database\Seeders;

use App\Models\ComplianceTemplate;
use App\Models\ComplianceTemplateItem;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds default compliance templates for body corporate / sectional title management.
 * Run for each tenant after tenant creation.
 */
class ComplianceTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $tenant) {
            $this->seedForTenant($tenant);
        }
    }

    protected function seedForTenant(Organization $tenant): void
    {
        // Skip if templates already exist for this tenant
        if (ComplianceTemplate::where('organization_id', $tenant->id)->exists()) {
            return;
        }

        // ── Botswana Sectional Title ────────────────────────────────
        $this->createTemplate($tenant, [
            'name'        => 'Sectional Title — Botswana',
            'description' => 'Standard annual compliance checklist for body corporate schemes in Botswana under the Sectional Titles Act.',
            'country'     => 'BW',
            'is_default'  => true,
            'is_system'   => true,
            'items'       => [
                // Governance
                ['name' => 'Annual General Meeting (AGM) held', 'category' => 'Governance', 'description' => 'AGM must be held within 4 months of financial year-end. All owners must be notified at least 14 days in advance.', 'priority' => 'critical', 'default_month_due' => 4, 'sort_order' => 1],
                ['name' => 'AGM minutes prepared and distributed', 'category' => 'Governance', 'description' => 'Minutes must be circulated to all owners within 30 days of the AGM.', 'priority' => 'high', 'default_month_due' => 5, 'sort_order' => 2],
                ['name' => 'Trustees elected / confirmed', 'category' => 'Governance', 'description' => 'Board of trustees must be elected or re-confirmed at the AGM. Minimum 2 trustees required.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 3],
                ['name' => 'Trustee meeting schedule set for year', 'category' => 'Governance', 'description' => 'Set regular meeting dates for the board of trustees (quarterly recommended).', 'priority' => 'medium', 'default_month_due' => 5, 'sort_order' => 4],
                ['name' => 'Management rules reviewed', 'category' => 'Governance', 'description' => 'Review and update management and conduct rules as needed. Any amendments require special resolution at AGM.', 'priority' => 'low', 'default_month_due' => 3, 'sort_order' => 5],

                // Financial
                ['name' => 'Annual budget prepared and approved', 'category' => 'Financial', 'description' => 'The annual budget must be prepared and presented for approval at the AGM. Budget should cover all estimated operational expenses.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 6],
                ['name' => 'Levy schedule issued to owners', 'category' => 'Financial', 'description' => 'Once budget is approved, issue updated levy schedule showing each unit\'s monthly contribution.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 7],
                ['name' => 'Audited financial statements prepared', 'category' => 'Financial', 'description' => 'Annual financial statements must be audited by a registered accountant and presented at the AGM.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 8],
                ['name' => 'Reserve fund / maintenance fund reviewed', 'category' => 'Financial', 'description' => 'Review the adequacy of the reserve fund for major maintenance and capital expenditure. 10-year maintenance plan recommended.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 9],
                ['name' => 'Arrears recovery plan in place', 'category' => 'Financial', 'description' => 'Review outstanding levies and ensure a formal arrears recovery process is being followed for delinquent owners.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 10],

                // Insurance
                ['name' => 'Building insurance renewed', 'category' => 'Insurance', 'description' => 'Building insurance policy must be renewed annually. Coverage must include the full replacement value of the common property.', 'priority' => 'critical', 'default_month_due' => 1, 'sort_order' => 11],
                ['name' => 'Insurance valuation updated', 'category' => 'Insurance', 'description' => 'Obtain a professional replacement cost valuation at least every 3 years. Review annually for adequacy.', 'priority' => 'medium', 'default_month_due' => 1, 'sort_order' => 12],
                ['name' => 'Fidelity guarantee cover confirmed', 'category' => 'Insurance', 'description' => 'Confirm that fidelity guarantee insurance is in place to protect the body corporate against fraud or misappropriation.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 13],

                // Legal & Administrative
                ['name' => 'Managing agent agreement reviewed', 'category' => 'Legal', 'description' => 'Review the managing agent service agreement. Confirm scope of services, fees, and performance KPIs.', 'priority' => 'medium', 'default_month_due' => 11, 'sort_order' => 14],
                ['name' => 'Registered owner records updated', 'category' => 'Legal', 'description' => 'Verify that the body corporate\'s register of owners is current and matches Deeds Office records.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 15],
                ['name' => 'Tax compliance confirmed (BURS)', 'category' => 'Legal', 'description' => 'Ensure the body corporate is compliant with BURS (Botswana Unified Revenue Service) requirements for tax filing.', 'priority' => 'high', 'default_month_due' => 9, 'sort_order' => 16],

                // Maintenance
                ['name' => 'Building condition inspection completed', 'category' => 'Maintenance', 'description' => 'Conduct an annual walk-through inspection of common property areas, noting maintenance issues and safety hazards.', 'priority' => 'medium', 'default_month_due' => 2, 'sort_order' => 17],
                ['name' => 'Fire safety equipment serviced', 'category' => 'Maintenance', 'description' => 'Service all fire extinguishers, hose reels, and fire alarm systems. Ensure compliance with fire safety regulations.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 18],
                ['name' => '10-year maintenance plan reviewed', 'category' => 'Maintenance', 'description' => 'Review and update the long-term maintenance plan covering roof, plumbing, electrical, elevators, and structural elements.', 'priority' => 'medium', 'default_month_due' => 3, 'sort_order' => 19],
            ],
        ]);

        // ── South Africa Sectional Title ────────────────────────────
        $this->createTemplate($tenant, [
            'name'        => 'Sectional Title — South Africa',
            'description' => 'Standard annual compliance checklist for body corporate schemes in South Africa under the Sectional Titles Schemes Management Act (STSMA) and Community Schemes Ombud Service Act.',
            'country'     => 'ZA',
            'is_default'  => true,
            'is_system'   => true,
            'items'       => [
                // Governance
                ['name' => 'Annual General Meeting (AGM) held', 'category' => 'Governance', 'description' => 'AGM must be held within 4 months of financial year-end per STSMA. All owners must receive 14 days written notice.', 'priority' => 'critical', 'default_month_due' => 4, 'sort_order' => 1],
                ['name' => 'AGM minutes prepared and distributed', 'category' => 'Governance', 'description' => 'Minutes must be circulated to all owners. Keep on record for inspection per STSMA requirements.', 'priority' => 'high', 'default_month_due' => 5, 'sort_order' => 2],
                ['name' => 'Trustees elected / confirmed', 'category' => 'Governance', 'description' => 'Trustees must be elected at AGM. Minimum of 2 trustees. Trustees serve until next AGM unless removed by special resolution.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 3],
                ['name' => 'Trustee meeting schedule set for year', 'category' => 'Governance', 'description' => 'Trustees must meet at least once per quarter. Set the annual calendar after AGM.', 'priority' => 'medium', 'default_month_due' => 5, 'sort_order' => 4],
                ['name' => 'Conduct rules reviewed and updated', 'category' => 'Governance', 'description' => 'Review prescribed conduct rules (PMR 1 Annexure 2) and any body corporate specific rules. Updates require trustee resolution.', 'priority' => 'low', 'default_month_due' => 6, 'sort_order' => 5],

                // Financial
                ['name' => 'Annual budget prepared and approved', 'category' => 'Financial', 'description' => 'Budget must be approved at AGM per STSMA s3(1)(b). Must include administrative and reserve fund contributions.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 6],
                ['name' => 'Levy schedule issued to owners', 'category' => 'Financial', 'description' => 'Issue levy invoices per approved budget. Levies are proportional to participation quota per STSMA.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 7],
                ['name' => 'Audited financial statements prepared', 'category' => 'Financial', 'description' => 'Annual financial statements must be independently audited and presented at AGM as required by STSMA.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 8],
                ['name' => 'Reserve fund adequacy reviewed', 'category' => 'Financial', 'description' => 'STSMA requires a reserve fund. Review the 10-year maintenance plan to ensure contributions are adequate. PMR 24(4) requires minimum balance.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 9],
                ['name' => 'Arrears recovery plan in place', 'category' => 'Financial', 'description' => 'Follow the body corporate arrears collection policy. Issue letters of demand, then hand over to attorneys if unresolved per arrears protocol.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 10],

                // Insurance
                ['name' => 'Building insurance renewed', 'category' => 'Insurance', 'description' => 'STSMA s3(1)(h) mandates insurance for replacement value of buildings and common property against fire, storm, earthquake, etc.', 'priority' => 'critical', 'default_month_due' => 1, 'sort_order' => 11],
                ['name' => 'Insurance valuation updated', 'category' => 'Insurance', 'description' => 'Professional replacement cost valuation required every 3 years. Must reflect current building costs.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 12],
                ['name' => 'Fidelity guarantee cover confirmed', 'category' => 'Insurance', 'description' => 'PMR 2(1)(a) requires fidelity guarantee insurance covering persons who handle body corporate funds.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 13],
                ['name' => 'Public liability insurance confirmed', 'category' => 'Insurance', 'description' => 'Ensure adequate public liability cover is in place for common property areas.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 14],

                // Legal & CSOS
                ['name' => 'CSOS levy return filed', 'category' => 'Legal', 'description' => 'File the annual Community Schemes Ombud Service (CSOS) levy return. The body corporate must be registered with CSOS.', 'priority' => 'critical', 'default_month_due' => 5, 'sort_order' => 15],
                ['name' => 'CSOS levy paid', 'category' => 'Legal', 'description' => 'Pay the CSOS levy as prescribed. Non-payment can result in penalties and sanctions.', 'priority' => 'critical', 'default_month_due' => 6, 'sort_order' => 16],
                ['name' => 'Managing agent agreement reviewed', 'category' => 'Legal', 'description' => 'Review the managing agent agreement. STSMA s7 requires trustees to oversee the managing agent.', 'priority' => 'medium', 'default_month_due' => 11, 'sort_order' => 17],
                ['name' => 'Registered owner records updated', 'category' => 'Legal', 'description' => 'Verify owner register against the Deeds Office. PMR 3(1)(d) requires the body corporate to keep an up-to-date register.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 18],
                ['name' => 'Tax compliance confirmed (SARS)', 'category' => 'Legal', 'description' => 'Ensure the body corporate is registered with SARS and compliant with income tax obligations (if applicable) and VAT if turnover exceeds thresholds.', 'priority' => 'high', 'default_month_due' => 9, 'sort_order' => 19],

                // Maintenance
                ['name' => 'Building condition inspection completed', 'category' => 'Maintenance', 'description' => 'Conduct annual inspection of all common property areas. Document findings and prioritise repairs.', 'priority' => 'medium', 'default_month_due' => 2, 'sort_order' => 20],
                ['name' => 'Fire safety equipment serviced', 'category' => 'Maintenance', 'description' => 'Service fire extinguishers, hose reels, alarms per SANS 10400-T. Keep certificates on file.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 21],
                ['name' => '10-year maintenance, repair and replacement plan reviewed', 'category' => 'Maintenance', 'description' => 'PMR 22(3) requires a 10-year plan. Review and update annually, adjusting reserve fund contributions accordingly.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 22],
                ['name' => 'Electrical compliance certificate (COC) valid', 'category' => 'Maintenance', 'description' => 'Ensure electrical COC for common property is valid. Required for insurance claims and sale of units.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 23],
            ],
        ]);

        // ── Generic / Custom Template ───────────────────────────────
        $this->createTemplate($tenant, [
            'name'        => 'Generic Property Compliance',
            'description' => 'A general compliance checklist suitable for residential rental and commercial properties managed under a managing agent agreement.',
            'country'     => null,
            'is_default'  => false,
            'is_system'   => true,
            'items'       => [
                ['name' => 'Annual budget prepared', 'category' => 'Financial', 'description' => 'Prepare and approve the annual operating budget for the property.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 1],
                ['name' => 'Insurance renewed', 'category' => 'Insurance', 'description' => 'Ensure building and liability insurance policies are renewed and adequate.', 'priority' => 'critical', 'default_month_due' => 1, 'sort_order' => 2],
                ['name' => 'Building inspection completed', 'category' => 'Maintenance', 'description' => 'Conduct annual inspection of the property and document maintenance requirements.', 'priority' => 'medium', 'default_month_due' => 2, 'sort_order' => 3],
                ['name' => 'Fire safety compliance', 'category' => 'Maintenance', 'description' => 'Service fire safety equipment and verify compliance with local fire regulations.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 4],
                ['name' => 'Lease agreements reviewed', 'category' => 'Legal', 'description' => 'Review all active lease agreements. Identify renewals due and terms requiring attention.', 'priority' => 'medium', 'default_month_due' => 10, 'sort_order' => 5],
                ['name' => 'Tax compliance confirmed', 'category' => 'Legal', 'description' => 'Ensure all tax filings and payments are up to date for the property entity.', 'priority' => 'high', 'default_month_due' => 9, 'sort_order' => 6],
                ['name' => 'Maintenance plan reviewed', 'category' => 'Maintenance', 'description' => 'Review and update the long-term maintenance plan and reserve fund adequacy.', 'priority' => 'medium', 'default_month_due' => 3, 'sort_order' => 7],
                ['name' => 'Financial statements prepared', 'category' => 'Financial', 'description' => 'Prepare annual financial statements for the property.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 8],
            ],
        ]);
    }

    protected function createTemplate(Organization $tenant, array $data): void
    {
        $template = ComplianceTemplate::create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'country'     => $data['country'],
            'is_default'  => $data['is_default'],
            'is_system'   => $data['is_system'],
            'organization_id'   => $tenant->id,
        ]);

        foreach ($data['items'] as $itemData) {
            ComplianceTemplateItem::create([
                'name'                   => $itemData['name'],
                'category'               => $itemData['category'],
                'description'            => $itemData['description'] ?? null,
                'priority'               => $itemData['priority'],
                'default_month_due'      => $itemData['default_month_due'] ?? null,
                'sort_order'             => $itemData['sort_order'] ?? 0,
                'is_recurring'           => $itemData['is_recurring'] ?? true,
                'compliance_template_id' => $template->id,
            ]);
        }
    }
}
