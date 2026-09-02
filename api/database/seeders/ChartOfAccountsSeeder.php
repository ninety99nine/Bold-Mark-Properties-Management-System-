<?php

namespace Database\Seeders;

use App\Models\Ledger;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds the WeConnectU-style chart of accounts for every organization.
 *
 * Each account becomes a ledger with a `code` (e.g. "1000/006"), a `category`
 * group header (e.g. "1000/000 - INCOME"), and a `name`. The customer-invoice
 * Account picker and the Default Billing Setup page read these, grouped by
 * category. Idempotent: keyed on (organization_id, code) via updateOrCreate.
 *
 * The 8000 BANK group is intentionally excluded — bank accounts live in the
 * dedicated bank_accounts table.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::query()->pluck('id');

        foreach ($organizations as $organizationId) {
            $this->seedForOrganization($organizationId);
        }
    }

    public function seedForOrganization(string $organizationId): void
    {
        $sort = 0;

        foreach ($this->accounts() as $category => $accounts) {
            foreach ($accounts as $code => $name) {
                Ledger::updateOrCreate(
                    ['organization_id' => $organizationId, 'code' => $code],
                    [
                        'category'     => $category,
                        'name'         => $name,
                        'is_system'    => false,
                        'is_active'    => true,
                        'is_recurring' => false,
                        'applies_to'   => 'owner',
                        'sort_order'   => $sort += 10,
                    ]
                );
            }
        }
    }

    /**
     * The full chart of accounts, grouped by category header.
     *
     * @return array<string, array<string, string>>
     */
    private function accounts(): array
    {
        return [
            '1000/000 - INCOME' => [
                '1000/001' => 'Levies',
                '1000/002' => 'Special Levy',
                '1000/003' => 'Interest Received Arrears',
                '1000/004' => 'Interest Received Bank',
                '1000/005' => 'Rental Income',
                '1000/006' => 'Laundry Room Water',
                '1000/007' => 'Additional Levy',
                '1000/008' => 'Additional insurance',
                '1000/009' => 'Remotes Income',
                '1000/010' => 'Penalty Income',
                '1000/011' => 'Water Recovered',
                '1000/012' => 'Sewerage Recovered',
                '1000/013' => 'Electricity Recovered',
                '1000/014' => 'Other Income',
                '1000/015' => 'Costs Recovered',
            ],
            '2000/000 - ADMINISTRATIVE EXPENSES' => [
                '2000/001' => 'Bank Charges',
                '2000/002' => 'Management Fee',
                '2000/003' => 'Cleaning & Materials',
                '2000/004' => 'Computer Expenses',
                '2000/005' => 'General Office Expenses',
                '2000/006' => 'Keys & Remotes',
                '2000/007' => 'Legal & Professional Fees',
                '2000/008' => 'Telephone: Mobile(s)',
                '2000/009' => 'Telephone: Landline(s)',
                '2000/010' => 'Telephone: Gate access',
                '2000/011' => 'Audit & Tax Fees',
                '2000/012' => 'Trustee Expense',
                '2000/013' => 'Rent Paid: Garage',
                '2000/014' => 'Rent Paid: Office Space',
                '2000/015' => 'Security',
                '2000/016' => 'Insurance',
                '2000/017' => 'Interest Paid',
                '2000/018' => 'Health & Safety',
                '2000/019' => 'Income Tax Expense',
                '2000/020' => 'Depreciation',
                '2000/021' => 'Diverse/Sundry Expenses',
                '2000/022' => 'CSOS Admin Fees',
                '2000/023' => 'Meter reading fee',
                '2000/024' => 'Building Management Fee',
            ],
            '2100/000 - MUNICIPAL EXPENSES' => [
                '2100/001' => 'Water',
                '2100/002' => 'Refuse',
                '2100/003' => 'Sewerage',
                '2100/004' => 'Electricity',
                '2100/005' => 'Rates',
                '2100/006' => 'Sundry Municipal Expenses',
                '2100/007' => 'Fixed Basic Charge - Water',
                '2100/008' => 'Electricity - Home User Charge',
            ],
            '2200/000 - MAINTENANCE' => [
                '2200/001' => 'Fire Equipment & Services',
                '2200/002' => 'General Building',
                '2200/003' => 'Sewerage & Plumbing',
                '2200/004' => 'Gate & Intercom',
                '2200/005' => 'Electrical',
                '2200/006' => 'Electric Fence & Monitoring',
                '2200/007' => 'DSTV / TV',
                '2200/008' => 'Turnstile & Access Control',
                '2200/009' => 'Gardening Expense General',
                '2200/010' => 'Gardening Equipment',
                '2200/011' => 'Cameras',
                '2200/012' => 'Swimming Pool',
                '2200/013' => 'Lifts',
                '2200/014' => 'Cleaning and Maintenance Contracts',
                '2200/015' => 'Other Maintenance',
                '2200/016' => 'Pest Control',
            ],
            '2300/000 - SPECIAL PROJECTS' => [
                '2300/001' => 'Buildings',
                '2300/002' => 'Gardens',
                '2300/003' => 'Improvements',
                '2300/004' => 'Valuations - 3 Year Cycle',
            ],
            '4000/000 - PERSONNEL' => [
                '4000/001' => 'Complex Manager Salary',
                '4000/002' => 'Operational Admin',
                '4000/003' => 'Casual / Relief Wages',
                '4000/004' => 'PAYE',
                '4000/005' => 'UIF',
                '4000/006' => 'Travel',
                '4000/007' => 'Bonusses & Overtime',
                '4000/008' => 'Security',
                '4000/009' => 'WCA',
            ],
            '5000/000 - EQUITY & RESERVES' => [
                '5000/001' => 'Retained Income',
                '5000/002' => 'Reserve Fund',
                '5000/003' => 'Prior Year Adjustments',
            ],
            '6000/000 - CURRENT LIABILITIES' => [
                '6000/001' => 'VAT Control Account',
                '6000/002' => 'Creditor Accruals',
                '6000/003' => 'Supplier Control Account',
                '6000/004' => 'Sundry Creditors',
                '6000/005' => 'Supplier Deposits',
                '6000/006' => 'Owner Deposits',
                '6000/007' => 'SARS: Income Tax Payable',
                '6000/008' => 'Loan Repayment',
            ],
            '6050/000 - CONTROL ACCOUNTS' => [
                '6050/001' => 'CSOS Control Account',
                '6050/002' => 'Insurance Claims Control Account',
                '6050/003' => 'Debt Collection Control Account',
                '6050/004' => 'Legal Fees Control Account',
                '6050/005' => 'Admin Fees Control Account',
                '6050/006' => 'Debit Order Control',
            ],
            '6500/000 - FIXED ASSETS' => [
                '6500/001' => 'Fixed Assets: Cost',
                '6500/002' => 'Fixed Assets: Depreciation',
                '6500/003' => 'Accumulated Depreciation',
            ],
            '7000/000 - CURRENT ASSETS' => [
                '7000/001' => 'Customer Control Account',
                '7000/002' => 'Debtor Accruals',
                '7000/003' => 'Sundry Debtors',
                '7000/004' => 'Inventory',
            ],
            '9900/000 - SUSPENSE ACCOUNT' => [
                '9900/001' => 'Suspense Account - General',
            ],
            'RFI/000 - INCOME' => [
                'RFI/001' => 'Reserve Fund Levy',
            ],
        ];
    }
}
