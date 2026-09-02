<?php

namespace Database\Seeders;

use App\Enums\FinancialCategory;
use App\Models\Ledger;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds the WeConnectU-style chart of accounts for every organization.
 *
 * Each account becomes a ledger with a `code` (e.g. "1000/006"), a `category`
 * group header (e.g. "1000/000 - INCOME"), and a `name`, plus WeConnectU GL
 * classification: `account_type` (income_statement | balance_sheet),
 * `financial_category` (used by the GL posting engine to resolve control
 * accounts) and `fund` (main | reserve). Every group also gets a real MAIN
 * account row (e.g. "1000/000 - INCOME") and each leaf is linked to it via
 * `parent_id`, mirroring WeConnectU's main/sub-account hierarchy.
 *
 * Idempotent: keyed on (organization_id, code) via updateOrCreate.
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

        foreach ($this->accounts() as $category => $group) {
            $mainCode = $group['main'];
            $mainName = $group['name'];

            $main = Ledger::updateOrCreate(
                ['organization_id' => $organizationId, 'code' => $mainCode],
                [
                    'category'           => $category,
                    'name'               => $mainName,
                    'account_type'       => $group['account_type'],
                    'financial_category' => $group['financial_category'],
                    'fund'               => $group['fund'],
                    'allow_sub_accounts' => true,
                    'parent_id'          => null,
                    'is_system'          => false,
                    'is_active'          => true,
                    'is_recurring'       => false,
                    'applies_to'         => 'owner',
                    'sort_order'         => $sort += 10,
                ]
            );

            foreach ($group['accounts'] as $code => $account) {
                Ledger::updateOrCreate(
                    ['organization_id' => $organizationId, 'code' => $code],
                    [
                        'category'           => $category,
                        'name'               => $account['name'],
                        'account_type'       => $group['account_type'],
                        'financial_category' => $account['financial_category'] ?? $group['financial_category'],
                        'fund'               => $group['fund'],
                        'allow_sub_accounts' => false,
                        'parent_id'          => $main->id,
                        'is_system'          => false,
                        'is_active'          => true,
                        'is_recurring'       => false,
                        'applies_to'         => 'owner',
                        'sort_order'         => $sort += 10,
                    ]
                );
            }
        }
    }

    /**
     * The full chart of accounts, grouped by category header. Each group carries
     * its MAIN account code/name and default GL classification; leaf accounts may
     * override `financial_category` (used for the control-account singletons).
     *
     * @return array<string, array{main:string, name:string, account_type:string, financial_category:FinancialCategory, fund:string, accounts:array<string, array{name:string, financial_category?:FinancialCategory}>}>
     */
    private function accounts(): array
    {
        return [
            '1000/000 - INCOME' => [
                'main'               => '1000/000',
                'name'               => 'INCOME',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::SALES,
                'fund'               => 'main',
                'accounts'           => [
                    '1000/001' => ['name' => 'Levies'],
                    '1000/002' => ['name' => 'Special Levy'],
                    '1000/003' => ['name' => 'Interest Received Arrears'],
                    '1000/004' => ['name' => 'Interest Received Bank'],
                    '1000/005' => ['name' => 'Rental Income'],
                    '1000/006' => ['name' => 'Laundry Room Water'],
                    '1000/007' => ['name' => 'Additional Levy'],
                    '1000/008' => ['name' => 'Additional insurance'],
                    '1000/009' => ['name' => 'Remotes Income'],
                    '1000/010' => ['name' => 'Penalty Income'],
                    '1000/011' => ['name' => 'Water Recovered'],
                    '1000/012' => ['name' => 'Sewerage Recovered'],
                    '1000/013' => ['name' => 'Electricity Recovered'],
                    '1000/014' => ['name' => 'Other Income'],
                    '1000/015' => ['name' => 'Costs Recovered'],
                    '1000/016' => ['name' => 'Admin Fee'],
                ],
            ],
            '2000/000 - ADMINISTRATIVE EXPENSES' => [
                'main'               => '2000/000',
                'name'               => 'ADMINISTRATIVE EXPENSES',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'main',
                'accounts'           => [
                    '2000/001' => ['name' => 'Bank Charges'],
                    '2000/002' => ['name' => 'Management Fee'],
                    '2000/003' => ['name' => 'Cleaning & Materials'],
                    '2000/004' => ['name' => 'Computer Expenses'],
                    '2000/005' => ['name' => 'General Office Expenses'],
                    '2000/006' => ['name' => 'Keys & Remotes'],
                    '2000/007' => ['name' => 'Legal & Professional Fees'],
                    '2000/008' => ['name' => 'Telephone: Mobile(s)'],
                    '2000/009' => ['name' => 'Telephone: Landline(s) & Fax'],
                    '2000/010' => ['name' => 'Telephone: Gate access'],
                    '2000/011' => ['name' => 'Audit & Tax Fees'],
                    '2000/012' => ['name' => 'Trustee Expense'],
                    '2000/013' => ['name' => 'Rent Paid: Garage'],
                    '2000/014' => ['name' => 'Rent Paid: Office Space'],
                    '2000/015' => ['name' => 'Security'],
                    '2000/016' => ['name' => 'Insurance'],
                    '2000/017' => ['name' => 'Interest Paid'],
                    '2000/018' => ['name' => 'Health & Safety'],
                    '2000/019' => ['name' => 'Income Tax Expense'],
                    '2000/020' => ['name' => 'Depreciation'],
                    '2000/021' => ['name' => 'Diverse/Sundry Expenses'],
                    '2000/022' => ['name' => 'CSOS Admin Fees'],
                    '2000/023' => ['name' => 'Meter reading fee'],
                    '2000/024' => ['name' => 'Building Management Fee'],
                    '2000/025' => ['name' => 'Cleaning material'],
                    '2000/026' => ['name' => 'Admin Fee'],
                ],
            ],
            '2100/000 - MUNICIPAL EXPENSES' => [
                'main'               => '2100/000',
                'name'               => 'MUNICIPAL EXPENSES',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'main',
                'accounts'           => [
                    '2100/001' => ['name' => 'Water'],
                    '2100/002' => ['name' => 'Refuse'],
                    '2100/003' => ['name' => 'Sewerage'],
                    '2100/004' => ['name' => 'Electricity'],
                    '2100/005' => ['name' => 'Rates'],
                    '2100/006' => ['name' => 'Sundry Municipal Expenses'],
                    '2100/007' => ['name' => 'Fixed Basic Charge - Water'],
                    '2100/008' => ['name' => 'Electricity - Home User Charge'],
                ],
            ],
            '2200/000 - MAINTENANCE' => [
                'main'               => '2200/000',
                'name'               => 'MAINTENANCE',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'main',
                'accounts'           => [
                    '2200/001' => ['name' => 'Fire Equipment & Services'],
                    '2200/002' => ['name' => 'General Building'],
                    '2200/003' => ['name' => 'Sewerage & Plumbing'],
                    '2200/004' => ['name' => 'Gate & Intercom'],
                    '2200/005' => ['name' => 'Electrical'],
                    '2200/006' => ['name' => 'Electric Fence & Monitoring'],
                    '2200/007' => ['name' => 'DSTV / TV'],
                    '2200/008' => ['name' => 'Turnstile & Access Control'],
                    '2200/009' => ['name' => 'Gardening Expense General'],
                    '2200/010' => ['name' => 'Gardening Equipment'],
                    '2200/011' => ['name' => 'Cameras'],
                    '2200/012' => ['name' => 'Swimming Pool'],
                    '2200/013' => ['name' => 'Lifts'],
                    '2200/014' => ['name' => 'Cleaning and Maintenance Contracts'],
                    '2200/015' => ['name' => 'Other Maintenance'],
                    '2200/016' => ['name' => 'Pest Control'],
                ],
            ],
            '2300/000 - SPECIAL PROJECTS' => [
                'main'               => '2300/000',
                'name'               => 'SPECIAL PROJECTS',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'main',
                'accounts'           => [
                    '2300/001' => ['name' => 'Buildings'],
                    '2300/002' => ['name' => 'Gardens'],
                    '2300/003' => ['name' => 'Improvements'],
                    '2300/004' => ['name' => 'Valuations - 3 Year Cycle'],
                ],
            ],
            '4000/000 - PERSONNEL' => [
                'main'               => '4000/000',
                'name'               => 'PERSONNEL',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'main',
                'accounts'           => [
                    '4000/001' => ['name' => 'Complex Manager Salary'],
                    '4000/002' => ['name' => 'Operational Admin'],
                    '4000/003' => ['name' => 'Casual / Relief Wages'],
                    '4000/004' => ['name' => 'PAYE'],
                    '4000/005' => ['name' => 'UIF'],
                    '4000/006' => ['name' => 'Travel'],
                    '4000/007' => ['name' => 'Bonusses & Overtime'],
                    '4000/008' => ['name' => 'Security'],
                    '4000/009' => ['name' => 'WCA'],
                ],
            ],
            '4100/000 - LONG TERM LIABILITIES' => [
                'main'               => '4100/000',
                'name'               => 'LONG TERM LIABILITIES',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::LONG_TERM_LIABILITIES,
                'fund'               => 'main',
                'accounts'           => [],
            ],
            '4500/000 - FIXED ASSETS' => [
                'main'               => '4500/000',
                'name'               => 'FIXED ASSETS',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::FIXED_ASSETS,
                'fund'               => 'main',
                'accounts'           => [],
            ],
            '5000/000 - EQUITY & RESERVES' => [
                'main'               => '5000/000',
                'name'               => 'EQUITY & RESERVES',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::EQUITY_RESERVES,
                'fund'               => 'main',
                'accounts'           => [
                    '5000/001' => ['name' => 'Retained Income', 'financial_category' => FinancialCategory::RETAINED_INCOME],
                    '5000/002' => ['name' => 'Reserve Fund'],
                    '5000/003' => ['name' => 'Prior Year Adjustments'],
                ],
            ],
            '6000/000 - CURRENT LIABILITIES' => [
                'main'               => '6000/000',
                'name'               => 'CURRENT LIABILITIES',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::CURRENT_LIABILITIES,
                'fund'               => 'main',
                'accounts'           => [
                    '6000/001' => ['name' => 'VAT Control Account', 'financial_category' => FinancialCategory::VAT_CONTROL],
                    '6000/002' => ['name' => 'Creditor Accruals'],
                    '6000/003' => ['name' => 'Supplier Control Account', 'financial_category' => FinancialCategory::ACCOUNTS_PAYABLE],
                    '6000/004' => ['name' => 'Sundry Creditors'],
                    '6000/005' => ['name' => 'Supplier Deposits'],
                    '6000/006' => ['name' => 'Owner Deposits'],
                    '6000/007' => ['name' => 'SARS: Income Tax Payable'],
                    '6000/008' => ['name' => 'Loan Repayment'],
                ],
            ],
            '6050/000 - CONTROL ACCOUNTS' => [
                'main'               => '6050/000',
                'name'               => 'CONTROL ACCOUNTS',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::CURRENT_LIABILITIES,
                'fund'               => 'main',
                'accounts'           => [
                    '6050/001' => ['name' => 'CSOS Control Account'],
                    '6050/002' => ['name' => 'Insurance Claims Control Account'],
                    '6050/003' => ['name' => 'Debt Collection Control Account'],
                    '6050/004' => ['name' => 'Legal Fees Control Account'],
                    '6050/005' => ['name' => 'Admin Fees Control Account'],
                    '6050/006' => ['name' => 'Debit Order Control'],
                ],
            ],
            '6500/000 - FIXED ASSETS' => [
                'main'               => '6500/000',
                'name'               => 'FIXED ASSETS',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::FIXED_ASSETS,
                'fund'               => 'main',
                'accounts'           => [
                    '6500/001' => ['name' => 'Fixed Assets: Cost'],
                    '6500/002' => ['name' => 'Fixed Assets: Depreciation'],
                    '6500/003' => ['name' => 'Accumulated Depreciation'],
                ],
            ],
            '7000/000 - CURRENT ASSETS' => [
                'main'               => '7000/000',
                'name'               => 'CURRENT ASSETS',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::CURRENT_ASSETS,
                'fund'               => 'main',
                'accounts'           => [
                    '7000/001' => ['name' => 'Customer Control Account', 'financial_category' => FinancialCategory::ACCOUNTS_RECEIVABLE],
                    '7000/002' => ['name' => 'Debtor Accruals'],
                    '7000/003' => ['name' => 'Sundry Debtors'],
                    '7000/004' => ['name' => 'Inventory'],
                ],
            ],
            '8000/000 - BANK' => [
                'main'               => '8000/000',
                'name'               => 'BANK',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::BANK,
                'fund'               => 'main',
                'accounts'           => [],
            ],
            '8100/000 - INVESTMENTS' => [
                'main'               => '8100/000',
                'name'               => 'INVESTMENTS',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::INVESTMENTS,
                'fund'               => 'main',
                'accounts'           => [],
            ],
            '9900/000 - SUSPENSE ACCOUNT' => [
                'main'               => '9900/000',
                'name'               => 'SUSPENSE ACCOUNT',
                'account_type'       => 'balance_sheet',
                'financial_category' => FinancialCategory::UNDEFINED,
                'fund'               => 'main',
                'accounts'           => [
                    '9900/001' => ['name' => 'Suspense Account - General'],
                ],
            ],
            'RFI/000 - INCOME' => [
                'main'               => 'RFI/000',
                'name'               => 'INCOME',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::SALES,
                'fund'               => 'reserve',
                'accounts'           => [
                    'RFI/001' => ['name' => 'Reserve Fund Levy'],
                ],
            ],
            'RFE/000 - EXPENSES' => [
                'main'               => 'RFE/000',
                'name'               => 'EXPENSES',
                'account_type'       => 'income_statement',
                'financial_category' => FinancialCategory::EXPENSES,
                'fund'               => 'reserve',
                'accounts'           => [],
            ],
        ];
    }
}
