<?php

namespace Database\Seeders;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\ComplianceTemplate;
use App\Models\ComplianceTemplateItem;
use App\Models\Community;
use App\Models\CommunityBillingSetup;
use App\Models\CommunityMember;
use App\Models\CustomerGroup;
use App\Models\Ledger;
use App\Models\CommunityLedger;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Occupant;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\User;
use App\Services\CommunityLedgerService;
use App\Services\UnitBalanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Full demo seed — org, BW + SA communities, all users, ledgers, compliance.
 * Fully self-contained: no $this->call() to other seeders.
 *
 * Usage: php artisan migrate:fresh --seed --class=DemoSeeder
 *        php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    protected string $organizationId;
    protected array  $ledgers  = [];
    protected int    $invoiceCounter = 0;
    protected int    $receiptIndex   = 0;
    protected ?int   $managerUserId  = null;
    protected int    $communitySeq   = 0;

    /** 9 billing periods: July 2025 → March 2026 (billing day 25) */
    protected const PERIODS = [
        ['period' => '2025-07-01', 'sent_at' => '2025-07-25 08:00:00', 'due_date' => '2025-08-01'],
        ['period' => '2025-08-01', 'sent_at' => '2025-08-25 08:00:00', 'due_date' => '2025-09-01'],
        ['period' => '2025-09-01', 'sent_at' => '2025-09-25 08:00:00', 'due_date' => '2025-10-02'],
        ['period' => '2025-10-01', 'sent_at' => '2025-10-25 08:00:00', 'due_date' => '2025-11-01'],
        ['period' => '2025-11-01', 'sent_at' => '2025-11-25 08:00:00', 'due_date' => '2025-12-02'],
        ['period' => '2025-12-01', 'sent_at' => '2025-12-25 08:00:00', 'due_date' => '2026-01-01'],
        ['period' => '2026-01-01', 'sent_at' => '2026-01-25 08:00:00', 'due_date' => '2026-02-01'],
        ['period' => '2026-02-01', 'sent_at' => '2026-02-25 08:00:00', 'due_date' => '2026-03-04'],
        ['period' => '2026-03-01', 'sent_at' => '2026-03-25 08:00:00', 'due_date' => '2026-04-01'],
    ];

    protected const RECEIPTS = [
        ['src' => 'samples/receipts/receipt-1.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-2.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-3.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-4.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-5.jpg', 'ext' => 'jpg'],
    ];

    /**
     * Maps shorthand codes used in community logic to ledger names in DB.
     * System types are matched by 'type' field; presets by 'name'.
     */
    protected const LEDGER_NAME_MAP = [
        'LEVY'                 => 'Admin Levy',
        'RESERVE_LEVY'         => 'Reserve Levy',
        'CSOS_LEVY'            => 'CSOS Levy',
        'RENT'                 => 'Rent',
        'SPECIAL_LEVY'         => 'Special Levy',
        'WATER_RECOVERY'       => 'Water Recovery',
        'ELECTRICITY_RECOVERY' => 'Electricity Recovery',
        'GAS_RECOVERY'         => 'Gas Recovery',
        'SEWERAGE_RECOVERY'    => 'Sewerage Recovery',
        'REFUSE_RECOVERY'      => 'Refuse Recovery',
        'LATE_INTEREST'        => 'Late Payment Interest',
        'LATE_PENALTY'         => 'Late Payment Penalty',
        'INSURANCE_EXCESS'     => 'Insurance Excess',
        'KEY_DEPOSIT'          => 'Key Deposit',
        'DAMAGE_DEPOSIT'       => 'Damage Deposit',
        'PARKING_RENTAL'       => 'Parking Rental',
        'STORAGE_RENTAL'       => 'Storage Rental',
        'MOVING_IN'            => 'Moving-In Fee',
        'MOVING_OUT'           => 'Moving-Out Fee',
        'ACCESS_CARD'          => 'Access Card Fee',
        'GYM_ACCESS'           => 'Gym Access',
        'POOL_ACCESS'          => 'Pool Access',
        'GARDEN_MAINT'         => 'Garden Maintenance',
        'PET_LEVY'             => 'Pet Levy',
        'SECURITY_CONTRIB'     => 'Security Contribution',
        'LEGAL_RECOVERY'       => 'Legal Recovery',
    ];

    /* ------------------------------------------------------------------ */
    /*  ENTRY POINT                                                         */
    /* ------------------------------------------------------------------ */

    public function run(): void
    {
        $this->command?->info('Creating Passport personal access client...');
        app(ClientRepository::class)->createPersonalAccessGrantClient(
            config('app.name') . ' Personal Access Client'
        );

        $this->seedRolesAndPermissions();
        $this->seedSuperAdmin();
        $this->seedOrganization();
        $this->seedDefaultLedgers();
        $this->seedAllUsers();
        $this->seedExternalUsers();
        $this->seedCommunities();
        $this->seedComplianceTemplates();
        $this->seedLedgerReports();

        $this->command?->info('Demo seed complete.');
    }

    /**
     * Seed a couple of "Recent Email Reports" per community so the Detailed
     * Customer Ledger panel is populated and its row-download can be tested.
     */
    private function seedLedgerReports(): void
    {
        $this->command?->info('Seeding recent ledger email reports...');

        foreach (\App\Models\Community::all() as $community) {
            $accounts = \App\Models\Unit::where('community_id', $community->id)->count();

            \App\Models\LedgerReportBatch::updateOrCreate(
                ['community_id' => $community->id, 'date_from' => '2026-01-01', 'date_to' => '2026-03-31'],
                [
                    'organization_id' => $community->organization_id,
                    'account_count'   => max(1, $accounts),
                    'status'          => 'completed',
                    'generated_at'    => '2026-03-12 12:22:00',
                    'filters'         => ['all_customers' => true, 'show_line_items' => true],
                ]
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  ROLES AND PERMISSIONS                                               */
    /* ------------------------------------------------------------------ */

    private function seedRolesAndPermissions(): void
    {
        $this->command?->info('Seeding roles and permissions...');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view-financials','manage-financials',
            'view-levies','manage-levies','approve-levies',
            'view-debt','manage-debt','approve-debt-actions',
            'view-compliance','manage-compliance',
            'view-maintenance','manage-maintenance','assign-contractors',
            'manage-users','manage-communities','manage-organizations',
            'view-reports','export-reports',
            'send-communications','manage-documents','approve-payments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->givePermissionTo(Permission::all());

        $companyAdmin = Role::firstOrCreate(['name' => 'company-admin', 'guard_name' => 'api']);
        $companyAdmin->givePermissionTo([
            'view-financials','manage-financials',
            'view-levies','manage-levies','approve-levies',
            'view-debt','manage-debt','approve-debt-actions',
            'view-compliance','manage-compliance',
            'view-maintenance','manage-maintenance','assign-contractors',
            'manage-users','manage-communities',
            'view-reports','export-reports',
            'send-communications','manage-documents',
        ]);

        $portfolioManager = Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'api']);
        $portfolioManager->givePermissionTo([
            'view-financials',
            'view-levies','manage-levies',
            'view-debt','manage-debt',
            'view-compliance','manage-compliance',
            'view-maintenance','manage-maintenance','assign-contractors',
            'view-reports','export-reports',
            'send-communications','manage-documents',
        ]);

        $financialController = Role::firstOrCreate(['name' => 'financial-controller', 'guard_name' => 'api']);
        $financialController->givePermissionTo([
            'view-financials','manage-financials',
            'view-levies','manage-levies',
            'view-debt','manage-debt',
            'view-reports','export-reports',
        ]);

        $portfolioAssistant = Role::firstOrCreate(['name' => 'portfolio-assistant', 'guard_name' => 'api']);
        $portfolioAssistant->givePermissionTo([
            'view-financials','view-levies','view-debt','view-compliance',
            'view-maintenance','manage-maintenance',
            'send-communications','manage-documents',
        ]);

        $trustee = Role::firstOrCreate(['name' => 'trustee', 'guard_name' => 'api']);
        $trustee->givePermissionTo(['view-financials','view-reports','approve-payments']);

        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        $owner->givePermissionTo(['view-financials']);

        Role::firstOrCreate(['name' => 'occupant',     'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'contractor', 'guard_name' => 'api']);
    }

    /* ------------------------------------------------------------------ */
    /*  SUPER ADMIN                                                         */
    /* ------------------------------------------------------------------ */

    private function seedSuperAdmin(): void
    {
        $this->command?->info('Seeding super admin...');
        $superAdmin = User::firstOrCreate(
            ['email' => 'super@optimumquality.co.za'],
            [
                'name'     => 'Optimum Quality Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
            ]
        );
        $superAdmin->assignRole(Role::findByName('super-admin', 'api'));
    }

    /* ------------------------------------------------------------------ */
    /*  ORGANIZATION                                                        */
    /* ------------------------------------------------------------------ */

    private function seedOrganization(): void
    {
        $this->command?->info('Seeding Bold Mark Properties organization...');
        Organization::updateOrCreate(
            ['slug' => 'boldmark'],
            [
                'name'            => 'Bold Mark Properties',
                'company_name'    => 'Bold Mark Properties',
                'company_slogan'  => 'Moving People Forward',
                'company_reg_no'  => '2021/147096/07',
                'transfer_clearance_fee' => 1100.00,
                'logo_url'        => '/assets/logo2-CB_yk5b_.png',
                'contact_email'   => 'info@boldmarkprop.co.za',
                'outgoing_email'  => 'noreply@boldmarkprop.co.za',
                'contact_phone'   => '010 824 9671',
                'address'         => '112 Boeing Rd, Bedfordview, Johannesburg',
                'country'         => 'ZA',
                'currency'        => 'ZAR',
                'bank_account_holder' => 'Bold Mark Properties',
                'bank_name'           => 'Standard Bank',
                'bank_account_type'   => 'Current',
                'bank_account_number' => '201656302',
                'bank_branch_code'    => '004305',
                'bank_branch_name'    => 'Rosebank',
                'primary_color'   => '#0B1F38',
                'secondary_color' => '#D89B4B',
                'credentials'     => ['NAMA-9141', 'PPRA Registered', 'Johannesburg · Botswana'],
                'copyright_name'  => 'Bold Mark Properties',
                'is_active'       => true,
            ]
        );
    }

    /* ------------------------------------------------------------------ */
    /*  DEFAULT LEDGERS                                                */
    /* ------------------------------------------------------------------ */

    private function seedDefaultLedgers(): void
    {
        $this->command?->info('Seeding default ledgers...');
        foreach (Organization::all() as $organization) {
            CommunityLedgerService::seedDefaultsForOrganization($organization->id);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  USERS (internal + 4th admin)                                        */
    /* ------------------------------------------------------------------ */

    private function seedAllUsers(): void
    {
        $this->command?->info('Seeding demo users...');
        $organization  = Organization::where('slug', 'boldmark')->firstOrFail();
        $boldPwd = Hash::make(env('BOLDMARK_ADMIN_PASSWORD', 'password'));

        $users = [
            ['name'=>'Julian Tabona',   'email'=>'julian@boldmarkprop.co.za',  'password'=>$boldPwd,              'phone'=>'+27 82 555 0000',  'organization_id'=>$organization->id, 'role'=>'company-admin'],
            ['name'=>'Justin Justin',   'email'=>'justin@boldmarkprop.co.za',  'password'=>$boldPwd,              'phone'=>'+267 72 555 0001', 'organization_id'=>$organization->id, 'role'=>'company-admin'],
            ['name'=>'Ayanda Dlamini',  'email'=>'ayanda@boldmarkprop.co.za',  'password'=>$boldPwd,              'phone'=>'+267 72 555 0005', 'organization_id'=>$organization->id, 'role'=>'company-admin'],
            ['name'=>'Mduduzi Mhlanga', 'email'=>'mduduzi@boldmarkprop.co.za', 'password'=>$boldPwd,              'phone'=>'+27 79 917 8596',  'organization_id'=>$organization->id, 'role'=>'company-admin'],
            ['name'=>'Brandon Tabona',  'email'=>'brandontabona@gmail.com',    'password'=>$boldPwd,              'phone'=>'+27 82 555 0003',  'organization_id'=>$organization->id, 'role'=>'company-admin'],
            ['name'=>'Thabo Ndlovu',   'email'=>'pm@demo.boldmark.test',     'password'=>Hash::make('password'), 'phone'=>'+267 72 555 0002', 'organization_id'=>$organization->id, 'role'=>'portfolio-manager'],
            ['name'=>'Lerato Pillay',  'email'=>'fc@demo.boldmark.test',     'password'=>Hash::make('password'), 'phone'=>'+267 72 555 0003', 'organization_id'=>$organization->id, 'role'=>'financial-controller'],
            ['name'=>'Naledi Khumalo', 'email'=>'pa@demo.boldmark.test',     'password'=>Hash::make('password'), 'phone'=>'+267 72 555 0004', 'organization_id'=>$organization->id, 'role'=>'portfolio-assistant'],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);
            $user = User::updateOrCreate(['email' => $data['email']], $data);
            $user->syncRoles([Role::findByName($role, 'api')]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  EXTERNAL USERS                                                      */
    /* ------------------------------------------------------------------ */

    private function seedExternalUsers(): void
    {
        $this->command?->info('Seeding external users...');
        $organization = Organization::where('slug', 'boldmark')->firstOrFail();

        $externalUsers = [
            ['name'=>'Kgomotso Tlokweng',   'email'=>'k.tlokweng@gmail.com',    'phone'=>'+267 71 601 1001', 'role'=>'trustee'],
            ['name'=>'Badisa Morokotso',     'email'=>'b.morokotso@hotmail.com', 'phone'=>'+267 71 601 1002', 'role'=>'trustee'],
            ['name'=>'Thandiwe Nkosi',       'email'=>'t.nkosi@yahoo.com',       'phone'=>'+267 71 601 1003', 'role'=>'trustee'],
            ['name'=>'Omphemetse Sekgololo', 'email'=>'o.sekgololo@gmail.com',   'phone'=>'+267 71 601 1004', 'role'=>'trustee'],
            ['name'=>'Kedibonye Gaefele',    'email'=>'k.gaefele@gmail.com',     'phone'=>'+267 71 601 1005', 'role'=>'trustee'],
            ['name'=>'Boitumelo Molefe',     'email'=>'boi.molefe@gmail.com',    'phone'=>'+267 71 602 2001', 'role'=>'owner'],
            ['name'=>'Gaositwe Sekgwele',    'email'=>'g.sekgwele@gmail.com',    'phone'=>'+267 71 602 2002', 'role'=>'owner'],
            ['name'=>'Motlalepula Gaborone', 'email'=>'m.gaborone@yahoo.com',    'phone'=>'+267 71 602 2003', 'role'=>'owner'],
            ['name'=>'Kelapile Ntlo',        'email'=>'k.ntlo@outlook.com',      'phone'=>'+267 71 602 2004', 'role'=>'owner'],
            ['name'=>'Dimakatso Phele',      'email'=>'d.phele@gmail.com',       'phone'=>'+267 71 602 2005', 'role'=>'owner'],
            ['name'=>'Keagile Ramotshabi',   'email'=>'k.ramotshabi@gmail.com',  'phone'=>'+267 71 603 3001', 'role'=>'occupant'],
            ['name'=>'Naledi Keorapetse',    'email'=>'n.keorapetse@gmail.com',  'phone'=>'+267 71 603 3002', 'role'=>'occupant'],
            ['name'=>'Tumisang Gaokgakala',  'email'=>'t.gaokgakala@yahoo.com',  'phone'=>'+267 71 603 3003', 'role'=>'occupant'],
            ['name'=>'Emang Segolodi',       'email'=>'e.segolodi@gmail.com',    'phone'=>'+267 71 603 3004', 'role'=>'occupant'],
            ['name'=>'Boikhutso Setihapi',   'email'=>'b.setihapi@hotmail.com',  'phone'=>'+267 71 603 3005', 'role'=>'occupant'],
            ['name'=>'Masa Plumbing Services',      'email'=>'info@masaplumbing.bw',    'phone'=>'+267 31 876 4001', 'role'=>'contractor'],
            ['name'=>'Phenyo Electrical Works',     'email'=>'phenyo.elec@gmail.com',   'phone'=>'+267 71 604 4002', 'role'=>'contractor'],
            ['name'=>'Kgomo Construction Ltd',      'email'=>'admin@kgomoconstruct.bw', 'phone'=>'+267 31 876 4003', 'role'=>'contractor'],
            ['name'=>'TiTi Cleaning Services',      'email'=>'titi.clean@gmail.com',    'phone'=>'+267 71 604 4004', 'role'=>'contractor'],
            ['name'=>'Botswana Security Solutions', 'email'=>'ops@bwsecurity.bw',       'phone'=>'+267 31 876 4005', 'role'=>'contractor'],
        ];

        foreach ($externalUsers as $data) {
            $role = $data['role'];
            unset($data['role']);
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => Hash::make('password'), 'organization_id' => $organization->id])
            );
            $user->syncRoles([Role::findByName($role, 'api')]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  COMMUNITY SEEDING — ORCHESTRATOR                                       */
    /* ------------------------------------------------------------------ */

    private function seedCommunities(): void
    {
        $organization = Organization::where('slug', 'boldmark')->firstOrFail();
        $this->organizationId = $organization->id;

        // A portfolio manager to assign as each community's Community Manager.
        $this->managerUserId = User::where('organization_id', $this->organizationId)
            ->where('email', 'pm@demo.boldmark.test')
            ->value('id')
            ?? User::where('organization_id', $this->organizationId)->orderBy('id')->value('id');
        $this->communitySeq = 0;

        // Wipe previously seeded uploaded files so every run is clean
        Storage::disk('public')->deleteDirectory("proof_of_payment/{$organization->id}");
        Storage::disk('public')->deleteDirectory('occupants');

        // Load ledgers keyed by shorthand code
        $this->loadLedgers();

        // Continue invoice numbering from any already-seeded invoices
        $this->invoiceCounter = Invoice::where('organization_id', $this->organizationId)->count();

        // ── Botswana Communities ──
        foreach ($this->botswanaCommunityDefinitions() as $def) {
            $this->command?->info("Seeding community: {$def['name']}");
            $community = $this->createCommunity($def);
            $this->enableCommunityLedgers($community, $def['type']);
            $this->seedUnits($community, $def);
            $this->seedCustomerGroups($community);
        }

        // ── South Africa Communities ──
        foreach ($this->southAfricaCommunityDefinitions() as $def) {
            $this->command?->info("Seeding community: {$def['name']}");
            $community = $this->createCommunity($def);
            $this->enableCommunityLedgers($community, $def['type']);
            $this->seedUnits($community, $def);
            $this->seedCustomerGroups($community);
        }

        // Recalculate unit balances after all invoices and payments are seeded
        $this->command?->info('Recalculating unit balances...');
        $balanceService = app(UnitBalanceService::class);
        Unit::where('organization_id', $this->organizationId)->each(function (Unit $unit) use ($balanceService) {
            $balanceService->recalculate($unit);
        });

        // Spread collection statuses / debit-order / transfer flags so the Age
        // Analysis status markers show the full WeConnectU range.
        $this->command?->info('Seeding collection statuses...');
        $this->call(DemoCollectionStatusSeeder::class);

        // Status-change history for the Status Batches / Automatic Status Changes pages.
        $this->command?->info('Seeding status history...');
        $this->call(DemoStatusHistorySeeder::class);
    }

    private function loadLedgers(): void
    {
        $nameToCode = array_flip(self::LEDGER_NAME_MAP);
        Ledger::where('organization_id', $this->organizationId)->get()
            ->each(function ($ct) use ($nameToCode) {
                $code = $nameToCode[$ct->name] ?? null;
                if ($code) {
                    $this->ledgers[$code] = $ct;
                }
            });
    }

    /* ------------------------------------------------------------------ */
    /*  COMMUNITY CREATION                                                     */
    /* ------------------------------------------------------------------ */

    private function createCommunity(array $def): Community
    {
        $this->communitySeq++;
        $seq     = $this->communitySeq;
        $isZA    = ($def['country'] ?? null) === 'ZA';
        $year    = 1980 + $seq; // spread scheme registration years

        $community = Community::create([
            'name'                => $def['name'],
            'entity_type'         => $def['type'],
            'address'             => $def['address'],
            'admin_fund_amount'   => $def['admin_fund_amount'] ?? null,
            'reserve_fund_amount' => $def['reserve_fund_amount'] ?? null,
            'default_rent_amount' => $def['default_rent_amount'] ?? null,
            'billing_day'         => $def['billing_day'],
            'country'             => $def['country'],
            'currency'            => $def['currency'],
            'is_active'           => true,
            'organization_id'     => $this->organizationId,

            // ── Registration / tax identifiers (Information tab) ──────────
            'registration_number'      => sprintf('SS%d/%d', 30 + $seq, $year),
            'csos_registration_number' => $isZA ? sprintf('CSOS%06d', 100000 + $seq) : null,
            'income_tax_number'        => sprintf('91%08d', 10000000 + $seq),
            'merchant_number'          => sprintf('MERCH-%05d', 40000 + $seq),
            'community_manager_id'     => $this->managerUserId,
            'payment_authorisation_mode' => 'single',

            // ── Admin charges (Admin Charges tab / Settings → Charges) ────
            'penalty_admin_fee'         => 65.00,
            'warning_admin_fee'         => 150.00,
            'transfer_clearance_fee'    => 1100.00,
            'phonecall_fee'             => 20.00,
            'handed_over_fee'           => 350.00,
            'notice_threshold_amount'   => 350.00,
            'apply_debt_collection_fee' => true,
            'notices_exemption'         => ['debit_order', 'handed_over', 'payment_arrangement'],
            'notice_charges'            => [
                'first'            => ['email_charge' => 0.00,  'sms_charge' => 3.45, 'threshold' => 'current', 'status' => null],
                'second'           => ['email_charge' => 27.60, 'sms_charge' => 3.45, 'threshold' => '30_days', 'status' => null],
                'letter_of_demand' => ['email_charge' => 75.00, 'sms_charge' => 3.45, 'threshold' => '60_days', 'status' => null],
            ],
        ]);

        $community->timestamps = false;
        $community->created_at = $def['created_at'];
        $community->updated_at = $def['created_at'];
        $community->save();
        $community->timestamps = true;

        $this->createBankAccounts($community);
        $this->seedCommunityGovernance($community, $seq);

        return $community;
    }

    /**
     * Seed the Default Billing Setup recovery flags (Information tab) and a small
     * board of directors / trustees (Trustees tab) for the community-info modal.
     */
    private function seedCommunityGovernance(Community $community, int $seq): void
    {
        CommunityBillingSetup::updateOrCreate(
            ['community_id' => $community->id],
            [
                'organization_id'      => $this->organizationId,
                'water_recovery'       => true,
                'electricity_recovery' => true,
            ]
        );

        $trustees = [
            ['name' => 'Kgomotso Tlokweng',   'email' => "trustee1.c{$seq}@boldmarkprop.co.za", 'cellphone' => '+267 71 601 1001'],
            ['name' => 'Badisa Morokotso',    'email' => "trustee2.c{$seq}@boldmarkprop.co.za", 'cellphone' => '+267 71 601 1002'],
            ['name' => 'Thandiwe Nkosi',      'email' => "trustee3.c{$seq}@boldmarkprop.co.za", 'cellphone' => '+27 82 601 1003'],
        ];
        foreach ($trustees as $i => $t) {
            CommunityMember::create([
                'organization_id'     => $this->organizationId,
                'community_id'        => $community->id,
                'name'                => $t['name'],
                'email'               => $t['email'],
                'cellphone'           => $t['cellphone'],
                'user_type'           => 'director_trustee',
                'is_director_trustee' => true,
                'is_payment_authoriser' => $i === 0,
                'is_verified'         => true,
                'sort_order'          => $i,
            ]);
        }
    }

    /**
     * Seed a current (bank) account and an investment account per community so
     * the dashboard financial card (Bank Balance / Investments) shows real data.
     */
    private function createBankAccounts(Community $community): void
    {
        $asAt = now()->subDays(rand(1, 15))->toDateString();

        BankAccount::create([
            'organization_id' => $this->organizationId,
            'community_id'    => $community->id,
            'name'            => 'Standard Bank Current',
            'bank_name'       => 'Standard Bank',
            'account_number'  => (string) rand(100000000, 999999999),
            'branch_code'     => '051001',
            'branch_name'     => 'Rosebank',
            'integration'     => 'Standard Bank Business Free',
            'type'            => BankAccountType::CURRENT->value,
            'balance'         => round(rand(5000, 350000) + (rand(0, 99) / 100), 2),
            'balance_as_at'   => $asAt,
            'is_active'       => true,
        ]);

        // Most (not all) communities hold a reserve-fund investment account.
        if (rand(1, 100) <= 75) {
            BankAccount::create([
                'organization_id' => $this->organizationId,
                'community_id'    => $community->id,
                'name'            => 'Reserve Fund Investment',
                'bank_name'       => 'Nedbank',
                'account_number'  => (string) rand(100000000, 999999999),
                'type'            => BankAccountType::INVESTMENT->value,
                'balance'         => round(rand(0, 700000) + (rand(0, 99) / 100), 2),
                'balance_as_at'   => $asAt,
                'is_active'       => true,
            ]);
        }
    }

    private function enableCommunityLedgers(Community $community, string $type): void
    {
        // Every demo community is a levy-billed ownership scheme (the 5 supported
        // entity types), so they all share the levy ledger set.
        $activeCodes = ['LEVY','WATER_RECOVERY','ELECTRICITY_RECOVERY','SEWERAGE_RECOVERY',
                        'REFUSE_RECOVERY','PARKING_RENTAL','GYM_ACCESS','POOL_ACCESS',
                        'PET_LEVY','GARDEN_MAINT','SECURITY_CONTRIB','SPECIAL_LEVY',
                        'LATE_INTEREST','LATE_PENALTY','ACCESS_CARD'];

        foreach ($this->ledgers as $code => $ct) {
            CommunityLedger::updateOrCreate(
                ['community_id' => $community->id, 'ledger_id' => $ct->id],
                ['is_active' => in_array($code, $activeCodes, true)]
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  UNIT SEEDING                                                        */
    /* ------------------------------------------------------------------ */

    private function seedUnits(Community $community, array $def): void
    {
        foreach ($def['units'] as $unitDef) {
            $unit  = $this->createUnit($community, $unitDef);
            $owner = $this->createOwner($unit, $unitDef['owner']);

            $currentOccupant = null;
            if ($unitDef['occupancy'] === 'occupant_occupied') {
                $currentOccupant = $this->seedOccupants($unit, $unitDef);
            }

            $this->seedInvoicesAndPayments($community, $unit, $owner, $currentOccupant, $unitDef, $def);
            $this->seedUnitActivities($unit, $owner, $currentOccupant, $def['type']);
        }
    }

    private function createUnit(Community $community, array $def): Unit
    {
        return Unit::create([
            'unit_number'    => $def['number'],
            'address'        => $community->address . ' — Unit ' . $def['number'],
            'occupancy_type' => $def['occupancy'],
            'status'         => 'active',
            'levy_override'  => $def['levy_override'] ?? null,
            // All demo communities are levy-billed ownership schemes — the
            // community levy (admin_fund_amount) drives billing, so units carry
            // no rent. (default_rent_amount is null for every community.)
            'rent_amount'    => $community->default_rent_amount,
            'balance'        => 0,
            'community_id'      => $community->id,
            'organization_id' => $this->organizationId,
        ]);
    }

    private function createOwner(Unit $unit, array $data): Owner
    {
        // WeConnectU-style customer code: 3-letter name prefix + sequence + unit suffix.
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $data['full_name'] ?? ''));
        $prefix  = $letters !== '' ? str_pad(substr($letters, 0, 3), 3, 'X') : 'CUS';

        return Owner::create(array_merge([
            'customer_code' => $prefix . '001-U' . $unit->unit_number,
            'customer_type' => $data['customer_type'] ?? 'individual',
        ], $data, [
            'unit_id'         => $unit->id,
            'community_id'    => $unit->community_id,
            'organization_id' => $this->organizationId,
        ]));
    }

    /**
     * Seed a couple of customer groups per community and attach a subset of its
     * customers, so the Manage Customers "Customer Groups" tab shows real data.
     */
    private function seedCustomerGroups(Community $community): void
    {
        $groups = collect(['Trustees', 'Directors'])->map(fn (string $name) => CustomerGroup::create([
            'name'            => $name,
            'community_id'    => $community->id,
            'organization_id' => $this->organizationId,
        ]));

        $owners = Owner::whereHas('unit', fn ($query) => $query->where('community_id', $community->id))->get();

        foreach ($owners as $index => $owner) {
            if ($index % 4 === 0) {
                $owner->customerGroups()->syncWithoutDetaching([$groups[$index % 2]->id]);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  OCCUPANT SEEDING                                                      */
    /* ------------------------------------------------------------------ */

    private function seedOccupants(Unit $unit, array $def): ?Occupant
    {
        $organizations = $def['organizations'] ?? [];
        if (empty($organizations)) return null;

        $currentData  = array_pop($organizations);
        $previousData = $organizations;

        foreach ($previousData as $prev) {
            $ut = Occupant::create(array_merge($prev, [
                'unit_id'         => $unit->id,
                'organization_id' => $this->organizationId,
                'is_active'       => false,
            ]));
            $this->attachLeaseDocument($ut);
        }

        $current = Occupant::create(array_merge($currentData, [
            'unit_id'         => $unit->id,
            'organization_id' => $this->organizationId,
            'is_active'       => true,
            'move_out_date'   => null,
            'move_out_reason' => null,
            'move_out_notes'  => null,
        ]));
        $this->attachLeaseDocument($current);

        return $current;
    }

    private function attachLeaseDocument(Occupant $ut): void
    {
        $srcPath = base_path('samples/documents/lease-agreement.pdf');
        $destRel = "occupants/{$ut->id}/lease-agreement.pdf";

        if (file_exists($srcPath)) {
            Storage::disk('public')->put($destRel, file_get_contents($srcPath));
            $ut->update([
                'lease_document_url'  => Storage::disk('public')->url($destRel),
                'lease_document_name' => "Lease_Agreement_{$ut->full_name}.pdf",
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  INVOICE & PAYMENT SEEDING                                           */
    /* ------------------------------------------------------------------ */

    private function seedInvoicesAndPayments(
        Community $community, Unit $unit, Owner $owner,
        ?Occupant $currentOccupant, array $unitDef, array $communityDef
    ): void {
        $startPeriod  = $communityDef['start_period'];
        $periods      = array_slice(self::PERIODS, $startPeriod);
        $isDebtor     = $unitDef['debtor'] ?? false;
        $debtorFrom   = $unitDef['debtor_from'] ?? 6;
        $communityType   = $communityDef['type'];

        $primaryCodes = $this->primaryCodes($communityType, $unit->occupancy_type->value);
        $extraCode    = $unitDef['extra_ct'] ?? null;

        $invoiceCount = 0;
        $maxInvoices  = 10;

        foreach ($primaryCodes as $code) {
            $ct = $this->ledgers[$code] ?? null;
            if (! $ct) continue;

            foreach ($periods as $periodIndex => $periodDef) {
                if ($invoiceCount >= $maxInvoices) break 2;

                $absolutePeriodIndex = $startPeriod + $periodIndex;
                $amount = $this->resolveAmount($code, $unit, $owner, $currentOccupant, $community);
                if ($amount <= 0) continue;

                [$billedToType, $billedToId, $recipientEmail, $recipientName] =
                    $this->resolveRecipient($code, $unit, $owner, $currentOccupant);
                if (! $billedToId) continue;

                $status  = $this->resolveStatus($absolutePeriodIndex, $isDebtor, $debtorFrom);
                $invoice = $this->createInvoice($unit, $ct, $billedToType, $billedToId, $amount, $periodDef, $status, $community);

                $this->createEmailEvents($invoice, $status, $recipientEmail);

                if ($status === 'paid') {
                    $this->createCashbookEntry($invoice, $unit, $community, $recipientName, $ct);
                }

                $invoiceCount++;
            }
        }

        if ($extraCode && $invoiceCount < $maxInvoices) {
            $extraCt = $this->ledgers[$extraCode] ?? null;
            if ($extraCt) {
                $extraPeriods = array_slice($periods, -min(2, $maxInvoices - $invoiceCount));
                foreach ($extraPeriods as $periodDef) {
                    if ($invoiceCount >= $maxInvoices) break;
                    $amount = $this->resolveExtraAmount($extraCode);
                    [$billedToType, $billedToId, $recipientEmail, $recipientName] =
                        $this->resolveRecipient($extraCode, $unit, $owner, $currentOccupant);
                    if (! $billedToId) continue;

                    $invoice = $this->createInvoice($unit, $extraCt, $billedToType, $billedToId, $amount, $periodDef, 'paid', $community);
                    $this->createEmailEvents($invoice, 'paid', $recipientEmail);
                    $this->createCashbookEntry($invoice, $unit, $community, $recipientName, $extraCt);
                    $invoiceCount++;
                }
            }
        }
    }

    private function primaryCodes(string $communityType, string $occupancy): array
    {
        return match ($communityType) {
            'sectional_title'    => ['LEVY'],
            'residential_rental' => ['RENT'],
            'commercial_rental'  => ['RENT'],
            'mixed' => match ($occupancy) {
                'occupant_occupied' => ['LEVY', 'RENT'],
                default           => ['LEVY'],
            },
            default => ['LEVY'],
        };
    }

    private function resolveAmount(string $code, Unit $unit, Owner $owner, ?Occupant $organization, Community $community): float
    {
        return match ($code) {
            'LEVY' => $unit->levy_override ?? $community->admin_fund_amount ?? 0,
            'RENT' => $unit->rent_amount   ?? $community->default_rent_amount  ?? 0,
            default => 0,
        };
    }

    private function resolveExtraAmount(string $code): float
    {
        return match ($code) {
            'PARKING_RENTAL'       => 450.00,
            'GYM_ACCESS'           => 300.00,
            'POOL_ACCESS'          => 250.00,
            'WATER_RECOVERY'       => 380.00,
            'ELECTRICITY_RECOVERY' => 620.00,
            'STORAGE_RENTAL'       => 350.00,
            default                => 200.00,
        };
    }

    private function resolveRecipient(string $code, Unit $unit, Owner $owner, ?Occupant $occupant): array
    {
        $ct = $this->ledgers[$code] ?? null;
        if (! $ct) return ['owner', null, null, null];

        $appliesTo = $ct->applies_to instanceof \App\Enums\LedgerAppliesTo
            ? $ct->applies_to->value
            : (string) $ct->applies_to;

        if ($appliesTo === 'occupant') {
            if (! $occupant) return ['occupant', null, null, null];
            return ['occupant', $occupant->id, $occupant->email, $occupant->full_name];
        }

        if ($appliesTo === 'owner') {
            return ['owner', $owner->id, $owner->email, $owner->full_name];
        }

        if ($unit->occupancy_type->value === 'occupant_occupied' && $occupant) {
            return ['occupant', $occupant->id, $occupant->email, $occupant->full_name];
        }
        return ['owner', $owner->id, $owner->email, $owner->full_name];
    }

    private function resolveStatus(int $absolutePeriodIndex, bool $isDebtor, int $debtorFrom): string
    {
        if ($isDebtor && $absolutePeriodIndex >= $debtorFrom) {
            return 'overdue';
        }
        return 'paid';
    }

    private function createInvoice(
        Unit $unit, $ledger, string $billedToType, string $billedToId,
        float $amount, array $periodDef, string $status, Community $community
    ): Invoice {
        $this->invoiceCounter++;
        $year      = substr($periodDef['period'], 0, 4);
        $num       = str_pad($this->invoiceCounter, 4, '0', STR_PAD_LEFT);
        $invNumber = "INV-{$year}-{$num}";

        return Invoice::create([
            'invoice_number'    => $invNumber,
            'status'            => $status,
            'billed_to_type'    => $billedToType,
            'billed_to_id'      => $billedToId,
            'amount'            => $amount,
            'billing_period'    => $periodDef['period'],
            'due_date'          => $periodDef['due_date'],
            'sent_at'           => $periodDef['sent_at'] ?? null,
            'issued_by_type'    => 'system',
            'issued_by_user_id' => null,
            'unit_id'           => $unit->id,
            'ledger_id'    => $ledger->id,
            'organization_id'   => $this->organizationId,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  EMAIL EVENTS                                                        */
    /* ------------------------------------------------------------------ */

    private function createEmailEvents(Invoice $invoice, string $status, string $email): void
    {
        if (! $invoice->sent_at) return;

        $sentAt = Carbon::parse($invoice->sent_at);

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id' => $this->organizationId,
            'event_type'      => 'sent',
            'email'           => $email,
            'resend_email_id' => 'demo_' . Str::random(20),
            'occurred_at'     => $sentAt,
            'metadata'        => ['source' => 'demo_seed'],
        ]);

        $deliveredAt = $sentAt->copy()->addMinutes(rand(8, 15));
        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id' => $this->organizationId,
            'event_type'      => 'delivered',
            'email'           => $email,
            'resend_email_id' => 'demo_' . Str::random(20),
            'occurred_at'     => $deliveredAt,
            'metadata'        => ['source' => 'demo_seed'],
        ]);

        if ($status === 'paid') {
            $openedAt = $deliveredAt->copy()->addHours(rand(2, 5))->addMinutes(rand(0, 30));
            InvoiceEmailEvent::create([
                'invoice_id'      => $invoice->id,
                'organization_id' => $this->organizationId,
                'event_type'      => 'opened',
                'email'           => $email,
                'resend_email_id' => 'demo_' . Str::random(20),
                'occurred_at'     => $openedAt,
                'metadata'        => ['source' => 'demo_seed'],
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  CASHBOOK ENTRIES                                                    */
    /* ------------------------------------------------------------------ */

    private function createCashbookEntry(Invoice $invoice, Unit $unit, Community $community, string $payerName, $ledger): void
    {
        $sentAt      = Carbon::parse($invoice->sent_at);
        $openedAt    = $sentAt->copy()->addHours(rand(3, 6));
        $paymentDate = $openedAt->copy()->addDays(rand(1, 5));

        $today = Carbon::parse('2026-04-20');
        if ($paymentDate->gt($today)) {
            $paymentDate = $today->copy()->subDays(rand(0, 3));
        }

        $nameParts = explode(' ', strtoupper($payerName));
        $surname   = end($nameParts);
        $ctName    = strtoupper(str_replace(' ', '_', $ledger->name));
        $period    = Carbon::parse($invoice->billing_period)->format('M Y');
        $desc      = "EFT – {$surname} {$ctName} {$period}";

        $proofPath = $this->storeReceipt();

        CashbookEntry::create([
            'description'           => $desc,
            'amount'                => $invoice->amount,
            'type'                  => 'credit',
            'date'                  => $paymentDate->toDateString(),
            'notes'                 => "Payment received for {$invoice->invoice_number}",
            'proof_of_payment_path' => $proofPath,
            'community_id'             => $community->id,
            'organization_id'       => $this->organizationId,
            'ledger_id'        => $ledger->id,
            'unit_id'               => $unit->id,
            'invoice_id'            => $invoice->id,
            'parent_entry_id'       => null,
        ]);
    }

    private function storeReceipt(): ?string
    {
        $receipt = self::RECEIPTS[$this->receiptIndex % count(self::RECEIPTS)];
        $this->receiptIndex++;

        $srcPath = base_path($receipt['src']);
        if (! file_exists($srcPath)) return null;

        $uniqueId = Str::uuid()->toString();
        $destRel  = "proof_of_payment/{$this->organizationId}/{$uniqueId}.{$receipt['ext']}";
        Storage::disk('public')->put($destRel, file_get_contents($srcPath));

        return $destRel;
    }

    /* ------------------------------------------------------------------ */
    /*  UNIT ACTIVITIES                                                     */
    /* ------------------------------------------------------------------ */

    private function seedUnitActivities(Unit $unit, Owner $owner, ?Occupant $occupant, string $communityType): void
    {
        $unitNum = (int) preg_replace('/[^0-9]/', '', $unit->unit_number);
        if (($unitNum % 5) !== 0 && ($unitNum % 7) !== 0) return;

        $batchId    = (string) Str::uuid();
        $activities = [];

        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id' => $this->organizationId,
            'batch_id'        => $batchId,
            'changed_by_name' => 'Justin Sobhee',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([['field'=>'full_name','old'=>$owner->full_name.' (Maiden)','new'=>$owner->full_name]]),
            'created_at'      => Carbon::parse('2025-08-10 10:23:00'),
            'updated_at'      => Carbon::parse('2025-08-10 10:23:00'),
        ];
        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id' => $this->organizationId,
            'batch_id'        => null,
            'changed_by_name' => 'Thabo Ndlovu',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([['field'=>'email','old'=>'old.'.$owner->email,'new'=>$owner->email]]),
            'created_at'      => Carbon::parse('2025-09-05 14:11:00'),
            'updated_at'      => Carbon::parse('2025-09-05 14:11:00'),
        ];
        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id' => $this->organizationId,
            'batch_id'        => null,
            'changed_by_name' => 'Naledi Khumalo',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([['field'=>'phone','old'=>'+267 71 000 0000','new'=>$owner->phone]]),
            'created_at'      => Carbon::parse('2025-10-20 09:45:00'),
            'updated_at'      => Carbon::parse('2025-10-20 09:45:00'),
        ];
        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id' => $this->organizationId,
            'batch_id'        => $batchId,
            'changed_by_name' => 'Justin Sobhee',
            'event'           => 'Unit created',
            'category'        => 'unit',
            'changes'         => json_encode([['field'=>'unit_number','old'=>null,'new'=>$unit->unit_number],['field'=>'occupancy_type','old'=>null,'new'=>$unit->occupancy_type->value]]),
            'created_at'      => Carbon::parse('2025-07-01 09:30:00'),
            'updated_at'      => Carbon::parse('2025-07-01 09:30:00'),
        ];

        if ($occupant && in_array($communityType, ['mixed','residential_rental','commercial_rental'], true)) {
            $activities[] = [
                'unit_id'         => $unit->id,
                'organization_id' => $this->organizationId,
                'batch_id'        => null,
                'changed_by_name' => 'Lerato Pillay',
                'event'           => 'Moved in occupant',
                'category'        => 'occupant',
                'changes'         => json_encode([
                    ['field'=>'occupant',      'old'=>null, 'new'=>$occupant->full_name],
                    ['field'=>'lease_start', 'old'=>null, 'new'=>(string)$occupant->lease_start],
                    ['field'=>'lease_end',   'old'=>null, 'new'=>(string)$occupant->lease_end],
                    ['field'=>'rent_amount', 'old'=>null, 'new'=>(string)$unit->rent_amount],
                ]),
                'created_at' => Carbon::parse($occupant->lease_start)->addDays(1)->setTime(8,30,0),
                'updated_at' => Carbon::parse($occupant->lease_start)->addDays(1)->setTime(8,30,0),
            ];
            $activities[] = [
                'unit_id'         => $unit->id,
                'organization_id' => $this->organizationId,
                'batch_id'        => null,
                'changed_by_name' => 'Thabo Ndlovu',
                'event'           => 'Updated occupant details',
                'category'        => 'occupant',
                'changes'         => json_encode([['field'=>'phone','old'=>'+267 71 000 0099','new'=>$occupant->phone]]),
                'created_at' => Carbon::parse($occupant->lease_start)->addDays(10)->setTime(11,15,0),
                'updated_at' => Carbon::parse($occupant->lease_start)->addDays(10)->setTime(11,15,0),
            ];
        }

        $rows = array_map(fn($a) => array_merge($a, [
            'id'         => (string) Str::uuid(),
            'created_at' => $a['created_at'] instanceof Carbon ? $a['created_at']->toDateTimeString() : $a['created_at'],
            'updated_at' => $a['updated_at'] instanceof Carbon ? $a['updated_at']->toDateTimeString() : $a['updated_at'],
        ]), $activities);

        DB::table('unit_activities')->insert($rows);
    }

    /* ------------------------------------------------------------------ */
    /*  DATA-BUILDER HELPERS                                                */
    /* ------------------------------------------------------------------ */

    private function o(string $name, string $email, string $phone, string $idNumber, string $address): array
    {
        return compact('email','phone','address') + ['full_name'=>$name,'id_number'=>$idNumber];
    }

    private function tc(string $name, string $email, string $phone, string $idNumber, string $leaseStart, string $leaseEnd, float $rent): array
    {
        return ['full_name'=>$name,'email'=>$email,'phone'=>$phone,'id_number'=>$idNumber,'lease_start'=>$leaseStart,'lease_end'=>$leaseEnd];
    }

    private function t(string $name, string $email, string $phone, string $idNumber, string $leaseStart, string $leaseEnd, string $moveOutDate, float $rent, string $reason): array
    {
        return [
            'full_name'       => $name,
            'email'           => $email,
            'phone'           => $phone,
            'id_number'       => $idNumber,
            'lease_start'     => $leaseStart,
            'lease_end'       => $leaseEnd,
            'move_out_date'   => $moveOutDate,
            'move_out_reason' => $reason,
            'move_out_notes'  => "Organization {$name} vacated the unit on {$moveOutDate}. {$reason}.",
        ];
    }

    /* ================================================================== */
    /*  BOTSWANA COMMUNITY DEFINITIONS                                         */
    /* ================================================================== */

    private function botswanaCommunityDefinitions(): array
    {
        return [
            ['name'=>'Molapo Crossing Residences','type'=>'body_corporate','address'=>'Plot 12875, Molapo Extension, Gaborone, Botswana',
             'admin_fund_amount'=>2500.00,'reserve_fund_amount'=>750.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-07-01 09:00:00','start_period'=>0,'units'=>$this->molapoCrossingUnits()],
            ['name'=>'Phakalane Golf Community','type'=>'home_owners_association','address'=>'Plot 2540, Phakalane, North-East Gaborone, Botswana',
             'admin_fund_amount'=>1800.00,'reserve_fund_amount'=>600.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-07-05 10:00:00','start_period'=>0,'units'=>$this->phakalaneGolfUnits()],
            ['name'=>'Kgale Hill Body Corporate','type'=>'body_corporate','address'=>'Lot 45891, Kgale Hill Extension 5, Gaborone, Botswana',
             'admin_fund_amount'=>3200.00,'reserve_fund_amount'=>950.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-08-01 08:00:00','start_period'=>1,'units'=>$this->kgaleHillUnits()],
            ['name'=>'Masa Centre Scheme','type'=>'body_corporate','address'=>'Plot 54360, Masa Centre, CBD, Gaborone, Botswana',
             'admin_fund_amount'=>2800.00,'reserve_fund_amount'=>840.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-09-01 08:00:00','start_period'=>2,'units'=>$this->masaCentreUnits()],
            ['name'=>'Extension 15 Home Owners','type'=>'home_owners_association','address'=>'15 Tswane Drive, Extension 15, Gaborone, Botswana',
             'admin_fund_amount'=>1500.00,'reserve_fund_amount'=>450.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-10-01 08:00:00','start_period'=>3,'units'=>$this->extension15Units()],
            ['name'=>'Fairgrounds Business Park','type'=>'property_owners_association','address'=>'Plot 6940, Fairgrounds Office Park, Gaborone, Botswana',
             'admin_fund_amount'=>3500.00,'reserve_fund_amount'=>1050.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'BW','currency'=>'BWP',
             'created_at'=>'2025-11-01 08:00:00','start_period'=>4,'units'=>$this->fairgroundsUnits()],
        ];
    }

    private function molapoCrossingUnits(): array
    {
        return [
            ['number'=>'MCR-A01','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Keletso Mosweu','k.mosweu@email.bw','+267 72 101 1001','BW740102-1001','Plot 1201, Molapo Ext, Gaborone')],
            ['number'=>'MCR-A02','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Otsile Gabaake','o.gabaake@gmail.com','+267 71 101 1002','BW780515-1002','Plot 4421, Block 3, Gaborone')],
            ['number'=>'MCR-A03','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Refilwe Setlhare','r.setlhare@hotmail.com','+267 72 101 1003','BW690820-1003','Plot 7821, Naledi, Gaborone')],
            ['number'=>'MCR-A04','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Mpho Tlhomeso','mpho.tlhomeso@gmail.com','+267 71 101 1004','BW751130-1004','Plot 5521, Tlokweng, Gaborone')],
            ['number'=>'MCR-A05','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'PARKING_RENTAL','owner'=>$this->o('Goitseone Segaetsho','g.segaetsho@yahoo.com','+267 72 101 1005','BW830205-1005','Plot 9901, Phase 2, Gaborone')],
            ['number'=>'MCR-A06','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Bonolo Molefhe','bonolo.molefhe@gmail.com','+267 71 101 1006','BW880910-1006','Plot 2231, Broadhurst, Gaborone')],
            ['number'=>'MCR-A07','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Lesego Gaolekwe','l.gaolekwe@gmail.com','+267 72 101 1007','BW770601-1007','Plot 3341, Bontleng, Gaborone')],
            ['number'=>'MCR-A08','occupancy'=>'owner_occupied','debtor'=>true,'debtor_from'=>6,'owner'=>$this->o('Tumelo Kelesitse','t.kelesitse@email.bw','+267 71 101 1008','BW791219-1008','Plot 8801, Molapo West, Gaborone')],
            ['number'=>'MCR-A09','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Bogosi Mothibe','b.mothibe@gmail.com','+267 72 101 1009','BW850317-1009','Plot 6621, Glen Valley, Gaborone')],
            ['number'=>'MCR-A10','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Onkgopotse Kgosiemang','onk.kgosiemang@gmail.com','+267 71 101 1010','BW910722-1010','Plot 1441, Extension 14, Gaborone')],
            ['number'=>'MCR-B01','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Sethebe Modise','s.modise@hotmail.com','+267 72 101 1011','BW670301-1011','Plot 7111, Lentsweletau Road, Gaborone')],
            ['number'=>'MCR-B02','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'GYM_ACCESS','owner'=>$this->o('Itumeleng Mooketsi','i.mooketsi@gmail.com','+267 71 101 1012','BW800415-1012','Plot 4441, Woodhall, Gaborone')],
            ['number'=>'MCR-B03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8500,
             'owner'=>$this->o('Gaopalelwe Ntsepe','g.ntsepe@email.bw','+267 72 101 1013','BW730625-1013','Plot 8821, Kgale Siding, Gaborone'),
             'organizations'=>[$this->t('Kagiso Mmolotsi','kagiso.mm@gmail.com','+267 71 201 2001','BW920412-2001','2024-05-01','2025-04-30','2025-04-15',6500,'Left in good standing'),
                         $this->t('Faith Ntshingile','f.ntshingile@yahoo.com','+267 71 201 2051','BW900108-2051','2023-02-01','2024-04-30','2024-04-20',6000,'Left in good standing'),
                         $this->tc('Bontsi Seboni','bontsi.seboni@gmail.com','+267 71 201 2002','BW940818-2002','2025-05-01','2026-04-30',8500)]],
            ['number'=>'MCR-B04','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8500,
             'owner'=>$this->o('Dineo Gabatlhaolwe','d.gabatlhaolwe@gmail.com','+267 72 101 1014','BW760911-1014','Plot 1671, Airport Road, Gaborone'),
             'organizations'=>[$this->t('Moses Nkgau','m.nkgau@gmail.com','+267 71 201 2052','BW880322-2052','2023-07-01','2024-06-30','2024-06-25',6200,'Left in good standing'),
                         $this->t('Beauty Segaise','b.segaise@hotmail.com','+267 71 201 2053','BW910507-2053','2024-07-01','2025-05-31','2025-05-20',7000,'Lease not renewed'),
                         $this->tc('Neo Gabumetsang','neo.gab@gmail.com','+267 71 201 2003','BW960130-2003','2025-06-01','2026-05-31',8500)]],
            ['number'=>'MCR-B05','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>7,'rent_amount'=>8500,
             'owner'=>$this->o('Neo Maribe','n.maribe@email.bw','+267 72 101 1015','BW820714-1015','Plot 3381, Kgatleng, Botswana'),
             'organizations'=>[$this->t('Patrick Nhamo','p.nhamo@gmail.com','+267 71 201 2054','BW850614-2054','2022-11-01','2023-10-31','2023-10-28',5800,'Lease not renewed'),
                         $this->t('Cecilia Modimoeng','c.modimoeng@yahoo.com','+267 71 201 2055','BW890211-2055','2024-02-01','2025-01-31','2025-01-25',7200,'Left in good standing'),
                         $this->tc('Tshiamo Gaboitlhile','tshiamo.gab@gmail.com','+267 71 201 2004','BW970305-2004','2025-02-01','2026-01-31',8500)]],
            ['number'=>'MCR-B06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9000,
             'owner'=>$this->o('Tshiamo Moitoi','t.moitoi@gmail.com','+267 71 101 1016','BW880222-1016','Plot 5561, Oldimeng, Gaborone'),
             'organizations'=>[$this->t('Joy Osei-Bonsu','joy.osei@gmail.com','+267 71 201 2056','BW920915-2056','2023-08-01','2024-07-31','2024-07-22',7500,'Left in good standing'),
                         $this->t('Abraham Nyathi','a.nyathi@gmail.com','+267 71 201 2057','BW880404-2057','2024-08-01','2025-07-31','2025-07-15',8200,'Left in good standing'),
                         $this->tc('Moagi Letsholo','moagi.l@gmail.com','+267 71 201 2005','BW950620-2005','2025-08-01','2026-07-31',9000)]],
            ['number'=>'MCR-B07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8500,
             'owner'=>$this->o('Kefilwe Moshoeshoe','k.moshoeshoe@hotmail.com','+267 72 101 1017','BW710418-1017','Plot 2211, Phase 1, Gaborone'),
             'organizations'=>[$this->t('Hope Gaborone','hope.gab@gmail.com','+267 71 201 2058','BW910801-2058','2023-04-01','2024-03-31','2024-03-25',6800,'Left in good standing'),
                         $this->t('Sarah Mosimanegape','s.mosim@yahoo.com','+267 71 201 2059','BW870616-2059','2024-04-01','2025-03-31','2025-03-28',7800,'Lease not renewed'),
                         $this->tc('Kedidimetse Ratshosa','kedi.ratsho@gmail.com','+267 71 201 2006','BW980112-2006','2025-04-01','2026-03-31',8500)]],
            ['number'=>'MCR-B08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8000,
             'owner'=>$this->o('Goitsemodimo Setshogo','goit.setshogo@email.bw','+267 72 101 1018','BW650712-1018','Plot 9901, Phakalane, Gaborone'),
             'organizations'=>[$this->t('John Kebatho','j.kebatho@gmail.com','+267 71 201 2060','BW830927-2060','2022-06-01','2023-05-31','2023-05-28',5500,'Left in good standing'),
                         $this->t('Maria Botlhole','m.botlhole@yahoo.com','+267 71 201 2061','BW900318-2061','2023-06-01','2024-05-31','2024-05-20',7000,'Left in good standing'),
                         $this->tc('Thato Motsepe','thato.motsepe@gmail.com','+267 71 201 2007','BW960825-2007','2024-06-01','2025-05-31',8000)]],
            ['number'=>'MCR-B09','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8500,
             'owner'=>$this->o('Nametso Gaokgewe','n.gaokgewe@gmail.com','+267 71 101 1019','BW930105-1019','Plot 3301, Extension 11, Gaborone'),
             'organizations'=>[$this->t('Peter Gaokgewe','p.gaok@gmail.com','+267 71 201 2062','BW870211-2062','2023-01-01','2023-12-31','2023-12-28',6500,'Left in good standing'),
                         $this->t('Anna Sekwati','a.sekwati@hotmail.com','+267 71 201 2063','BW911105-2063','2024-01-01','2024-12-31','2024-12-25',7500,'Lease not renewed'),
                         $this->tc('Warona Kgosidintsi','warona.kg@gmail.com','+267 71 201 2008','BW990320-2008','2025-01-01','2025-12-31',8500)]],
            ['number'=>'MCR-B10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8500,
             'owner'=>$this->o('Lorato Seretse','lorato.seretse@gmail.com','+267 72 101 1020','BW840904-1020','Plot 7741, Lobatse Road, Gaborone'),
             'organizations'=>[$this->t('Michael Mosimane','m.mosimane@gmail.com','+267 71 201 2064','BW860123-2064','2023-03-01','2024-02-29','2024-02-25',6700,'Left in good standing'),
                         $this->t('Rose Kelepile','r.kelepile@yahoo.com','+267 71 201 2065','BW920810-2065','2024-03-01','2025-02-28','2025-02-22',7800,'Left in good standing'),
                         $this->tc('Ntombizodwa Dube','ntombi.dube@gmail.com','+267 71 201 2009','BW970611-2009','2025-03-01','2026-02-28',8500)]],
            ['number'=>'MCR-C01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9000,
             'owner'=>$this->o('Taolo Matlhako','t.matlhako@email.bw','+267 71 101 1021','BW720312-1021','Plot 4421, Northgate, Gaborone'),
             'organizations'=>[$this->t('David Keagile','d.keagile@gmail.com','+267 71 201 2066','BW841217-2066','2022-09-01','2023-08-31','2023-08-28',5800,'Left in good standing'),
                         $this->t('Linda Mokoena','l.mokoena@hotmail.com','+267 71 201 2067','BW900505-2067','2023-09-01','2024-08-31','2024-08-22',8000,'Lease not renewed'),
                         $this->tc('Mompati Kepadisa','mompati.k@gmail.com','+267 71 201 2010','BW980715-2010','2024-09-01','2025-08-31',9000)]],
            ['number'=>'MCR-C02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8000,
             'owner'=>$this->o('Reokeditse Lekgowe','r.lekgowe@gmail.com','+267 72 101 1022','BW811027-1022','Plot 2281, Gaborone West, Gaborone'),
             'organizations'=>[$this->t('Thomas Ramotshabi','t.ramotshabi@yahoo.com','+267 71 201 2068','BW870730-2068','2023-06-01','2024-05-31','2024-05-28',7000,'Left in good standing'),
                         $this->t('Precious Kenosi','p.kenosi@gmail.com','+267 71 201 2069','BW920416-2069','2024-06-01','2025-05-31','2025-05-20',7500,'Left in good standing'),
                         $this->tc('Gorata Sello','gorata.sello@gmail.com','+267 71 201 2011','BW960922-2011','2025-06-01','2026-05-31',8000)]],
            ['number'=>'MCR-C03','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Phenyo Botlhole','p.botlhole@gmail.com','+267 71 101 1023','BW790823-1023','Plot 6661, Extension 17, Gaborone')],
            ['number'=>'MCR-C04','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Modisa Mompati','m.mompati@email.bw','+267 72 101 1024','BW680507-1024','Plot 1121, Phase 4, Gaborone')],
            ['number'=>'MCR-C05','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Kabelo Motlhanke','k.motlhanke@gmail.com','+267 71 101 1025','BW920218-1025','Plot 5341, Broadhurst North, Gaborone')],
        ];
    }

    private function phakalaneGolfUnits(): array
    {
        return [
            ['number'=>'PGE-01','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Boitumelo Lefatshe','b.lefatshe@gmail.com','+267 72 102 1001','BW810616-1101','Plot 2541, Phakalane, Gaborone')],
            ['number'=>'PGE-02','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Gaositwe Phetwe','g.phetwe@email.bw','+267 71 102 1002','BW750924-1102','Plot 2542, Phakalane, Gaborone')],
            ['number'=>'PGE-03','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Motlalepula Keaboka','m.keaboka@hotmail.com','+267 72 102 1003','BW830101-1103','Plot 2543, Phakalane, Gaborone')],
            ['number'=>'PGE-04','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Kelapile Rankotso','k.rankotso@gmail.com','+267 71 102 1004','BW690718-1104','Plot 2544, Phakalane, Gaborone')],
            ['number'=>'PGE-05','occupancy'=>'owner_occupied','debtor'=>true,'debtor_from'=>5,'extra_ct'=>'POOL_ACCESS','owner'=>$this->o('David Patel','d.patel@gmail.com','+267 72 102 1005','ZA691015-1105','Plot 2545, Phakalane, Gaborone')],
            ['number'=>'PGE-06','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Dimakatso Sefhako','d.sefhako@yahoo.com','+267 71 102 1006','BW870302-1106','Plot 2546, Phakalane, Gaborone')],
            ['number'=>'PGE-07','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Segopotso Kelatlhegile','s.kelat@email.bw','+267 72 102 1007','BW940811-1107','Plot 2547, Phakalane, Gaborone')],
            ['number'=>'PGE-08','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'GYM_ACCESS','owner'=>$this->o('Banno Ramotshabi','b.ramotshabi@gmail.com','+267 71 102 1008','BW780506-1108','Plot 2548, Phakalane, Gaborone')],
            ['number'=>'PGE-09','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Thapelo Keboneilwe','t.kebon@gmail.com','+267 72 102 1009','BW860113-1109','Plot 2549, Phakalane, Gaborone')],
            ['number'=>'PGE-10','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Mmoloki Gaobonwe','m.gaob@email.bw','+267 71 102 1010','BW720428-1110','Plot 2550, Phakalane, Gaborone')],
            ['number'=>'PGE-11','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Otlile Kgosidintsi','o.kgosi@gmail.com','+267 72 102 1011','BW910317-1111','Plot 2551, Phakalane, Gaborone')],
            ['number'=>'PGE-12','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Pako Baitshepi','p.baitshepi@hotmail.com','+267 71 102 1012','BW841122-1112','Plot 2552, Phakalane, Gaborone')],
            ['number'=>'PGE-13','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Kabo Otlhogile','k.otlhogile@gmail.com','+267 72 102 1013','BW780914-1113','Plot 2553, Phakalane, Gaborone')],
            ['number'=>'PGE-14','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Sarah Mitchell','sarah.mitchell@gmail.com','+267 72 102 1014','UK801205-1114','Plot 2554, Phakalane, Gaborone')],
            ['number'=>'PGE-15','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Kelebogile Madimabe','k.madimabe@gmail.com','+267 71 102 1027','BW731115-1127','Plot 2555, Phakalane, Gaborone')],
            ['number'=>'PGE-16','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Oratile Lekorwe','o.lekorwe@email.bw','+267 71 102 1015','BW660831-1115','Plot 9901, Ledumang, Gaborone'),
             'organizations'=>[$this->t('Charles Nkgowe','c.nkgowe@gmail.com','+267 71 202 2015','BW880109-2015','2023-05-01','2024-04-30','2024-04-22',7800,'Left in good standing'),
                         $this->t('Ruth Keboletse','r.keboletse@yahoo.com','+267 71 202 2065','BW920614-2065','2024-05-01','2025-04-30','2025-04-18',8500,'Left in good standing'),
                         $this->tc('Batlang Gaotlhobogwe','batlang.gao@gmail.com','+267 71 202 2016','BW970422-2016','2025-05-01','2026-04-30',9200)]],
            ['number'=>'PGE-17','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9500,
             'owner'=>$this->o('Moagi Setlhogo','m.setlhogo@gmail.com','+267 72 102 1016','BW740217-1116','Plot 3371, Kgale, Gaborone'),
             'organizations'=>[$this->t('Andrew Moalosi','a.moalosi@gmail.com','+267 71 202 2066','BW830720-2066','2022-10-01','2023-09-30','2023-09-25',7000,'Left in good standing'),
                         $this->t('Lydia Kgosiemang','l.kgosi@hotmail.com','+267 71 202 2067','BW900115-2067','2023-10-01','2024-09-30','2024-09-22',8800,'Lease not renewed'),
                         $this->tc('Alice Osei','alice.osei@gmail.com','+267 71 202 2017','BW961210-2017','2024-10-01','2025-09-30',9500)]],
            ['number'=>'PGE-18','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Onneile Rasebotsa','o.rasebotsa@email.bw','+267 71 102 1017','BW850629-1117','Plot 5541, Gaborone North, Gaborone'),
             'organizations'=>[$this->t('Joseph Modise','j.modise@gmail.com','+267 71 202 2068','BW860304-2068','2023-07-01','2024-06-30','2024-06-20',8000,'Left in good standing'),
                         $this->t('Esther Gabaake','e.gabaake@yahoo.com','+267 71 202 2069','BW920918-2069','2024-07-01','2025-06-30','2025-06-18',9000,'Left in good standing'),
                         $this->tc('Samuel Mutua','samuel.mutua@gmail.com','+267 71 202 2018','BW950731-2018','2025-07-01','2026-06-30',9200)]],
            ['number'=>'PGE-19','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Keletso Gabankitse','k.gabankitse@gmail.com','+267 72 102 1018','BW810923-1118','Plot 7761, Oldimeng, Gaborone'),
             'organizations'=>[$this->t('Daniel Mosweu','d.mosweu@gmail.com','+267 71 202 2070','BW850512-2070','2023-02-01','2024-01-31','2024-01-28',7200,'Left in good standing'),
                         $this->t('Mary Setlhare','m.setlhare@hotmail.com','+267 71 202 2071','BW900807-2071','2024-02-01','2025-01-31','2025-01-25',8500,'Left in good standing'),
                         $this->tc('Grace Phiri','grace.phiri@gmail.com','+267 71 202 2019','BW971115-2019','2025-02-01','2026-01-31',9200)]],
            ['number'=>'PGE-20','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>8,'rent_amount'=>9200,
             'owner'=>$this->o('Molatelo Senwelo','m.senwelo@email.bw','+267 71 102 1019','BW720614-1119','Plot 4411, Bontleng, Gaborone'),
             'organizations'=>[$this->t('Samuel Gaolekwe','s.gaol@gmail.com','+267 71 202 2072','BW840319-2072','2022-12-01','2023-11-30','2023-11-25',7500,'Left in good standing'),
                         $this->t('Rachel Tlhomeso','r.tlhomeso@yahoo.com','+267 71 202 2073','BW910124-2073','2024-01-01','2024-12-31','2024-12-22',8800,'Lease not renewed'),
                         $this->tc('Neo Gabumetsang','neo.gabum@gmail.com','+267 71 202 2020','BW980228-2020','2025-01-01','2025-12-31',9200)]],
            ['number'=>'PGE-21','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9000,
             'owner'=>$this->o('Tsholofelo Rantong','t.rantong@gmail.com','+267 72 102 1020','BW780908-1120','Plot 6631, Block 8, Gaborone'),
             'organizations'=>[$this->t('James Segaetsho','j.segat@gmail.com','+267 71 202 2074','BW870416-2074','2023-04-01','2024-03-31','2024-03-25',7800,'Left in good standing'),
                         $this->t('Naomi Molefhe','n.molefhe@hotmail.com','+267 71 202 2075','BW920710-2075','2024-04-01','2025-03-31','2025-03-22',8500,'Left in good standing'),
                         $this->tc('Victor Kwakye','victor.kwakye@gmail.com','+267 71 202 2021','BW961003-2021','2025-04-01','2026-03-31',9000)]],
            ['number'=>'PGE-22','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Kagiso Seloilwe','k.seloilwe@email.bw','+267 71 102 1021','BW840106-1121','Plot 8881, Kgale View, Gaborone'),
             'organizations'=>[$this->t('Philip Kelesitse','p.keles@gmail.com','+267 71 202 2076','BW830821-2076','2023-08-01','2024-07-31','2024-07-25',8000,'Left in good standing'),
                         $this->t('Deborah Mothibe','d.mothibe@yahoo.com','+267 71 202 2077','BW900213-2077','2024-08-01','2025-07-31','2025-07-20',9000,'Left in good standing'),
                         $this->tc('Joy Banda','joy.banda@gmail.com','+267 71 202 2022','BW970518-2022','2025-08-01','2026-07-31',9200)]],
            ['number'=>'PGE-23','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>8800,
             'owner'=>$this->o('Ntombi Lebotse','n.lebotse@gmail.com','+267 72 102 1022','BW710519-1122','Plot 3341, Extension 3, Gaborone'),
             'organizations'=>[$this->t('Jonathan Kgosidintsi','j.kgosi@gmail.com','+267 71 202 2078','BW850925-2078','2023-01-01','2023-12-31','2023-12-28',7500,'Left in good standing'),
                         $this->t('Hannah Mooketsi','h.mooketsi@hotmail.com','+267 71 202 2079','BW910617-2079','2024-01-01','2024-12-31','2024-12-20',8200,'Lease not renewed'),
                         $this->tc('Abraham Molelekwa','ab.molel@gmail.com','+267 71 202 2023','BW980813-2023','2025-01-01','2025-12-31',8800)]],
            ['number'=>'PGE-24','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Boago Gabakgosi','b.gabakgosi@email.bw','+267 71 102 1023','BW820330-1123','Plot 5501, Gaborone Ext 12, Gaborone'),
             'organizations'=>[$this->t('Matthew Ntsepe','m.ntsepe@gmail.com','+267 71 202 2080','BW870112-2080','2023-06-01','2024-05-31','2024-05-28',8200,'Left in good standing'),
                         $this->t('Martha Gabatlhaolwe','m.gabt@yahoo.com','+267 71 202 2081','BW920408-2081','2024-06-01','2025-05-31','2025-05-22',8800,'Left in good standing'),
                         $this->tc('Hope Setlhare','hope.setlhare@gmail.com','+267 71 202 2024','BW961128-2024','2025-06-01','2026-05-31',9200)]],
            ['number'=>'PGE-25','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9000,
             'owner'=>$this->o('Obakeng Lekalake','o.lekalake@gmail.com','+267 72 102 1024','BW760714-1124','Plot 7721, Gaborone East, Gaborone'),
             'organizations'=>[$this->t('Luke Maribe','l.maribe@gmail.com','+267 71 202 2082','BW840529-2082','2022-08-01','2023-07-31','2023-07-28',7000,'Left in good standing'),
                         $this->t('Priscilla Moitoi','p.moitoi@hotmail.com','+267 71 202 2083','BW900922-2083','2023-08-01','2024-07-31','2024-07-22',8500,'Left in good standing'),
                         $this->tc('Emmanuel Mmoloki','emm.mmoloki@gmail.com','+267 71 202 2025','BW970315-2025','2024-08-01','2025-07-31',9000)]],
            ['number'=>'PGE-26','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9500,
             'owner'=>$this->o('Polelo Moatlhodi','p.moatlhodi@email.bw','+267 71 102 1025','BW690201-1125','Plot 9961, Phakalane Hills, Gaborone'),
             'organizations'=>[$this->t('Mark Moshoeshoe','m.moshoe@gmail.com','+267 71 202 2084','BW860315-2084','2023-03-01','2024-02-29','2024-02-25',8000,'Left in good standing'),
                         $this->t('Joanna Setshogo','j.setshogo@yahoo.com','+267 71 202 2085','BW911008-2085','2024-03-01','2025-02-28','2025-02-22',9000,'Left in good standing'),
                         $this->tc('Cecilia Modiseotsile','cecilia.mod@gmail.com','+267 71 202 2026','BW980612-2026','2025-03-01','2026-02-28',9500)]],
            ['number'=>'PGE-27','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>9200,
             'owner'=>$this->o('Rekgopotse Baipidi','r.baipidi@gmail.com','+267 72 102 1026','BW800417-1126','Plot 4401, Block 6, Gaborone'),
             'organizations'=>[$this->t('Timothy Gaokgewe','t.gaok@gmail.com','+267 71 202 2086','BW850706-2086','2023-09-01','2024-08-31','2024-08-25',8400,'Left in good standing'),
                         $this->t('Esther Seretse','e.seretse@hotmail.com','+267 71 202 2087','BW920201-2087','2024-09-01','2025-08-31','2025-08-20',9000,'Left in good standing'),
                         $this->tc('Patrick Nkgau','pat.nkgau@gmail.com','+267 71 202 2027','BW970829-2027','2025-09-01','2026-08-31',9200)]],
            ['number'=>'PGE-28','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Tiro Motshegwa','t.motshegwa@email.bw','+267 72 102 1028','BW880214-1128','Plot 2221, Francistown Road, Botswana')],
            ['number'=>'PGE-29','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Ditiro Kgarametsi','d.kgaram@gmail.com','+267 71 102 1029','BW790508-1129','Plot 5561, Serowe, Botswana')],
            ['number'=>'PGE-30','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Kagiso Raditladi','k.raditladi@yahoo.com','+267 72 102 1030','BW860919-1130','Plot 9901, Maun, Botswana')],
        ];
    }

    private function kgaleHillUnits(): array
    {
        $owners = [
            [$this->o('Kago Setshogo','k.setshogo@gmail.com','+267 71 103 1001','BW780301-1201','Lot 45891-01, Kgale Hill, Gaborone'), false, null],
            [$this->o('Lorato Seboko','l.seboko@email.bw','+267 72 103 1002','BW830615-1202','Lot 45891-02, Kgale Hill, Gaborone'), false, null],
            [$this->o('Mpho Mosimanegape','mpho.mosim@gmail.com','+267 71 103 1003','BW700920-1203','Lot 45891-03, Kgale Hill, Gaborone'), false, null],
            [$this->o('Robert Chen','robert.chen@gmail.com','+267 72 103 1004','HK801231-1204','Lot 45891-04, Kgale Hill, Gaborone'), false, null],
            [$this->o('Sethebe Modikwe','s.modikwe@hotmail.com','+267 71 103 1005','BW760418-1205','Lot 45891-05, Kgale Hill, Gaborone'), false, null],
            [$this->o('Neo Basinyi','n.basinyi@gmail.com','+267 72 103 1006','BW910722-1206','Lot 45891-06, Kgale Hill, Gaborone'), false, null],
            [$this->o('Bonolo Molefhe','bo.molefhe@email.bw','+267 71 103 1007','BW640115-1207','Lot 45891-07, Kgale Hill, Gaborone'), true, 4],
            [$this->o('Goitseone Boke','g.boke@gmail.com','+267 72 103 1008','BW850830-1208','Lot 45891-08, Kgale Hill, Gaborone'), false, null],
            [$this->o('Tshiamo Kepaletswe','t.kepaletswe@gmail.com','+267 71 103 1009','BW720407-1209','Lot 45891-09, Kgale Hill, Gaborone'), false, null],
            [$this->o('Moagi Segwabe','m.segwabe@yahoo.com','+267 72 103 1010','BW890614-1210','Lot 45891-10, Kgale Hill, Gaborone'), false, null],
            [$this->o('Phenyo Tlhomeso','p.tlhomeso@email.bw','+267 71 103 1011','BW740919-1211','Lot 45891-11, Kgale Hill, Gaborone'), false, null],
            [$this->o('James Mwangi','james.mwangi@gmail.com','+267 72 103 1012','KE820124-1212','Lot 45891-12, Kgale Hill, Gaborone'), true, 7],
            [$this->o('Kethabile Makori','k.makori@gmail.com','+267 71 103 1013','BW961108-1213','Lot 45891-13, Kgale Hill, Gaborone'), false, null],
            [$this->o('Batlang Mosetlhe','b.mosetlhe@gmail.com','+267 72 103 1014','BW810205-1214','Lot 45891-14, Kgale Hill, Gaborone'), false, null],
            [$this->o('Gaofenngwe Tau','g.tau@email.bw','+267 71 103 1015','BW770312-1215','Lot 45891-15, Kgale Hill, Gaborone'), false, null],
            [$this->o('Rona Ditshego','r.ditshego@hotmail.com','+267 72 103 1016','BW900817-1216','Lot 45891-16, Kgale Hill, Gaborone'), false, null],
            [$this->o('Pako Motlhajwe','p.motlhajwe@gmail.com','+267 71 103 1017','BW830611-1217','Lot 45891-17, Kgale Hill, Gaborone'), false, null],
            [$this->o('Kealeboga Selema','k.selema@email.bw','+267 72 103 1018','BW760928-1218','Lot 45891-18, Kgale Hill, Gaborone'), false, null],
        ];
        $units = [];
        foreach ($owners as $i => [$owner, $debtor, $debtorFrom]) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $units[] = ['number'=>"KHB-{$num}",'occupancy'=>'owner_occupied','debtor'=>$debtor,'debtor_from'=>$debtorFrom,'owner'=>$owner];
        }
        $units[] = ['number'=>'KHB-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Oarabile Mosetsi','o.mosetsi@gmail.com','+267 71 103 1019','BW910104-1219','Lot 45891-19, Kgale Hill, Gaborone')];
        $units[] = ['number'=>'KHB-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Priya Naidoo','priya.naidoo@gmail.com','+267 72 103 1020','ZA850515-1220','Lot 45891-20, Kgale Hill, Gaborone')];
        return $units;
    }

    private function masaCentreUnits(): array
    {
        $owners = [
            [$this->o('Mpho Tlhomeso','mpho.t@gmail.com','+267 71 104 1001','BW800312-1301','Plot 54360-01, Masa Centre, Gaborone'), true, 3],
            [$this->o('Otsile Moloisi','o.moloisi@email.bw','+267 72 104 1002','BW750624-1302','Plot 54360-02, Masa Centre, Gaborone'), false, null],
            [$this->o('Lorato Seretse','lor.seretse@gmail.com','+267 71 104 1003','BW840909-1303','Plot 54360-03, Masa Centre, Gaborone'), false, null],
            [$this->o('Bogosi Batshegi','b.batshegi@gmail.com','+267 72 104 1004','BW910215-1304','Plot 54360-04, Masa Centre, Gaborone'), false, null],
            [$this->o('Kefilwe Moshoeshoe','kef.mosh@gmail.com','+267 71 104 1005','BW680720-1305','Plot 54360-05, Masa Centre, Gaborone'), false, null],
            [$this->o('Kgomotso Tlokweng','kgom.tlo@gmail.com','+267 72 104 1006','BW870316-1306','Plot 54360-06, Masa Centre, Gaborone'), false, null],
            [$this->o('Nametso Gaokgewe','na.gaok@email.bw','+267 71 104 1007','BW730511-1307','Plot 54360-07, Masa Centre, Gaborone'), false, null],
            [$this->o('Tumelo Kelesitse','tum.kel@gmail.com','+267 72 104 1008','BW820218-1308','Plot 54360-08, Masa Centre, Gaborone'), false, null],
            [$this->o('Phenyo Botlhole','phe.bot@gmail.com','+267 71 104 1009','BW900807-1309','Plot 54360-09, Masa Centre, Gaborone'), false, null],
            [$this->o('Itumeleng Mosimane','i.mosimane@gmail.com','+267 72 104 1010','BW770913-1310','Plot 54360-10, Masa Centre, Gaborone'), false, null],
            [$this->o('Modisa Mompati','mod.momp@email.bw','+267 71 104 1011','BW641228-1311','Plot 54360-11, Masa Centre, Gaborone'), false, null],
            [$this->o('Kabelo Motlhanke','kab.mot@gmail.com','+267 72 104 1012','BW920104-1312','Plot 54360-12, Masa Centre, Gaborone'), false, null],
            [$this->o('Onkgopotse Kgosi','onk.kgosi@gmail.com','+267 71 104 1013','BW780601-1313','Plot 54360-13, Masa Centre, Gaborone'), false, null],
            [$this->o('Boitumelo Lefatshe','boi.lef@email.bw','+267 72 104 1014','BW850714-1314','Plot 54360-14, Masa Centre, Gaborone'), false, null],
            [$this->o('Rekgopotse Baipidi','rek.baip@gmail.com','+267 71 104 1015','BW710822-1315','Plot 54360-15, Masa Centre, Gaborone'), false, null],
            [$this->o('Grace Osei','grace.osei@gmail.com','+267 72 104 1016','GH860317-1316','Plot 54360-16, Masa Centre, Gaborone'), false, null],
            [$this->o('Goitseone Segaetsho','goit.segaet@gmail.com','+267 71 104 1017','BW730429-1317','Plot 54360-17, Masa Centre, Gaborone'), true, 6],
            [$this->o('Lesego Gaolekwe','les.gaol@email.bw','+267 72 104 1018','BW880111-1318','Plot 54360-18, Masa Centre, Gaborone'), false, null],
            [$this->o('Reokeditse Lekgowe','reo.lekgowe@gmail.com','+267 71 104 1019','BW760825-1319','Plot 54360-19, Masa Centre, Gaborone'), false, null],
            [$this->o('Dineo Gabatlhaolwe','din.gab@gmail.com','+267 72 104 1020','BW930516-1320','Plot 54360-20, Masa Centre, Gaborone'), false, null],
            [$this->o('Taolo Matlhako','ta.matl@gmail.com','+267 71 104 1021','BW800613-1321','Plot 54360-21, Masa Centre, Gaborone'), false, null],
            [$this->o('Segopotso Kelatlhegile','s.kelat2@email.bw','+267 72 104 1022','BW750218-1322','Plot 54360-22, Masa Centre, Gaborone'), false, null],
            [$this->o('Emmanuel Banda','emm.banda@gmail.com','+267 71 104 1023','ZM900404-1323','Plot 54360-23, Masa Centre, Gaborone'), false, null],
        ];
        $units = [];
        foreach ($owners as $i => [$owner, $debtor, $debtorFrom]) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $units[] = ['number'=>"MSC-{$num}",'occupancy'=>'owner_occupied','debtor'=>$debtor,'debtor_from'=>$debtorFrom,'owner'=>$owner];
        }
        $units[] = ['number'=>'MSC-24','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Kelapile Ntlo','kel.ntlo@gmail.com','+267 72 104 1024','BW840928-1324','Plot 54360-24, Masa Centre, Gaborone')];
        $units[] = ['number'=>'MSC-25','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Ditiro Kgarametsi','dit.kgaram@email.bw','+267 71 104 1025','BW910307-1325','Plot 54360-25, Masa Centre, Gaborone')];
        return $units;
    }

    private function extension15Units(): array
    {
        return [
            ['number'=>'EXT15-01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Kelebogile Molosiwa','k.molosiwa@email.bw','+267 71 105 1001','BW820714-1401','Plot 15-01, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Kago Ramotse','kago.ram@gmail.com','+267 71 205 3001','BW880315-3001','2022-07-01','2023-06-30','2023-06-25',6000,'Left in good standing'),
                         $this->t('Boitumelo Mokone','boit.mok@gmail.com','+267 71 205 3002','BW910820-3002','2023-07-01','2024-04-30','2024-04-22',6800,'Lease not renewed'),
                         $this->tc('Kagiso Ramothwa','kagiso.r@gmail.com','+267 71 205 2001','BW940512-2101','2024-05-01','2025-04-30',7500)]],
            ['number'=>'EXT15-02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Ntombi Sebolai','n.sebolai@gmail.com','+267 72 105 1002','BW760921-1402','Plot 15-02, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Tshiamo Lekgwathi','tshiamo.lek@gmail.com','+267 71 205 3003','BW860510-3003','2022-08-01','2023-07-31','2023-07-28',5800,'Left in good standing'),
                         $this->t('Kefilwe Gabanakgosi','kefilwe.gab@yahoo.com','+267 71 205 3004','BW900225-3004','2023-08-01','2024-05-31','2024-05-20',6500,'Left in good standing'),
                         $this->tc('Bontsi Moalosi','bontsi.m@gmail.com','+267 71 205 2002','BW960818-2102','2024-06-01','2025-05-31',7500)]],
            ['number'=>'EXT15-03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Otsile Gaokgatlhe','o.gaok@email.bw','+267 71 105 1003','BW840208-1403','Plot 15-03, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Oteng Ralekgokgo','oteng.ral@gmail.com','+267 71 205 3005','BW870918-3005','2022-09-01','2023-08-31','2023-08-28',6200,'Left in good standing'),
                         $this->t('Modise Keoreng','modise.k@hotmail.com','+267 71 205 3006','BW920103-3006','2023-09-01','2024-06-30','2024-06-22',6800,'Lease not renewed'),
                         $this->tc('Neo Raditlhoko','neo.radi@gmail.com','+267 71 205 2003','BW980330-2103','2024-07-01','2025-06-30',7500)]],
            ['number'=>'EXT15-04','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7800,
             'owner'=>$this->o('Bonolo Segathe','b.segathe@gmail.com','+267 72 105 1004','BW910615-1404','Plot 15-04, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Sethunya Moetse','sethunya.m@gmail.com','+267 71 205 3007','BW850422-3007','2022-10-01','2023-09-30','2023-09-25',5800,'Left in good standing'),
                         $this->t('Ditiro Kgang','ditiro.kg@yahoo.com','+267 71 205 3008','BW890711-3008','2023-10-01','2024-07-31','2024-07-28',6500,'Left in good standing'),
                         $this->tc('Mpho Sekgwa','mpho.sekgwa@gmail.com','+267 71 205 2004','BW950117-2104','2024-08-01','2025-07-31',7800)]],
            ['number'=>'EXT15-05','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Lesego Rantsimako','l.rantsimako@email.bw','+267 71 105 1005','BW780312-1405','Plot 15-05, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Motlhabani Segokgo','motlh.seg@gmail.com','+267 71 205 3009','BW840116-3009','2022-11-01','2023-10-31','2023-10-28',5500,'Left in good standing'),
                         $this->t('Kabelo Ramotswa','kab.ram@hotmail.com','+267 71 205 3010','BW900629-3010','2023-11-01','2024-08-31','2024-08-25',6800,'Lease not renewed'),
                         $this->tc('Tebogo Mogatusi','tebogo.m@gmail.com','+267 71 205 2005','BW921025-2105','2024-09-01','2025-08-31',7500)]],
            ['number'=>'EXT15-06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Taolo Mosarwa','t.mosarwa@gmail.com','+267 72 105 1006','BW650824-1406','Plot 15-06, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Gape Mogapi','gape.mog@gmail.com','+267 71 205 3011','BW860805-3011','2022-12-01','2023-11-30','2023-11-25',5800,'Left in good standing'),
                         $this->t('Phenyo Letshwenyo','phenyo.let@yahoo.com','+267 71 205 3012','BW910312-3012','2023-12-01','2024-09-30','2024-09-22',6500,'Left in good standing'),
                         $this->tc('Lorato Baele','lorato.b@gmail.com','+267 71 205 2006','BW960602-2106','2024-10-01','2025-09-30',7500)]],
            ['number'=>'EXT15-07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Segomotso Dikobe','s.dikobe@email.bw','+267 71 105 1007','BW720119-1407','Plot 15-07, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Ontibile Kebaitse','ontibile.k@gmail.com','+267 71 205 3013','BW830620-3013','2023-01-01','2023-12-31','2023-12-28',6000,'Left in good standing'),
                         $this->t('Boago Dintwe','boago.d@hotmail.com','+267 71 205 3014','BW880214-3014','2024-01-01','2024-10-31','2024-10-25',6800,'Lease not renewed'),
                         $this->tc('Keane Modise','keane.m@gmail.com','+267 71 205 2007','BW990314-2107','2024-11-01','2025-10-31',7500)]],
            ['number'=>'EXT15-08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Bathusi Makwati','b.makwati@gmail.com','+267 72 105 1008','BW880413-1408','Plot 15-08, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Amantle Mokgosi','amantle.m@gmail.com','+267 71 205 3015','BW850927-3015','2023-02-01','2024-01-31','2024-01-28',5800,'Left in good standing'),
                         $this->t('Reatlegile Bolokwe','reatl.b@yahoo.com','+267 71 205 3016','BW910518-3016','2024-02-01','2024-11-30','2024-11-22',6500,'Left in good standing'),
                         $this->tc('Onalenna Sethibe','onalenna.s@gmail.com','+267 71 205 2008','BW980706-2108','2024-12-01','2025-11-30',7500)]],
            ['number'=>'EXT15-09','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Mpho Lenyeletse','m.lenyeletse@email.bw','+267 71 105 1009','BW760807-1409','Plot 15-09, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Warona Moatlhodi','warona.m@gmail.com','+267 71 205 3017','BW840303-3017','2023-03-01','2024-02-29','2024-02-25',6200,'Left in good standing'),
                         $this->t('Keletso Sebina','keletso.s@hotmail.com','+267 71 205 3018','BW890810-3018','2024-03-01','2024-12-31','2024-12-25',6800,'Lease not renewed'),
                         $this->tc('Gorata Segwapetse','gorata.seg@gmail.com','+267 71 205 2009','BW970521-2109','2025-01-01','2025-12-31',7500)]],
            ['number'=>'EXT15-10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Rona Lephete','r.lephete@gmail.com','+267 72 105 1010','BW810330-1410','Plot 15-10, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Koketso Gabana','koketso.g@gmail.com','+267 71 205 3019','BW860710-3019','2023-04-01','2024-03-31','2024-03-25',6000,'Left in good standing'),
                         $this->t('Tshepo Letsholo','tshepo.l@yahoo.com','+267 71 205 3020','BW920115-3020','2024-04-01','2025-01-31','2025-01-25',6800,'Left in good standing'),
                         $this->tc('Phenyo Rakakane','phenyo.r@gmail.com','+267 71 205 2010','BW960812-2110','2025-02-01','2026-01-31',7500)]],
            ['number'=>'EXT15-11','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>5,'rent_amount'=>7500,
             'owner'=>$this->o('Kgosietsile Ntuane','k.ntuane@email.bw','+267 71 105 1011','BW730518-1411','Plot 15-11, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Gaone Molatlhegi','gaone.mol@gmail.com','+267 71 205 3021','BW850422-3021','2022-06-01','2023-05-31','2023-05-28',5500,'Left in good standing'),
                         $this->t('Modiri Tlhagale','modiri.t@hotmail.com','+267 71 205 3022','BW900817-3022','2023-06-01','2024-03-31','2024-03-25',6500,'Lease not renewed'),
                         $this->tc('Mompati Kepadisa','mom.kep@gmail.com','+267 71 205 2011','BW951108-2111','2024-04-01','2025-03-31',7500)]],
            ['number'=>'EXT15-12','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Tshiamo Gabusa','t.gabusa@gmail.com','+267 72 105 1012','BW860921-1412','Plot 15-12, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Oaitse Mogorosi','oaitse.m@gmail.com','+267 71 205 3023','BW830128-3023','2023-05-01','2024-04-30','2024-04-22',6000,'Left in good standing'),
                         $this->t('Rebaone Kgaswane','rebaone.k@yahoo.com','+267 71 205 3024','BW880603-3024','2024-05-01','2024-12-31','2024-12-28',6800,'Left in good standing'),
                         $this->tc('Ditiro Segolame','dit.seg@gmail.com','+267 71 205 2012','BW980225-2112','2025-01-01','2025-12-31',7500)]],
            ['number'=>'EXT15-13','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7800,
             'owner'=>$this->o('Boitshepo Tsheko','b.tsheko@email.bw','+267 71 105 1013','BW790614-1413','Plot 15-13, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Tumisang Montshiwa','tumisang.m@gmail.com','+267 71 205 3025','BW850914-3025','2023-06-01','2024-05-31','2024-05-25',6200,'Left in good standing'),
                         $this->t('Kagiso Tshipe','kagiso.t@hotmail.com','+267 71 205 3026','BW900120-3026','2024-06-01','2025-01-31','2025-01-28',7000,'Lease not renewed'),
                         $this->tc('Naledi Modisa','naledi.mod@gmail.com','+267 71 205 2013','BW960417-2113','2025-02-01','2026-01-31',7800)]],
            ['number'=>'EXT15-14','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Keaboka Ditlhago','k.ditlhago@gmail.com','+267 72 105 1014','BW820903-1414','Plot 15-14, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Gomolemo Sebonego','gomolemo.s@gmail.com','+267 71 205 3027','BW840709-3027','2022-05-01','2023-04-30','2023-04-25',5500,'Left in good standing'),
                         $this->t('Keneilwe Gaolape','keneilwe.g@yahoo.com','+267 71 205 3028','BW891218-3028','2023-05-01','2024-02-28','2024-02-22',6500,'Left in good standing'),
                         $this->tc('Gaone Sebotho','gaone.seb@gmail.com','+267 71 205 2014','BW941203-2114','2024-03-01','2025-02-28',7500)]],
            ['number'=>'EXT15-15','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Refilwe Gabaake','ref.gab@email.bw','+267 71 105 1015','BW710227-1415','Plot 15-15, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Motheo Gaothobogwe','motheo.g@gmail.com','+267 71 205 3029','BW860523-3029','2023-07-01','2024-06-30','2024-06-25',6000,'Left in good standing'),
                         $this->t('Lefika Moumakwa','lefika.m@hotmail.com','+267 71 205 3030','BW910408-3030','2024-07-01','2025-02-28','2025-02-22',7000,'Lease not renewed'),
                         $this->tc('Keorapetse Morupisi','keor.mor@gmail.com','+267 71 205 2015','BW980905-2115','2025-03-01','2026-02-28',7500)]],
            ['number'=>'EXT15-16','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>7,'rent_amount'=>7500,
             'owner'=>$this->o('Tumelo Leotwane','t.leotwane@gmail.com','+267 72 105 1016','BW850710-1416','Plot 15-16, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Bakang Masire','bakang.m@gmail.com','+267 71 205 3031','BW830816-3031','2022-08-01','2023-07-31','2023-07-28',5800,'Left in good standing'),
                         $this->t('Tebogo Gabatlhaolwe','tebogo.gab@yahoo.com','+267 71 205 3032','BW880202-3032','2023-08-01','2024-07-31','2024-07-22',6500,'Left in good standing'),
                         $this->tc('Alice Osei','alice.osei2@gmail.com','+267 71 205 2016','BW951110-2116','2024-08-01','2025-07-31',7500)]],
            ['number'=>'EXT15-17','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7500,
             'owner'=>$this->o('Gaone Molefe','gaone.molefe@email.bw','+267 71 105 1017','BW680319-1417','Plot 15-17, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Ofentse Kealeboga','ofentse.k@gmail.com','+267 71 205 3033','BW850115-3033','2023-08-01','2024-07-31','2024-07-25',6200,'Left in good standing'),
                         $this->t('Kagiso Mothibi','kagiso.moth@hotmail.com','+267 71 205 3034','BW900520-3034','2024-08-01','2024-12-31','2024-12-28',6800,'Lease not renewed'),
                         $this->tc('Onthatile Sereto','onth.sereto@gmail.com','+267 71 205 2017','BW970630-2117','2025-01-01','2025-12-31',7500)]],
            ['number'=>'EXT15-18','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>7800,
             'owner'=>$this->o('Lebang Goitsemelwe','l.goitsemelwe@gmail.com','+267 72 105 1018','BW790812-1418','Plot 15-18, Ext 15, Gaborone'),
             'organizations'=>[$this->t('Tsholofelo Lekoko','tsholofelo.l@gmail.com','+267 71 205 3035','BW840928-3035','2023-04-01','2024-03-31','2024-03-25',6000,'Left in good standing'),
                         $this->t('Ontiretse Mokgware','ontiretse.m@yahoo.com','+267 71 205 3036','BW891107-3036','2024-04-01','2025-01-31','2025-01-25',7000,'Left in good standing'),
                         $this->tc('Setlhare Gaborone','setl.gab@gmail.com','+267 71 205 2018','BW990124-2118','2025-02-01','2026-01-31',7800)]],
            ['number'=>'EXT15-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Polelo Kobedi','p.kobedi@email.bw','+267 71 105 1019','BW821026-1419','Plot 15-19, Ext 15, Gaborone')],
            ['number'=>'EXT15-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Kago Morakaladi','k.morakaladi@gmail.com','+267 72 105 1020','BW900314-1420','Plot 15-20, Ext 15, Gaborone')],
        ];
    }

    private function fairgroundsUnits(): array
    {
        return [
            ['number'=>'FBP-01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('Botswana Life Holdings Ltd','botswana.life@bw.com','+267 31 881 1001','BW-CORP-5001','Plot 6940-01, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Debswana Mining Co','info@debswana.bw','+267 31 880 4001','BW-CORP-7001','2022-01-01','2023-12-31','2023-12-22',14000,'Lease expired'),
                         $this->t('Mascom Wireless Pvt','ops@mascom.bw','+267 31 880 4002','BW-CORP-7002','2024-01-01','2024-12-31','2024-12-18',16000,'Lease not renewed'),
                         $this->tc('Botswana Life Insurance','claims@botswanalife.bw','+267 31 880 3001','BW-CORP-6001','2025-01-01','2027-12-31',18000)]],
            ['number'=>'FBP-02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Standard Chartered BW Ltd','scb.gaborone@sc.com','+267 31 881 1002','BW-CORP-5002','Plot 6940-02, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Stanbic Bank Botswana','stanbic.ops@stanbic.bw','+267 31 880 4003','BW-CORP-7003','2022-03-01','2024-02-29','2024-02-22',16000,'Relocated'),
                         $this->t('BBS Limited BW','admin@bbs.co.bw','+267 31 880 4004','BW-CORP-7004','2024-03-01','2024-12-31','2024-12-20',19000,'Lease expired'),
                         $this->tc('Standard Chartered Bank','business@scbank.bw','+267 31 880 3002','BW-CORP-6002','2025-01-01','2027-12-31',22000)]],
            ['number'=>'FBP-03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('Engen Botswana Pty Ltd','engen.bw@engen.co.za','+267 31 881 1003','BW-CORP-5003','Plot 6940-03, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Orange Botswana Pty','orange.ops@orange.bw','+267 31 880 4005','BW-CORP-7005','2022-06-01','2024-05-31','2024-05-25',14500,'Left in good standing'),
                         $this->t('Cresta Hotels Group','admin@crestahotels.bw','+267 31 880 4006','BW-CORP-7006','2024-06-01','2025-01-31','2025-01-25',16000,'Lease not renewed'),
                         $this->tc('Engen Petroleum','gaborone@engen.bw','+267 31 880 3003','BW-CORP-6003','2025-02-01','2028-01-31',18000)]],
            ['number'=>'FBP-04','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>5,'rent_amount'=>20000,
             'owner'=>$this->o('Choppies Enterprises Ltd','property@choppies.com','+267 31 881 1004','BW-CORP-5004','Plot 6940-04, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Motor Centre Botswana','motorcentre@mc.bw','+267 31 880 4007','BW-CORP-7007','2022-04-01','2023-03-31','2023-03-28',15000,'Lease expired'),
                         $this->t('Furnmart Holdings BW','admin@furnmart.bw','+267 31 880 4008','BW-CORP-7008','2023-04-01','2024-12-31','2024-12-20',17000,'Lease not renewed'),
                         $this->tc('Choppies Supermarket','ops.fairgrounds@choppies.bw','+267 31 880 3004','BW-CORP-6004','2025-02-01','2027-01-31',20000)]],
            ['number'=>'FBP-05','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('First National Bank BW','fnb.property@fnb.co.za','+267 31 881 1005','BW-CORP-5005','Plot 6940-05, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Riverwalk Properties','info@riverwalkprop.bw','+267 31 880 4009','BW-CORP-7009','2022-09-01','2024-08-31','2024-08-25',14000,'Left in good standing'),
                         $this->t('Gaborone Sun Hotel','admin@gabsun.bw','+267 31 880 4010','BW-CORP-7010','2024-09-01','2025-02-28','2025-02-22',16500,'Lease not renewed'),
                         $this->tc('FNB Botswana','gaborone@fnbbw.com','+267 31 880 3005','BW-CORP-6005','2025-03-01','2028-02-28',18000)]],
            ['number'=>'FBP-06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18500,
             'owner'=>$this->o('BancABC Holdings Ltd','bancabc.prop@bancabc.bw','+267 31 881 1006','BW-CORP-5006','Plot 6940-06, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Pula Holdings Pvt','info@pulaholdings.bw','+267 31 880 4011','BW-CORP-7011','2022-05-01','2023-04-30','2023-04-25',13000,'Left in good standing'),
                         $this->t('KBL Bottling Company','admin@kblbottling.bw','+267 31 880 4012','BW-CORP-7012','2023-05-01','2025-02-28','2025-02-22',16000,'Lease expired'),
                         $this->tc('BancABC Gaborone','info@bancabc.bw','+267 31 880 3006','BW-CORP-6006','2025-03-01','2027-02-28',18500)]],
            ['number'=>'FBP-07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>16000,
             'owner'=>$this->o('Payless Group Ltd','payless.prop@payless.bw','+267 31 881 1007','BW-CORP-5007','Plot 6940-07, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Air Botswana Pvt Ltd','admin@airbotswana.bw','+267 31 880 4013','BW-CORP-7013','2022-02-01','2024-01-31','2024-01-25',12000,'Relocated'),
                         $this->t('Wilderness Safaris BW','ops@wilderness.bw','+267 31 880 4014','BW-CORP-7014','2024-02-01','2025-03-31','2025-03-25',14000,'Lease not renewed'),
                         $this->tc('Payless Pharmaceuticals','pharm@payless.bw','+267 31 880 3007','BW-CORP-6007','2025-04-01','2028-03-31',16000)]],
            ['number'=>'FBP-08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Kgabo IT Solutions Pty','kgabo@kgabotech.bw','+267 71 881 1008','BW-CORP-5008','Plot 6940-08, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Game Stores Botswana','admin@gamestores.bw','+267 31 880 4015','BW-CORP-7015','2022-07-01','2023-06-30','2023-06-25',11000,'Left in good standing'),
                         $this->t('Turn n Tender BW','ops@turnntender.bw','+267 71 880 4016','BW-CORP-7016','2023-07-01','2025-03-31','2025-03-22',13000,'Lease expired'),
                         $this->tc('Kgabo IT Solutions','admin@kgabotech.bw','+267 71 880 3008','BW-CORP-6008','2025-04-01','2027-03-31',15000)]],
            ['number'=>'FBP-09','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>8,'rent_amount'=>20000,
             'owner'=>$this->o('Sefalana Holdings Ltd','sefalana.prop@sefal.bw','+267 31 881 1009','BW-CORP-5009','Plot 6940-09, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Nandos Botswana Ltd','admin@nandos.bw','+267 31 880 4017','BW-CORP-7017','2022-08-01','2024-07-31','2024-07-25',14000,'Left in good standing'),
                         $this->t('Woolworths BW Pvt','ops@woolworths.bw','+267 31 880 4018','BW-CORP-7018','2024-08-01','2025-03-31','2025-03-22',17000,'Lease not renewed'),
                         $this->tc('Sefalana Holdings','ops@sefalana.bw','+267 31 880 3009','BW-CORP-6009','2025-04-01','2027-03-31',20000)]],
            ['number'=>'FBP-10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('Letshego Financial Ltd','letshego.prop@letsf.bw','+267 31 881 1010','BW-CORP-5010','Plot 6940-10, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Spar Botswana Ltd','admin@sparbw.com','+267 31 880 4019','BW-CORP-7019','2022-10-01','2024-09-30','2024-09-25',14000,'Left in good standing'),
                         $this->t('Capital Management BW','ops@capman.bw','+267 31 880 4020','BW-CORP-7020','2024-10-01','2025-04-30','2025-04-22',16000,'Lease expired'),
                         $this->tc('Letshego Financial','gaborone@letshego.bw','+267 31 880 3010','BW-CORP-6010','2025-05-01','2028-04-30',18000)]],
            ['number'=>'FBP-11','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Barclays BW Pty Ltd','barclays.bw@barclays.com','+267 31 881 1011','BW-CORP-5011','Plot 6940-11, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('BW Power Corporation','admin@bpc.bw','+267 31 880 4021','BW-CORP-7021','2022-06-01','2024-05-31','2024-05-25',16000,'Left in good standing'),
                         $this->t('Water Utilities Corp','ops@wuc.bw','+267 31 880 4022','BW-CORP-7022','2024-06-01','2025-04-30','2025-04-20',19000,'Lease not renewed'),
                         $this->tc('Barclays Botswana','gaborone@barclaysbw.com','+267 31 880 3011','BW-CORP-6011','2025-05-01','2028-04-30',22000)]],
            ['number'=>'FBP-12','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>16000,
             'owner'=>$this->o('Africa Financial Svcs','afs.prop@afsbw.com','+267 71 881 1012','BW-CORP-5012','Plot 6940-12, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('BAMB Holdings Ltd','admin@bamb.bw','+267 31 880 4023','BW-CORP-7023','2022-11-01','2024-10-31','2024-10-25',12000,'Left in good standing'),
                         $this->t('BW Meat Commission','ops@bmc.bw','+267 31 880 4024','BW-CORP-7024','2024-11-01','2025-05-31','2025-05-20',14000,'Lease expired'),
                         $this->tc('Africa Financial Services','info@afsbw.com','+267 71 880 3012','BW-CORP-6012','2025-06-01','2028-05-31',16000)]],
            ['number'=>'FBP-13','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('Diamond Trading Co BW','dtc.prop@dtcbw.com','+267 31 881 1013','BW-CORP-5013','Plot 6940-13, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('National DB Botswana','admin@ndb.bw','+267 31 880 4025','BW-CORP-7025','2022-04-01','2024-03-31','2024-03-25',14000,'Left in good standing'),
                         $this->t('Letlole La Rona Pvt','ops@letlole.bw','+267 31 880 4026','BW-CORP-7026','2024-04-01','2025-05-31','2025-05-22',16000,'Lease not renewed'),
                         $this->tc('Diamond Trading Company','gaborone@dtcbw.com','+267 31 880 3013','BW-CORP-6013','2025-06-01','2028-05-31',18000)]],
            ['number'=>'FBP-14','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Lentswe Properties Ltd','lentswe@lentsweprop.bw','+267 71 881 1014','BW-CORP-5014','Plot 6940-14, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('G4S Botswana Pvt','admin@g4s.bw','+267 31 880 4027','BW-CORP-7027','2022-12-01','2024-11-30','2024-11-25',12000,'Relocated'),
                         $this->t('BotswanaPost Holdings','ops@botswanapost.bw','+267 31 880 4028','BW-CORP-7028','2024-12-01','2025-06-30','2025-06-22',13500,'Lease expired'),
                         $this->tc('Lentswe Properties','admin@lentsweprop.bw','+267 71 880 3014','BW-CORP-6014','2025-07-01','2028-06-30',15000)]],
            ['number'=>'FBP-15','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>20000,
             'owner'=>$this->o('BW Telecom Holdings','bwtelecom.prop@btc.bw','+267 31 881 1015','BW-CORP-5015','Plot 6940-15, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Chobe Safari Lodge','admin@chobesafari.bw','+267 31 880 4029','BW-CORP-7029','2022-07-01','2024-06-30','2024-06-25',15000,'Left in good standing'),
                         $this->t('Tlotlo Hotel Group','ops@tlotlo.bw','+267 31 880 4030','BW-CORP-7030','2024-07-01','2025-06-30','2025-06-20',17500,'Lease not renewed'),
                         $this->tc('Botswana Telecommunications','corp@btc.bw','+267 31 880 3015','BW-CORP-6015','2025-07-01','2028-06-30',20000)]],
            ['number'=>'FBP-16','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>18000,
             'owner'=>$this->o('Botswana Savings Bank','bsb.prop@bsb.co.bw','+267 31 881 1016','BW-CORP-5016','Plot 6940-16, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Broadhurst Invest Ltd','admin@broadhurst.bw','+267 31 880 4031','BW-CORP-7031','2022-09-01','2024-08-31','2024-08-22',14000,'Left in good standing'),
                         $this->t('Notwane Holdings','ops@notwane.bw','+267 31 880 4032','BW-CORP-7032','2024-09-01','2025-07-31','2025-07-25',16000,'Lease expired'),
                         $this->tc('Botswana Savings Bank','gaborone@bsb.co.bw','+267 31 880 3016','BW-CORP-6016','2025-08-01','2028-07-31',18000)]],
            ['number'=>'FBP-17','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>16000,
             'owner'=>$this->o('Kago Investments Ltd','kago@kagoinvest.bw','+267 31 881 1017','BW-CORP-5017','Plot 6940-17, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('Gaborone Glass Ind','admin@gabglass.bw','+267 31 880 4033','BW-CORP-7033','2022-05-01','2024-04-30','2024-04-22',12000,'Left in good standing'),
                         $this->t('Kalahari Breweries','ops@kalabrew.bw','+267 31 880 4034','BW-CORP-7034','2024-05-01','2025-04-30','2025-04-20',14000,'Lease not renewed'),
                         $this->tc('Kago Tech Solutions','admin@kagotech.bw','+267 31 880 3017','BW-CORP-6017','2025-05-01','2027-04-30',16000)]],
            ['number'=>'FBP-18','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Moagi Holdings Pty Ltd','moagi@moagihold.bw','+267 31 881 1018','BW-CORP-5018','Plot 6940-18, Fairgrounds, Gaborone'),
             'organizations'=>[$this->t('BW Insurance Holdings','admin@bwinsure.bw','+267 31 880 4035','BW-CORP-7035','2022-08-01','2024-07-31','2024-07-25',11000,'Left in good standing'),
                         $this->t('Motovac Botswana','ops@motovac.bw','+267 31 880 4036','BW-CORP-7036','2024-08-01','2025-03-31','2025-03-22',13000,'Lease expired'),
                         $this->tc('Moagi Consulting Pvt','admin@moagiconsult.bw','+267 31 880 3018','BW-CORP-6018','2025-04-01','2027-03-31',15000)]],
            ['number'=>'FBP-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Phenyo Property Group','phenyo@phenprop.bw','+267 31 881 1019','BW-CORP-5019','Plot 6940-19, Fairgrounds, Gaborone')],
            ['number'=>'FBP-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Gaone Real Community Ltd','gaone@gaonere.bw','+267 31 881 1020','BW-CORP-5020','Plot 6940-20, Fairgrounds, Gaborone')],
        ];
    }

    /* ================================================================== */
    /*  SOUTH AFRICA COMMUNITY DEFINITIONS                                     */
    /* ================================================================== */

    private function southAfricaCommunityDefinitions(): array
    {
        return [
            ['name'=>'Menlyn Maine Lifestyle Community','type'=>'body_corporate','address'=>'Menlyn Maine, cnr Aramand & Corobay Avenue, Waterkloof Glen, Pretoria, 0181',
             'admin_fund_amount'=>3800.00,'reserve_fund_amount'=>1140.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-07-02 09:00:00','start_period'=>0,'units'=>$this->menlynMaineUnits()],
            ['name'=>'Waterfall Country Village','type'=>'home_owners_association','address'=>'Waterfall Country Village, Waterfall Drive, Jukskei View, Midrand, 1682',
             'admin_fund_amount'=>3200.00,'reserve_fund_amount'=>960.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-07-06 10:00:00','start_period'=>0,'units'=>$this->waterfallVillageUnits()],
            ['name'=>'Sandton Skye Body Corporate','type'=>'body_corporate','address'=>'75 Maude Street, Sandton, Johannesburg, 2196',
             'admin_fund_amount'=>5200.00,'reserve_fund_amount'=>1560.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-08-02 08:00:00','start_period'=>1,'units'=>$this->sandtonSkyeUnits()],
            ['name'=>'The Marc Residences','type'=>'body_corporate','address'=>'129 Rivonia Road, Sandton, Johannesburg, 2196',
             'admin_fund_amount'=>4500.00,'reserve_fund_amount'=>1350.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-09-02 08:00:00','start_period'=>2,'units'=>$this->theMarcUnits()],
            ['name'=>'Camps Bay Terrace Homes','type'=>'full_title','address'=>'12 Victoria Road, Camps Bay, Cape Town, 8005',
             'admin_fund_amount'=>2200.00,'reserve_fund_amount'=>660.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-10-02 08:00:00','start_period'=>3,'units'=>$this->campsBayUnits()],
            ['name'=>'Umhlanga Ridge Office Park','type'=>'property_owners_association','address'=>'10 Umhlanga Ridge Boulevard, Umhlanga, Durban, 4319',
             'admin_fund_amount'=>4500.00,'reserve_fund_amount'=>1350.00,'default_rent_amount'=>null,'billing_day'=>25,'country'=>'ZA','currency'=>'ZAR',
             'created_at'=>'2025-11-02 08:00:00','start_period'=>4,'units'=>$this->umhlangaRidgeUnits()],
        ];
    }

    private function menlynMaineUnits(): array
    {
        return [
            ['number'=>'MML-A01','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Sipho Ndlovu','s.ndlovu@gmail.com','+27 82 301 1001','8501015800083','15 Lois Avenue, Menlyn, Pretoria, 0181')],
            ['number'=>'MML-A02','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Lerato Mokoena','l.mokoena@gmail.com','+27 72 301 1002','8803125100089','42 Atterbury Road, Faerie Glen, Pretoria')],
            ['number'=>'MML-A03','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Johan Botha','j.botha@gmail.com','+27 82 301 1003','7506155800081','88 Lynnwood Road, Hatfield, Pretoria')],
            ['number'=>'MML-A04','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Zanele Mthembu','z.mthembu@yahoo.com','+27 72 301 1004','9001085100087','23 Garsfontein Road, Pretoria, 0042')],
            ['number'=>'MML-A05','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'PARKING_RENTAL','owner'=>$this->o('Rajesh Naidoo','r.naidoo@gmail.com','+27 83 301 1005','7808125800085','7 Rigel Avenue, Erasmusrand, Pretoria')],
            ['number'=>'MML-A06','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Thabo Molefe','t.molefe@hotmail.com','+27 76 301 1006','8205015800089','101 Justice Mahomed Street, Pretoria CBD')],
            ['number'=>'MML-A07','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Nomvula Zulu','n.zulu@gmail.com','+27 82 301 1007','9104125100083','19 Duncan Street, Hatfield, Pretoria')],
            ['number'=>'MML-A08','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Pieter du Plessis','p.duplessis@gmail.com','+27 72 301 1008','7701225800087','31 Dely Road, Hazelwood, Pretoria')],
            ['number'=>'MML-A09','occupancy'=>'owner_occupied','debtor'=>true,'debtor_from'=>6,'owner'=>$this->o('Bongani Dlamini','b.dlamini@gmail.com','+27 83 301 1009','8309015800081','56 Bronkhorst Street, Brooklyn, Pretoria')],
            ['number'=>'MML-A10','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Priya Pillay','p.pillay@gmail.com','+27 82 301 1010','8512085100085','9 Middel Street, Nieuw Muckleneuk, Pretoria')],
            ['number'=>'MML-A11','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Mandla Khumalo','m.khumalo@gmail.com','+27 76 301 1011','7904015800083','64 Park Street, Arcadia, Pretoria')],
            ['number'=>'MML-A12','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Katlego Maseko','k.maseko@hotmail.com','+27 72 301 1012','9206155800089','33 Festival Street, Hatfield, Pretoria')],
            ['number'=>'MML-A13','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'GYM_ACCESS','owner'=>$this->o('Willem Steyn','w.steyn@gmail.com','+27 82 301 1013','7107125800081','14 Pretorius Street, Pretoria CBD')],
            ['number'=>'MML-A14','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Sibongile Buthelezi','s.buthelezi@gmail.com','+27 83 301 1014','8808205100087','72 Waterkloof Road, Waterkloof, Pretoria')],
            ['number'=>'MML-A15','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Vikram Govender','v.govender@gmail.com','+27 72 301 1015','8003075800085','21 Celliers Street, Sunnyside, Pretoria')],
            ['number'=>'MML-B01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Thandeka Ngcobo','t.ngcobo@gmail.com','+27 72 302 1001','7803125100089','22 Atterbury Road, Garsfontein, Pretoria'),
             'organizations'=>[$this->t('Sifiso Sithole','s.sithole@gmail.com','+27 61 303 2001','9204126800081','2023-05-01','2024-04-30','2024-04-15',12000,'Left in good standing'),
                         $this->t('Nonhlanhla Mbatha','n.mbatha@yahoo.com','+27 83 303 2002','9001085100087','2024-05-01','2025-04-30','2025-04-20',13500,'Lease not renewed'),
                         $this->tc('Andile Cele','a.cele@gmail.com','+27 76 303 2003','9408185800085','2025-05-01','2026-04-30',15000)]],
            ['number'=>'MML-B02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Marius Coetzee','m.coetzee@gmail.com','+27 82 302 1002','7502075800083','48 Rigel Avenue, Erasmusrand, Pretoria'),
             'organizations'=>[$this->t('Dikeledi Phiri','d.phiri@gmail.com','+27 72 303 2004','8806125100089','2023-07-01','2024-06-30','2024-06-25',12500,'Left in good standing'),
                         $this->t('Teboho Mahlangu','t.mahlangu@hotmail.com','+27 83 303 2005','9103015800081','2024-07-01','2025-05-31','2025-05-20',13800,'Left in good standing'),
                         $this->tc('Nhlanhla Shabalala','n.shabalala@gmail.com','+27 61 303 2006','9507025800087','2025-06-01','2026-05-31',15000)]],
            ['number'=>'MML-B03','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>7,'rent_amount'=>15000,
             'owner'=>$this->o('Kamogelo Modise','k.modise@gmail.com','+27 76 302 1003','8110085100085','11 Bronkhorst Street, Brooklyn, Pretoria'),
             'organizations'=>[$this->t('Sbusiso Mkhize','s.mkhize@gmail.com','+27 82 303 2007','8704015800083','2022-11-01','2023-10-31','2023-10-28',11000,'Left in good standing'),
                         $this->t('Anita van Wyk','a.vanwyk@yahoo.com','+27 72 303 2008','9002175100089','2024-02-01','2025-01-31','2025-01-25',13000,'Lease not renewed'),
                         $this->tc('Lungelo Bhengu','l.bhengu@gmail.com','+27 83 303 2009','9609055800081','2025-02-01','2026-01-31',15000)]],
            ['number'=>'MML-B04','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>16000,
             'owner'=>$this->o('Gerhard Viljoen','g.viljoen@gmail.com','+27 82 302 1004','7309155800087','67 Festival Street, Hatfield, Pretoria'),
             'organizations'=>[$this->t('Sunitha Moodley','s.moodley@gmail.com','+27 76 303 2010','8501125100085','2023-08-01','2024-07-31','2024-07-22',12000,'Left in good standing'),
                         $this->t('Yanga Jwara','y.jwara@hotmail.com','+27 61 303 2011','9207015800083','2024-08-01','2025-07-31','2025-07-15',14000,'Left in good standing'),
                         $this->tc('Buhle Majola','b.majola@gmail.com','+27 82 303 2012','9711085100089','2025-08-01','2026-07-31',16000)]],
            ['number'=>'MML-B05','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Anele Radebe','a.radebe@gmail.com','+27 72 302 1005','8407205100081','35 Park Street, Arcadia, Pretoria'),
             'organizations'=>[$this->t('Noluthando Xaba','n.xaba@gmail.com','+27 83 303 2013','8803095100087','2023-04-01','2024-03-31','2024-03-25',11500,'Left in good standing'),
                         $this->t('Nceba Mfenyana','n.mfenyana@yahoo.com','+27 72 303 2014','9106015800085','2024-04-01','2025-03-31','2025-03-28',13500,'Lease not renewed'),
                         $this->tc('Siyanda Cele','siyanda.c@gmail.com','+27 76 303 2015','9508125800083','2025-04-01','2026-03-31',15000)]],
            ['number'=>'MML-B06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Ashwin Chetty','a.chetty@gmail.com','+27 82 302 1006','7911075800089','19 Celliers Street, Sunnyside, Pretoria'),
             'organizations'=>[$this->t('Tshegofatso Letseka','tshego.l@gmail.com','+27 61 303 2016','8702155100081','2023-01-01','2023-12-31','2023-12-28',11000,'Left in good standing'),
                         $this->t('Sandile Gcaba','s.gcaba@hotmail.com','+27 83 303 2017','9004015800087','2024-01-01','2024-12-31','2024-12-20',13000,'Left in good standing'),
                         $this->tc('Zintle Zungu','z.zungu@gmail.com','+27 72 303 2018','9610195100085','2025-01-01','2025-12-31',15000)]],
            ['number'=>'MML-B07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Christo Joubert','c.joubert@gmail.com','+27 76 302 1007','7605095800083','8 Jan Shoba Street, Brooklyn, Pretoria'),
             'organizations'=>[$this->t('Nosipho Dube','n.dube@gmail.com','+27 82 303 2019','8506025100089','2023-06-01','2024-05-31','2024-05-28',11500,'Left in good standing'),
                         $this->t('Tlotliso Motsoeneng','t.motsoeneng@yahoo.com','+27 72 303 2020','9109015800081','2024-06-01','2025-05-31','2025-05-20',13000,'Lease not renewed'),
                         $this->tc('Dimakatso Mosia','d.mosia@gmail.com','+27 83 303 2021','9703085100087','2025-06-01','2026-05-31',14500)]],
            ['number'=>'MML-B08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Mpho Motaung','m.motaung@gmail.com','+27 82 302 1008','8208015800085','44 Waterkloof Road, Waterkloof, Pretoria'),
             'organizations'=>[$this->t('Lizelle Fourie','l.fourie@gmail.com','+27 76 303 2022','8710205100083','2023-08-01','2024-07-31','2024-07-25',12000,'Left in good standing'),
                         $this->t('Rajan Padayachee','r.padayachee@hotmail.com','+27 61 303 2023','9005015800089','2024-08-01','2025-06-30','2025-06-22',13500,'Left in good standing'),
                         $this->tc('Sifiso Hlongwane','s.hlongwane@gmail.com','+27 82 303 2024','9608125800081','2025-07-01','2026-06-30',15000)]],
            ['number'=>'MML-B09','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15500,
             'owner'=>$this->o('Thandiwe Nkosi','t.nkosi@gmail.com','+27 72 302 1009','8004125100087','73 Dey Street, Arcadia, Pretoria'),
             'organizations'=>[$this->t('Willem Erasmus','w.erasmus@gmail.com','+27 83 303 2025','8211015800085','2022-09-01','2023-08-31','2023-08-28',11000,'Left in good standing'),
                         $this->t('Kavitha Reddy','k.reddy@yahoo.com','+27 72 303 2026','8907085100083','2023-09-01','2024-08-31','2024-08-22',13000,'Lease not renewed'),
                         $this->tc('Thulani Ntuli','t.ntuli@gmail.com','+27 76 303 2027','9512015800089','2024-09-01','2025-08-31',15500)]],
            ['number'=>'MML-B10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Francois Swanepoel','f.swanepoel@gmail.com','+27 82 302 1010','7108155800081','55 Pretorius Street, Pretoria CBD'),
             'organizations'=>[$this->t('Ayanda Mkhize','a.mkhize@gmail.com','+27 61 303 2028','8603015800087','2023-03-01','2024-02-29','2024-02-25',11500,'Left in good standing'),
                         $this->t('Keitumetse Tladi','k.tladi@hotmail.com','+27 83 303 2029','9201165100085','2024-03-01','2025-02-28','2025-02-22',13500,'Left in good standing'),
                         $this->tc('Nkosinathi Dlamini','n.dlamini@gmail.com','+27 72 303 2030','9706055800083','2025-03-01','2026-02-28',15000)]],
            ['number'=>'MML-B11','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Adriaan Erasmus','a.erasmus@gmail.com','+27 76 302 1011','7404015800089','28 Mackie Street, Brooklyn, Pretoria'),
             'organizations'=>[$this->t('Buhle Ngcobo','b.ngcobo@gmail.com','+27 82 303 2031','8811025100081','2023-06-01','2024-05-31','2024-05-28',12000,'Left in good standing'),
                         $this->t('Nishaan Maharaj','n.maharaj@yahoo.com','+27 72 303 2032','9107015800087','2024-06-01','2025-05-31','2025-05-20',13500,'Lease not renewed'),
                         $this->tc('Lunga Sithole','l.sithole@gmail.com','+27 83 303 2033','9802085800085','2025-06-01','2026-05-31',15000)]],
            ['number'=>'MML-B12','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Sunitha Govender','s.govender@gmail.com','+27 82 302 1012','8110125100083','16 Stanza Bopape Street, Hatfield, Pretoria'),
             'organizations'=>[$this->t('Danie Botha','d.botha@gmail.com','+27 76 303 2034','8406015800089','2023-04-01','2024-03-31','2024-03-25',11000,'Left in good standing'),
                         $this->t('Zinhle Radebe','z.radebe@hotmail.com','+27 61 303 2035','9205085100081','2024-04-01','2025-03-31','2025-03-28',13000,'Left in good standing'),
                         $this->tc('Asanda Jwara','a.jwara@gmail.com','+27 82 303 2036','9709155800087','2025-04-01','2026-03-31',14500)]],
            ['number'=>'MML-C01','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Hennie Fourie','h.fourie@gmail.com','+27 82 301 1016','7506155800081','88 Lynnwood Road, Pretoria, 0081')],
            ['number'=>'MML-C02','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Tshegofatso Sekoto','t.sekoto@gmail.com','+27 72 301 1017','9003205100085','12 Strelitzia Lane, Garsfontein, Pretoria')],
            ['number'=>'MML-C03','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Nishma Naicker','n.naicker@gmail.com','+27 83 301 1018','8712085100089','5 Rigel Avenue, Erasmusrand, Pretoria')],
        ];
    }

    private function waterfallVillageUnits(): array
    {
        return [
            ['number'=>'WCV-A01','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Andile Mbatha','a.mbatha@gmail.com','+27 82 311 1001','8305015800083','18 Waterfall Drive, Jukskei View, Midrand')],
            ['number'=>'WCV-A02','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Dikeledi Molefe','d.molefe@gmail.com','+27 72 311 1002','8607125100089','24 Maxwell Drive, Waterfall, Midrand')],
            ['number'=>'WCV-A03','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Christo Pretorius','c.pretorius@gmail.com','+27 82 311 1003','7801075800081','51 Bekker Road, Vorna Valley, Midrand')],
            ['number'=>'WCV-A04','occupancy'=>'owner_occupied','debtor'=>true,'debtor_from'=>5,'owner'=>$this->o('Nompumelelo Zulu','n.zulu2@gmail.com','+27 76 311 1004','9004185100087','9 Waterfall Crescent, Jukskei View')],
            ['number'=>'WCV-A05','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Devan Naidoo','d.naidoo@gmail.com','+27 83 311 1005','8209015800085','37 Country Lane, Waterfall, Midrand')],
            ['number'=>'WCV-A06','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Lungelo Ntuli','l.ntuli@gmail.com','+27 72 311 1006','8411025800083','62 Woodmead Drive, Woodmead, Sandton')],
            ['number'=>'WCV-A07','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Lizelle Viljoen','l.viljoen@gmail.com','+27 82 311 1007','8103115100089','15 Allandale Road, Midrand')],
            ['number'=>'WCV-A08','occupancy'=>'owner_occupied','debtor'=>false,'extra_ct'=>'POOL_ACCESS','owner'=>$this->o('Sbusiso Ngcobo','s.ngcobo@gmail.com','+27 76 311 1008','8706015800081','28 Lever Road, Halfway House, Midrand')],
            ['number'=>'WCV-A09','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Kamogelo Maseko','kam.maseko@gmail.com','+27 83 311 1009','9108185800087','43 Grand Central Avenue, Midrand')],
            ['number'=>'WCV-A10','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Yashica Pillay','y.pillay@gmail.com','+27 72 311 1010','8509085100085','6 Sunninghill Village, Sunninghill')],
            ['number'=>'WCV-A11','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Siyanda Dube','s.dube@gmail.com','+27 82 311 1011','8802015800083','70 Waterfall Avenue, Jukskei View')],
            ['number'=>'WCV-A12','occupancy'=>'owner_occupied','debtor'=>false,'owner'=>$this->o('Marius Steyn','m.steyn@gmail.com','+27 76 311 1012','7606155800089','34 Kyalami Hills Road, Kyalami')],
            ['number'=>'WCV-B01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Kgomotso Modise','kg.modise@gmail.com','+27 82 312 1001','7908125100081','12 Lever Road, Halfway House, Midrand'),
             'organizations'=>[$this->t('Themba Mabaso','t.mabaso@gmail.com','+27 61 313 2001','8604015800087','2023-05-01','2024-04-30','2024-04-22',11500,'Left in good standing'),
                         $this->t('Chantel Joubert','c.joubert2@yahoo.com','+27 83 313 2002','9102085100085','2024-05-01','2025-04-30','2025-04-18',13000,'Lease not renewed'),
                         $this->tc('Bongiwe Cele','b.cele@gmail.com','+27 72 313 2003','9607025800083','2025-05-01','2026-04-30',14500)]],
            ['number'=>'WCV-B02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Adriaan Botha','a.botha@gmail.com','+27 72 312 1002','7503035800089','55 Maxwell Drive, Waterfall, Midrand'),
             'organizations'=>[$this->t('Zama Ngwenya','z.ngwenya@gmail.com','+27 76 313 2004','8710015800081','2023-07-01','2024-06-30','2024-06-25',11000,'Left in good standing'),
                         $this->t('Lesedi Mokone','l.mokone@hotmail.com','+27 82 313 2005','9205125100087','2024-07-01','2025-05-31','2025-05-20',13000,'Left in good standing'),
                         $this->tc('Sanele Dlamini','s.dlamini@gmail.com','+27 61 313 2006','9708015800085','2025-06-01','2026-05-31',14500)]],
            ['number'=>'WCV-B03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Nomsa Nkosi','n.nkosi@gmail.com','+27 83 312 1003','8205085100083','31 Sunninghill Village, Sunninghill'),
             'organizations'=>[$this->t('Pieter Steenkamp','p.steenkamp@gmail.com','+27 72 313 2007','8401015800089','2022-10-01','2023-09-30','2023-09-25',11000,'Left in good standing'),
                         $this->t('Nandi Mkhize','nandi.m@yahoo.com','+27 83 313 2008','9103125100081','2023-10-01','2024-09-30','2024-09-22',13000,'Lease not renewed'),
                         $this->tc('Ashwin Maharaj','a.maharaj@gmail.com','+27 76 313 2009','9510015800087','2024-10-01','2025-09-30',15000)]],
            ['number'=>'WCV-B04','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Thapelo Motaung','t.motaung@gmail.com','+27 76 312 1004','8609015800085','22 Grand Central Ave, Midrand'),
             'organizations'=>[$this->t('Rikus Botha','r.botha@gmail.com','+27 82 313 2010','8301015800083','2023-02-01','2024-01-31','2024-01-28',11000,'Left in good standing'),
                         $this->t('Thandiwe Radebe','t.radebe@hotmail.com','+27 72 313 2011','9006085100089','2024-02-01','2025-01-31','2025-01-25',13000,'Left in good standing'),
                         $this->tc('Nishaan Govender','n.govender@gmail.com','+27 83 313 2012','9708055800081','2025-02-01','2026-01-31',14500)]],
            ['number'=>'WCV-B05','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>8,'rent_amount'=>14500,
             'owner'=>$this->o('Zanele Maseko','z.maseko@gmail.com','+27 82 312 1005','8411205100087','47 Kyalami Hills Road, Kyalami'),
             'organizations'=>[$this->t('Wandile Khumalo','w.khumalo@gmail.com','+27 61 313 2013','8502015800085','2023-04-01','2024-03-31','2024-03-25',11000,'Left in good standing'),
                         $this->t('Annemarie Steyn','a.steyn@yahoo.com','+27 76 313 2014','9204125100083','2024-04-01','2025-03-31','2025-03-22',12800,'Lease not renewed'),
                         $this->tc('Suren Naicker','s.naicker@gmail.com','+27 82 313 2015','9609015800089','2025-04-01','2026-03-31',14500)]],
            ['number'=>'WCV-B06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14000,
             'owner'=>$this->o('Danie Swanepoel','d.swanepoel@gmail.com','+27 72 312 1006','7807155800081','8 Allandale Road, Midrand'),
             'organizations'=>[$this->t('Nonkululeko Zwane','n.zwane@gmail.com','+27 83 313 2016','8805015100087','2023-08-01','2024-07-31','2024-07-22',10500,'Left in good standing'),
                         $this->t('Refiloe Mokoena','r.mokoena@hotmail.com','+27 72 313 2017','9107065100085','2024-08-01','2025-07-31','2025-07-15',12500,'Left in good standing'),
                         $this->tc('Mpumelelo Ndlovu','m.ndlovu@gmail.com','+27 76 313 2018','9711015800083','2025-08-01','2026-07-31',14000)]],
            ['number'=>'WCV-B07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Nthabiseng Sefako','n.sefako@gmail.com','+27 83 312 1007','8306195100089','16 Bekker Road, Vorna Valley, Midrand'),
             'organizations'=>[$this->t('Gerrit Booysen','g.booysen@gmail.com','+27 82 313 2019','8201015800081','2023-01-01','2023-12-31','2023-12-28',10500,'Left in good standing'),
                         $this->t('Lebo Mahlangu','l.mahlangu@yahoo.com','+27 61 313 2020','9003015800087','2024-01-01','2024-12-31','2024-12-20',12800,'Lease not renewed'),
                         $this->tc('Siphelele Mthembu','s.mthembu@gmail.com','+27 72 313 2021','9802125800085','2025-01-01','2025-12-31',14500)]],
            ['number'=>'WCV-B08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Vikash Pillay','v.pillay@gmail.com','+27 76 312 1008','7909015800083','29 Country Lane, Waterfall, Midrand'),
             'organizations'=>[$this->t('Lehlohonolo Tsotetsi','l.tsotetsi@gmail.com','+27 83 313 2022','8507015800089','2023-06-01','2024-05-31','2024-05-28',11000,'Left in good standing'),
                         $this->t('Jolene Marais','j.marais@hotmail.com','+27 72 313 2023','9208105100081','2024-06-01','2025-05-31','2025-05-20',13000,'Left in good standing'),
                         $this->tc('Thabani Ngubane','t.ngubane@gmail.com','+27 82 313 2024','9710015800087','2025-06-01','2026-05-31',14500)]],
            ['number'=>'WCV-B09','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>15000,
             'owner'=>$this->o('Reneilwe Masemola','r.masemola@gmail.com','+27 82 312 1009','8102025100085','63 Woodmead Drive, Woodmead'),
             'organizations'=>[$this->t('Jacques Venter','j.venter@gmail.com','+27 76 313 2025','8308015800083','2023-03-01','2024-02-29','2024-02-25',11500,'Left in good standing'),
                         $this->t('Palesa Mokoena','p.mokoena@yahoo.com','+27 61 313 2026','9105085100089','2024-03-01','2025-02-28','2025-02-22',13500,'Left in good standing'),
                         $this->tc('Thabiso Sithole','t.sithole@gmail.com','+27 83 313 2027','9604015800081','2025-03-01','2026-02-28',15000)]],
            ['number'=>'WCV-B10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14500,
             'owner'=>$this->o('Elmarie Steyn','e.steyn@gmail.com','+27 72 312 1010','7711095100087','40 Lever Road, Halfway House, Midrand'),
             'organizations'=>[$this->t('Xolani Buthelezi','x.buthelezi@gmail.com','+27 82 313 2028','8609015800085','2022-08-01','2023-07-31','2023-07-28',10500,'Left in good standing'),
                         $this->t('Lebohang Mofokeng','l.mofokeng@hotmail.com','+27 72 313 2029','9202125100083','2023-08-01','2024-07-31','2024-07-22',12500,'Left in good standing'),
                         $this->tc('Vuyo Gaxa','v.gaxa@gmail.com','+27 76 313 2030','9807015800089','2024-08-01','2025-07-31',14500)]],
            ['number'=>'WCV-B11','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>14000,
             'owner'=>$this->o('Kabelo Tladi','k.tladi@gmail.com','+27 83 312 1011','8508015800081','11 Sunninghill Village, Sunninghill'),
             'organizations'=>[$this->t('Johan Fourie','j.fourie@gmail.com','+27 82 313 2031','8110015800087','2023-09-01','2024-08-31','2024-08-25',10500,'Left in good standing'),
                         $this->t('Precious Zwane','p.zwane@yahoo.com','+27 61 313 2032','9306085100085','2024-09-01','2025-08-31','2025-08-20',12500,'Lease not renewed'),
                         $this->tc('Mandisa Khumalo','m.khumalo2@gmail.com','+27 72 313 2033','9901125100083','2025-09-01','2026-08-31',14000)]],
            ['number'=>'WCV-C01','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Francois Marais','f.marais@gmail.com','+27 82 311 1013','7704015800085','77 Waterfall Drive, Jukskei View')],
            ['number'=>'WCV-C02','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Lesedi Kgatla','l.kgatla@gmail.com','+27 72 311 1014','9110085100089','33 Allandale Road, Midrand')],
        ];
    }

    private function sandtonSkyeUnits(): array
    {
        $owners = [
            [$this->o('Sipho Zulu','s.zulu@gmail.com','+27 82 401 1001','8301015800083','Unit 1, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Lerato Maseko','l.maseko@gmail.com','+27 72 401 1002','8605125100089','Unit 2, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Johan van Wyk','j.vanwyk@gmail.com','+27 82 401 1003','7504015800081','Unit 3, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Zanele Dlamini','z.dlamini@gmail.com','+27 76 401 1004','9108085100087','Unit 4, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Rajesh Maharaj','r.maharaj@gmail.com','+27 83 401 1005','7902025800085','Unit 5, Sandton Skye, 75 Maude St, Sandton'), true, 4],
            [$this->o('Nomvula Ngcobo','n.ngcobo@gmail.com','+27 72 401 1006','8803205100083','Unit 6, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Pieter Steyn','p.steyn@gmail.com','+27 82 401 1007','8107155800089','Unit 7, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Bongani Nkosi','b.nkosi@gmail.com','+27 76 401 1008','8504015800081','Unit 8, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Priya Moodley','p.moodley@gmail.com','+27 83 401 1009','9007085100087','Unit 9, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Thabo Phiri','t.phiri@gmail.com','+27 72 401 1010','8210015800085','Unit 10, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Marius Viljoen','m.viljoen@gmail.com','+27 82 401 1011','7608155800083','Unit 11, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Sibongile Cele','s.cele@gmail.com','+27 76 401 1012','9312205100089','Unit 12, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Nishaan Singh','n.singh@gmail.com','+27 83 401 1013','8405025800081','Unit 13, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Katlego Motaung','k.motaung@gmail.com','+27 72 401 1014','8809015800087','Unit 14, Sandton Skye, 75 Maude St, Sandton'), true, 7],
            [$this->o('Gerhard Erasmus','g.erasmus@gmail.com','+27 82 401 1015','7303015800085','Unit 15, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Mandla Shabalala','m.shabalala@gmail.com','+27 76 401 1016','8701015800083','Unit 16, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Dikeledi Tladi','d.tladi@gmail.com','+27 83 401 1017','9203085100089','Unit 17, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Willem Botha','w.botha@gmail.com','+27 72 401 1018','7806015800081','Unit 18, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Nokuthula Mthembu','noku.mthembu@gmail.com','+27 82 401 1019','8904125100087','Unit 19, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Ashwin Naidoo','a.naidoo@gmail.com','+27 76 401 1020','8102015800085','Unit 20, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Kamogelo Letseka','k.letseka@gmail.com','+27 83 401 1021','9507015800083','Unit 21, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Francois du Plessis','f.duplessis@gmail.com','+27 72 401 1022','7209155800089','Unit 22, Sandton Skye, 75 Maude St, Sandton'), false, null],
            [$this->o('Lungelo Buthelezi','l.buthelezi@gmail.com','+27 82 401 1023','8610015800081','Unit 23, Sandton Skye, 75 Maude St, Sandton'), false, null],
        ];
        $units = [];
        foreach ($owners as $i => [$owner, $debtor, $debtorFrom]) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $units[] = ['number'=>"SSK-{$num}",'occupancy'=>'owner_occupied','debtor'=>$debtor,'debtor_from'=>$debtorFrom,'owner'=>$owner];
        }
        $units[] = ['number'=>'SSK-24','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Sunitha Reddy','s.reddy@gmail.com','+27 83 401 1024','8708085100087','Unit 24, Sandton Skye, 75 Maude St, Sandton')];
        $units[] = ['number'=>'SSK-25','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Teboho Mokoena','t.mokoena@gmail.com','+27 72 401 1025','9106015800085','Unit 25, Sandton Skye, 75 Maude St, Sandton')];
        return $units;
    }

    private function theMarcUnits(): array
    {
        $owners = [
            [$this->o('Thabo Ndlovu','t.ndlovu@gmail.com','+27 82 411 1001','8203015800083','Unit 1, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Lerato Phiri','l.phiri@gmail.com','+27 72 411 1002','8706085100089','Unit 2, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Pieter Marais','p.marais@gmail.com','+27 82 411 1003','7404015800081','Unit 3, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Zanele Khumalo','z.khumalo@gmail.com','+27 76 411 1004','9201125100087','Unit 4, The Marc, 129 Rivonia Rd, Sandton'), true, 3],
            [$this->o('Vikram Chetty','v.chetty@gmail.com','+27 83 411 1005','8009025800085','Unit 5, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Bongani Zulu','b.zulu@gmail.com','+27 72 411 1006','8505015800083','Unit 6, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Nomvula Maseko','n.maseko@gmail.com','+27 82 411 1007','8808205100089','Unit 7, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Marius Coetzee','m.coetzee2@gmail.com','+27 76 411 1008','7507155800081','Unit 8, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Sipho Sithole','s.sithole2@gmail.com','+27 83 411 1009','8311015800087','Unit 9, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Priya Govender','p.govender@gmail.com','+27 72 411 1010','9003085100085','Unit 10, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Katlego Dlamini','k.dlamini@gmail.com','+27 82 411 1011','8704015800083','Unit 11, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Christo Steyn','c.steyn@gmail.com','+27 76 411 1012','7210155800089','Unit 12, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Mandla Ngcobo','m.ngcobo@gmail.com','+27 83 411 1013','8606015800081','Unit 13, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Dikeledi Mokoena','d.mokoena@gmail.com','+27 72 411 1014','9109085100087','Unit 14, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Nishaan Pillay','n.pillay@gmail.com','+27 82 411 1015','8208025800085','Unit 15, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Sibongile Mthembu','s.mthembu2@gmail.com','+27 76 411 1016','9305125100083','Unit 16, The Marc, 129 Rivonia Rd, Sandton'), true, 6],
            [$this->o('Willem Fourie','w.fourie@gmail.com','+27 83 411 1017','7801015800089','Unit 17, The Marc, 129 Rivonia Rd, Sandton'), false, null],
            [$this->o('Kamogelo Nkosi','k.nkosi@gmail.com','+27 72 411 1018','9502015800081','Unit 18, The Marc, 129 Rivonia Rd, Sandton'), false, null],
        ];
        $units = [];
        foreach ($owners as $i => [$owner, $debtor, $debtorFrom]) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $units[] = ['number'=>"TMA-{$num}",'occupancy'=>'owner_occupied','debtor'=>$debtor,'debtor_from'=>$debtorFrom,'owner'=>$owner];
        }
        $units[] = ['number'=>'TMA-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Gerhard Botha','g.botha@gmail.com','+27 82 411 1019','7603015800087','Unit 19, The Marc, 129 Rivonia Rd, Sandton')];
        $units[] = ['number'=>'TMA-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Thandiwe Cele','t.cele@gmail.com','+27 72 411 1020','8912085100085','Unit 20, The Marc, 129 Rivonia Rd, Sandton')];
        return $units;
    }

    /* ================================================================== */
    /*  CAMPS BAY TERRACE — Residential Rental, 20 (18 occupant + 2 vacant) */
    /* ================================================================== */
    private function campsBayUnits(): array
    {
        return [
            ['number'=>'CBT-01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Johan du Plessis','j.duplessis@gmail.com','+27 82 501 1001','7301015800083','18 Victoria Road, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Andile Dyani','a.dyani@gmail.com','+27 61 503 2001','8804015800089','2022-07-01','2023-06-30','2023-06-25',17000,'Left in good standing'),
                         $this->t('Lisa Petersen','l.petersen@yahoo.com','+27 83 503 2002','9107125100081','2023-07-01','2024-04-30','2024-04-22',19500,'Lease not renewed'),
                         $this->tc('Luvuyo Qunta','l.qunta@gmail.com','+27 72 503 2003','9503015800087','2024-05-01','2025-04-30',22000)]],
            ['number'=>'CBT-02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Sarah Williams','s.williams@gmail.com','+27 72 501 1002','8006085100085','25 Camps Bay Drive, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Marius Joubert','m.joubert@gmail.com','+27 76 503 2004','8502015800083','2022-08-01','2023-07-31','2023-07-28',17500,'Left in good standing'),
                         $this->t('Unathi Ntsele','u.ntsele@hotmail.com','+27 82 503 2005','9209085100089','2023-08-01','2024-05-31','2024-05-20',19500,'Left in good standing'),
                         $this->tc('James Abrahams','j.abrahams@gmail.com','+27 83 503 2006','9608015800081','2024-06-01','2025-05-31',22000)]],
            ['number'=>'CBT-03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>24000,
             'owner'=>$this->o('Christo van Wyk','c.vanwyk@gmail.com','+27 82 501 1003','7605155800087','9 Geneva Drive, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Nosipho Mfenyana','n.mfenyana@gmail.com','+27 72 503 2007','8710015100085','2022-09-01','2023-08-31','2023-08-28',18000,'Left in good standing'),
                         $this->t('David Davids','d.davids@yahoo.com','+27 76 503 2008','9103015800083','2023-09-01','2024-06-30','2024-06-22',21000,'Lease not renewed'),
                         $this->tc('Zintle Dyani','z.dyani@gmail.com','+27 82 503 2009','9707085100089','2024-07-01','2025-06-30',24000)]],
            ['number'=>'CBT-04','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Anele Ntsele','a.ntsele@gmail.com','+27 76 501 1004','8204205100081','33 The Glen, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Pieter Botha','p.botha2@gmail.com','+27 83 503 2010','8301015800087','2022-10-01','2023-09-30','2023-09-25',16500,'Left in good standing'),
                         $this->t('Yanga Gaxa','y.gaxa@hotmail.com','+27 72 503 2011','9205125800085','2023-10-01','2024-07-31','2024-07-28',19000,'Left in good standing'),
                         $this->tc('Karen Smith','k.smith@gmail.com','+27 82 503 2012','9602085100083','2024-08-01','2025-07-31',22000)]],
            ['number'=>'CBT-05','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>5,'rent_amount'=>22000,
             'owner'=>$this->o('Lizelle Steyn','l.steyn@gmail.com','+27 83 501 1005','8507175100089','14 Ravensteyn Road, Camps Bay'),
             'organizations'=>[$this->t('Siyanda Nqobile','s.nqobile@gmail.com','+27 61 503 2013','8604015800081','2022-11-01','2023-10-31','2023-10-28',16000,'Left in good standing'),
                         $this->t('Michael Jones','m.jones@yahoo.com','+27 82 503 2014','9106015800087','2023-11-01','2024-08-31','2024-08-25',19500,'Lease not renewed'),
                         $this->tc('Nceba Mfenyana','nceba.mf@gmail.com','+27 76 503 2015','9708015800085','2024-09-01','2025-08-31',22000)]],
            ['number'=>'CBT-06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>20000,
             'owner'=>$this->o('Lunga Dyani','l.dyani@gmail.com','+27 72 501 1006','8109015800083','41 Theresa Avenue, Camps Bay'),
             'organizations'=>[$this->t('Anita Fourie','a.fourie@gmail.com','+27 83 503 2016','8803125100089','2022-12-01','2023-11-30','2023-11-25',15000,'Left in good standing'),
                         $this->t('Buhle Xaba','b.xaba@hotmail.com','+27 72 503 2017','9201015800081','2023-12-01','2024-09-30','2024-09-22',17500,'Left in good standing'),
                         $this->tc('Rashid Abrahams','r.abrahams@gmail.com','+27 82 503 2018','9609025800087','2024-10-01','2025-09-30',20000)]],
            ['number'=>'CBT-07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Gerhard Viljoen','g.viljoen2@gmail.com','+27 82 501 1007','7408015800085','7 Beta Road, Bakoven, Cape Town'),
             'organizations'=>[$this->t('Zintle Qunta','z.qunta@gmail.com','+27 76 503 2019','8705025100083','2023-01-01','2023-12-31','2023-12-28',16500,'Left in good standing'),
                         $this->t('Willem Marais','w.marais@yahoo.com','+27 61 503 2020','9008015800089','2024-01-01','2024-10-31','2024-10-25',19000,'Lease not renewed'),
                         $this->tc('Noluthando Dyani','n.dyani@gmail.com','+27 83 503 2021','9711085100081','2024-11-01','2025-10-31',22000)]],
            ['number'=>'CBT-08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Noluthando Smith','n.smith@gmail.com','+27 76 501 1008','8210125100087','51 Kloof Road, Clifton, Cape Town'),
             'organizations'=>[$this->t('Adriaan Botha','a.botha2@gmail.com','+27 82 503 2022','8103015800085','2023-02-01','2024-01-31','2024-01-28',17000,'Left in good standing'),
                         $this->t('Unathi Xaba','u.xaba@hotmail.com','+27 72 503 2023','9107065100083','2024-02-01','2024-11-30','2024-11-22',19500,'Left in good standing'),
                         $this->tc('Faizel Petersen','f.petersen@gmail.com','+27 76 503 2024','9805015800089','2024-12-01','2025-11-30',22000)]],
            ['number'=>'CBT-09','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>24000,
             'owner'=>$this->o('Michael Davids','m.davids@gmail.com','+27 83 501 1009','7709015800081','62 Victoria Road, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Luvuyo Ntsele','l.ntsele@gmail.com','+27 72 503 2025','8506015800087','2023-03-01','2024-02-29','2024-02-25',18500,'Left in good standing'),
                         $this->t('Chantel du Plessis','c.duplessis@yahoo.com','+27 83 503 2026','9204125100085','2024-03-01','2024-12-31','2024-12-25',21000,'Lease not renewed'),
                         $this->tc('Siphelele Mfenyana','s.mfenyana@gmail.com','+27 82 503 2027','9701015800083','2025-01-01','2025-12-31',24000)]],
            ['number'=>'CBT-10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Yusuf Abrahams','y.abrahams@gmail.com','+27 72 501 1010','8011015800089','28 The Ridge, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Marius Booysen','m.booysen@gmail.com','+27 76 503 2028','8308015800081','2023-04-01','2024-03-31','2024-03-25',17000,'Left in good standing'),
                         $this->t('Anele Dyani','a.dyani2@hotmail.com','+27 61 503 2029','9105085100087','2024-04-01','2025-01-31','2025-01-25',19500,'Left in good standing'),
                         $this->tc('Pieter Venter','p.venter@gmail.com','+27 83 503 2030','9609015800085','2025-02-01','2026-01-31',22000)]],
            ['number'=>'CBT-11','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Christo Marais','c.marais@gmail.com','+27 82 501 1011','7506015800083','5 Athol Road, Camps Bay, Cape Town'),
             'organizations'=>[$this->t('Nosipho Qunta','n.qunta@gmail.com','+27 82 503 2031','8802025100089','2023-06-01','2024-05-31','2024-05-28',17000,'Left in good standing'),
                         $this->t('James Petersen','j.petersen@yahoo.com','+27 72 503 2032','9204015800081','2024-06-01','2025-05-31','2025-05-20',19500,'Lease not renewed'),
                         $this->tc('Lunga Gaxa','l.gaxa@gmail.com','+27 76 503 2033','9805085800087','2025-06-01','2026-05-31',22000)]],
            ['number'=>'CBT-12','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>20000,
             'owner'=>$this->o('Karen Petersen','k.petersen@gmail.com','+27 76 501 1012','8303205100085','16 Francolin Road, Camps Bay'),
             'organizations'=>[$this->t('Siyanda Ntsele','s.ntsele@gmail.com','+27 83 503 2034','8607015800083','2023-08-01','2024-07-31','2024-07-25',15500,'Left in good standing'),
                         $this->t('Lizelle Joubert','l.joubert@hotmail.com','+27 72 503 2035','9308125100089','2024-08-01','2025-06-30','2025-06-22',18000,'Left in good standing'),
                         $this->tc('Buhle Dyani','b.dyani@gmail.com','+27 82 503 2036','9802015800081','2025-07-01','2026-06-30',20000)]],
            ['number'=>'CBT-13','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('David Jones','d.jones@gmail.com','+27 83 501 1013','7805015800087','8 Geneva Drive, Camps Bay'),
             'organizations'=>[$this->t('Zintle Abrahams','z.abrahams@gmail.com','+27 76 503 2037','8908085100085','2022-06-01','2023-05-31','2023-05-28',16000,'Left in good standing'),
                         $this->t('Gerhard Fourie','g.fourie@yahoo.com','+27 61 503 2038','9203015800083','2023-06-01','2024-03-31','2024-03-25',19000,'Lease not renewed'),
                         $this->tc('Andile Mfenyana','a.mfenyana@gmail.com','+27 82 503 2039','9706025800089','2024-04-01','2025-03-31',22000)]],
            ['number'=>'CBT-14','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>7,'rent_amount'=>22000,
             'owner'=>$this->o('Anele Qunta','a.qunta@gmail.com','+27 72 501 1014','8502205100081','22 Houghton Road, Camps Bay'),
             'organizations'=>[$this->t('Johan Steyn','j.steyn@gmail.com','+27 82 503 2040','8107015800087','2023-07-01','2024-06-30','2024-06-25',17000,'Left in good standing'),
                         $this->t('Unathi Gaxa','u.gaxa@hotmail.com','+27 83 503 2041','9005125100085','2024-07-01','2025-03-31','2025-03-22',19500,'Left in good standing'),
                         $this->tc('Lisa Davids','l.davids@gmail.com','+27 72 503 2042','9801085100083','2025-04-01','2026-03-31',22000)]],
            ['number'=>'CBT-15','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Marius van Wyk','m.vanwyk@gmail.com','+27 82 501 1015','7701015800085','35 Ravensteyn Road, Camps Bay'),
             'organizations'=>[$this->t('Nceba Ntsele','n.ntsele2@gmail.com','+27 76 503 2043','8604015800083','2022-08-01','2023-07-31','2023-07-28',16000,'Left in good standing'),
                         $this->t('Sarah Joubert','s.joubert@yahoo.com','+27 61 503 2044','9108085100089','2023-08-01','2024-05-31','2024-05-20',19000,'Left in good standing'),
                         $this->tc('Yanga Mfenyana','y.mfenyana@gmail.com','+27 83 503 2045','9707015800081','2024-06-01','2025-05-31',22000)]],
            ['number'=>'CBT-16','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>20000,
             'owner'=>$this->o('Faizel Davids','f.davids@gmail.com','+27 76 501 1016','8209015800087','44 The Glen, Camps Bay'),
             'organizations'=>[$this->t('Pieter Viljoen','p.viljoen@gmail.com','+27 82 503 2046','8403015800085','2023-09-01','2024-08-31','2024-08-25',15500,'Left in good standing'),
                         $this->t('Noluthando Gaxa','n.gaxa@hotmail.com','+27 72 503 2047','9306025100083','2024-09-01','2025-07-31','2025-07-22',18000,'Lease not renewed'),
                         $this->tc('Rashid Petersen','r.petersen@gmail.com','+27 83 503 2048','9809015800089','2025-08-01','2026-07-31',20000)]],
            ['number'=>'CBT-17','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Lunga Smith','l.smith@gmail.com','+27 83 501 1017','8607015800081','11 Beta Road, Bakoven, Cape Town'),
             'organizations'=>[$this->t('Anita van Wyk','a.vanwyk2@gmail.com','+27 76 503 2049','8705025100087','2023-01-01','2023-12-31','2023-12-28',17000,'Left in good standing'),
                         $this->t('Andile Ntsele','a.ntsele@yahoo.com','+27 61 503 2050','9207015800085','2024-01-01','2024-10-31','2024-10-25',19500,'Left in good standing'),
                         $this->tc('Christo Botha','c.botha@gmail.com','+27 82 503 2051','9712015800083','2024-11-01','2025-10-31',22000)]],
            ['number'=>'CBT-18','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>24000,
             'owner'=>$this->o('Nosipho Abrahams','n.abrahams@gmail.com','+27 72 501 1018','8910125100089','55 Victoria Road, Camps Bay'),
             'organizations'=>[$this->t('Willem du Plessis','w.duplessis@gmail.com','+27 83 503 2052','8201015800081','2023-04-01','2024-03-31','2024-03-25',18500,'Left in good standing'),
                         $this->t('Zintle Ntsele','z.ntsele@hotmail.com','+27 72 503 2053','9102085100087','2024-04-01','2025-01-31','2025-01-25',21000,'Left in good standing'),
                         $this->tc('Michael Petersen','m.petersen@gmail.com','+27 76 503 2054','9810015800085','2025-02-01','2026-01-31',24000)]],
            // Vacant (2)
            ['number'=>'CBT-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Gerhard Smith','g.smith@gmail.com','+27 82 501 1019','7606015800083','66 Kloof Road, Clifton, Cape Town')],
            ['number'=>'CBT-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Andile Petersen','a.petersen@gmail.com','+27 72 501 1020','9008085100089','29 Theresa Avenue, Camps Bay')],
        ];
    }

    /* ================================================================== */
    /*  UMHLANGA RIDGE — Commercial Rental, 20 (18 occupant + 2 vacant)     */
    /* ================================================================== */
    private function umhlangaRidgeUnits(): array
    {
        return [
            ['number'=>'URO-01','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Growthpoint Properties Ltd','property@growthpoint.co.za','+27 31 561 1001','ZA-CORP-8001','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Investec Advisory SA','admin@investecadv.co.za','+27 31 560 4001','ZA-CORP-9001','2022-01-01','2023-12-31','2023-12-22',22000,'Lease expired'),
                         $this->t('PwC South Africa','ops@pwc.co.za','+27 31 560 4002','ZA-CORP-9002','2024-01-01','2024-12-31','2024-12-18',25000,'Lease not renewed'),
                         $this->tc('Discovery Health','durban@discovery.co.za','+27 31 560 3001','ZA-CORP-8101','2025-01-01','2027-12-31',28000)]],
            ['number'=>'URO-02','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>32000,
             'owner'=>$this->o('Redefine Properties Ltd','property@redefine.co.za','+27 31 561 1002','ZA-CORP-8002','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('KPMG South Africa','admin@kpmg.co.za','+27 31 560 4003','ZA-CORP-9003','2022-03-01','2024-02-29','2024-02-22',24000,'Relocated'),
                         $this->t('EY South Africa','ops@ey.co.za','+27 31 560 4004','ZA-CORP-9004','2024-03-01','2024-12-31','2024-12-20',28000,'Lease expired'),
                         $this->tc('Capitec Bank','umhlanga@capitec.co.za','+27 31 560 3002','ZA-CORP-8102','2025-01-01','2027-12-31',32000)]],
            ['number'=>'URO-03','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Attacq Ltd','property@attacq.co.za','+27 31 561 1003','ZA-CORP-8003','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Deloitte SA','admin@deloitte.co.za','+27 31 560 4005','ZA-CORP-9005','2022-06-01','2024-05-31','2024-05-25',22000,'Left in good standing'),
                         $this->t('Dimension Data','ops@didata.co.za','+27 31 560 4006','ZA-CORP-9006','2024-06-01','2025-01-31','2025-01-25',25000,'Lease not renewed'),
                         $this->tc('Vodacom SA','umhlanga@vodacom.co.za','+27 31 560 3003','ZA-CORP-8103','2025-02-01','2028-01-31',28000)]],
            ['number'=>'URO-04','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>5,'rent_amount'=>30000,
             'owner'=>$this->o('Vukile Property Fund','property@vukile.co.za','+27 31 561 1004','ZA-CORP-8004','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Altron Limited','admin@altron.co.za','+27 31 560 4007','ZA-CORP-9007','2022-04-01','2023-03-31','2023-03-28',22000,'Lease expired'),
                         $this->t('Datatec Holdings','ops@datatec.co.za','+27 31 560 4008','ZA-CORP-9008','2023-04-01','2024-12-31','2024-12-20',26000,'Lease not renewed'),
                         $this->tc('MTN Group','umhlanga@mtn.co.za','+27 31 560 3004','ZA-CORP-8104','2025-02-01','2027-01-31',30000)]],
            ['number'=>'URO-05','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Hyprop Investments Ltd','property@hyprop.co.za','+27 31 561 1005','ZA-CORP-8005','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Sappi Limited','admin@sappi.co.za','+27 31 560 4009','ZA-CORP-9009','2022-09-01','2024-08-31','2024-08-25',22000,'Left in good standing'),
                         $this->t('Tiger Brands','ops@tigerbrands.co.za','+27 31 560 4010','ZA-CORP-9010','2024-09-01','2025-02-28','2025-02-22',25000,'Lease not renewed'),
                         $this->tc('Standard Bank','umhlanga@standardbank.co.za','+27 31 560 3005','ZA-CORP-8105','2025-03-01','2028-02-28',28000)]],
            ['number'=>'URO-06','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Liberty Holdings Ltd','property@liberty.co.za','+27 31 561 1006','ZA-CORP-8006','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Pioneer Foods','admin@pioneerfoods.co.za','+27 31 560 4011','ZA-CORP-9011','2022-05-01','2023-04-30','2023-04-25',21000,'Left in good standing'),
                         $this->t('RCL Foods','ops@rclfoods.co.za','+27 31 560 4012','ZA-CORP-9012','2023-05-01','2025-02-28','2025-02-22',25000,'Lease expired'),
                         $this->tc('Nedbank','umhlanga@nedbank.co.za','+27 31 560 3006','ZA-CORP-8106','2025-03-01','2027-02-28',28000)]],
            ['number'=>'URO-07','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>25000,
             'owner'=>$this->o('Investec Property Ltd','property@investec.co.za','+27 31 561 1007','ZA-CORP-8007','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Nandos SA Pty','admin@nandos.co.za','+27 31 560 4013','ZA-CORP-9013','2022-02-01','2024-01-31','2024-01-25',19000,'Relocated'),
                         $this->t('Steers Holdings','ops@steers.co.za','+27 31 560 4014','ZA-CORP-9014','2024-02-01','2025-03-31','2025-03-25',22000,'Lease not renewed'),
                         $this->tc('Old Mutual','umhlanga@oldmutual.co.za','+27 31 560 3007','ZA-CORP-8107','2025-04-01','2028-03-31',25000)]],
            ['number'=>'URO-08','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>25000,
             'owner'=>$this->o('Dipula Income Fund','property@dipula.co.za','+27 31 561 1008','ZA-CORP-8008','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Clicks Group','admin@clicks.co.za','+27 31 560 4015','ZA-CORP-9015','2022-07-01','2023-06-30','2023-06-25',18000,'Left in good standing'),
                         $this->t('Multichoice SA','ops@multichoice.co.za','+27 31 560 4016','ZA-CORP-9016','2023-07-01','2025-03-31','2025-03-22',22000,'Lease expired'),
                         $this->tc('Sanlam','umhlanga@sanlam.co.za','+27 31 560 3008','ZA-CORP-8108','2025-04-01','2027-03-31',25000)]],
            ['number'=>'URO-09','occupancy'=>'occupant_occupied','debtor'=>true,'debtor_from'=>8,'rent_amount'=>30000,
             'owner'=>$this->o('Fairvest Capital Ltd','property@fairvest.co.za','+27 31 561 1009','ZA-CORP-8009','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Mr Price Group','admin@mrprice.co.za','+27 31 560 4017','ZA-CORP-9017','2022-08-01','2024-07-31','2024-07-25',22000,'Left in good standing'),
                         $this->t('TFG Holdings','ops@tfg.co.za','+27 31 560 4018','ZA-CORP-9018','2024-08-01','2025-03-31','2025-03-22',26000,'Lease not renewed'),
                         $this->tc('Momentum','umhlanga@momentum.co.za','+27 31 560 3009','ZA-CORP-8109','2025-04-01','2027-03-31',30000)]],
            ['number'=>'URO-10','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('SA Corporate RE Ltd','property@sacorp.co.za','+27 31 561 1010','ZA-CORP-8010','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Pick n Pay','admin@picknpay.co.za','+27 31 560 4019','ZA-CORP-9019','2022-10-01','2024-09-30','2024-09-25',22000,'Left in good standing'),
                         $this->t('Shoprite Holdings','ops@shoprite.co.za','+27 31 560 4020','ZA-CORP-9020','2024-10-01','2025-04-30','2025-04-22',25000,'Lease expired'),
                         $this->tc('Deloitte SA','umhlanga@deloitte.co.za','+27 31 560 3010','ZA-CORP-8110','2025-05-01','2028-04-30',28000)]],
            ['number'=>'URO-11','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>35000,
             'owner'=>$this->o('Octodec Investments','property@octodec.co.za','+27 31 561 1011','ZA-CORP-8011','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Woolworths Holdings','admin@woolworths.co.za','+27 31 560 4021','ZA-CORP-9021','2022-06-01','2024-05-31','2024-05-25',26000,'Left in good standing'),
                         $this->t('Massmart Holdings','ops@massmart.co.za','+27 31 560 4022','ZA-CORP-9022','2024-06-01','2025-04-30','2025-04-20',30000,'Lease not renewed'),
                         $this->tc('KPMG South Africa','umhlanga@kpmg.co.za','+27 31 560 3011','ZA-CORP-8111','2025-05-01','2028-04-30',35000)]],
            ['number'=>'URO-12','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>25000,
             'owner'=>$this->o('Emira Property Fund','property@emira.co.za','+27 31 561 1012','ZA-CORP-8012','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Sasol Limited','admin@sasol.co.za','+27 31 560 4023','ZA-CORP-9023','2022-11-01','2024-10-31','2024-10-25',19000,'Left in good standing'),
                         $this->t('Nandos SA Head Office','ops@nandos2.co.za','+27 31 560 4024','ZA-CORP-9024','2024-11-01','2025-05-31','2025-05-20',22000,'Lease expired'),
                         $this->tc('PwC South Africa','umhlanga@pwc.co.za','+27 31 560 3012','ZA-CORP-8112','2025-06-01','2028-05-31',25000)]],
            ['number'=>'URO-13','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Arrowhead Properties','property@arrowhead.co.za','+27 31 561 1013','ZA-CORP-8013','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Steers Holdings SA','admin@steers2.co.za','+27 31 560 4025','ZA-CORP-9025','2022-04-01','2024-03-31','2024-03-25',21000,'Left in good standing'),
                         $this->t('FNB Life','ops@fnblife.co.za','+27 31 560 4026','ZA-CORP-9026','2024-04-01','2025-05-31','2025-05-22',25000,'Lease not renewed'),
                         $this->tc('Dimension Data','umhlanga@didata.co.za','+27 31 560 3013','ZA-CORP-8113','2025-06-01','2028-05-31',28000)]],
            ['number'=>'URO-14','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Delta Property Fund','property@delta.co.za','+27 31 561 1014','ZA-CORP-8014','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Liberty Life SA','admin@libertylife.co.za','+27 31 560 4027','ZA-CORP-9027','2022-12-01','2024-11-30','2024-11-25',17000,'Relocated'),
                         $this->t('Datatec SA','ops@datatec2.co.za','+27 31 560 4028','ZA-CORP-9028','2024-12-01','2025-06-30','2025-06-22',20000,'Lease expired'),
                         $this->tc('EY South Africa','umhlanga@ey.co.za','+27 31 560 3014','ZA-CORP-8114','2025-07-01','2028-06-30',22000)]],
            ['number'=>'URO-15','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>30000,
             'owner'=>$this->o('Transcend Property Ltd','property@transcend.co.za','+27 31 561 1015','ZA-CORP-8015','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Altron SA','admin@altron2.co.za','+27 31 560 4029','ZA-CORP-9029','2022-07-01','2024-06-30','2024-06-25',23000,'Left in good standing'),
                         $this->t('Sappi SA','ops@sappi2.co.za','+27 31 560 4030','ZA-CORP-9030','2024-07-01','2025-06-30','2025-06-20',27000,'Lease not renewed'),
                         $this->tc('Clicks Group','umhlanga@clicks.co.za','+27 31 560 3015','ZA-CORP-8115','2025-07-01','2028-06-30',30000)]],
            ['number'=>'URO-16','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>28000,
             'owner'=>$this->o('Fortress REIT Ltd','property@fortress.co.za','+27 31 561 1016','ZA-CORP-8016','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Tiger Brands SA','admin@tigerbrands2.co.za','+27 31 560 4031','ZA-CORP-9031','2022-09-01','2024-08-31','2024-08-22',21000,'Left in good standing'),
                         $this->t('Pioneer Foods SA','ops@pioneer2.co.za','+27 31 560 4032','ZA-CORP-9032','2024-09-01','2025-07-31','2025-07-25',25000,'Lease expired'),
                         $this->tc('Multichoice','umhlanga@multichoice.co.za','+27 31 560 3016','ZA-CORP-8116','2025-08-01','2028-07-31',28000)]],
            ['number'=>'URO-17','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>25000,
             'owner'=>$this->o('Rebosis Property Fund','property@rebosis.co.za','+27 31 561 1017','ZA-CORP-8017','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('RCL Foods SA','admin@rclfoods2.co.za','+27 31 560 4033','ZA-CORP-9033','2022-05-01','2024-04-30','2024-04-22',19000,'Left in good standing'),
                         $this->t('Massmart SA','ops@massmart2.co.za','+27 31 560 4034','ZA-CORP-9034','2024-05-01','2025-04-30','2025-04-20',22000,'Lease not renewed'),
                         $this->tc('FNB South Africa','umhlanga@fnb.co.za','+27 31 560 3017','ZA-CORP-8117','2025-05-01','2027-04-30',25000)]],
            ['number'=>'URO-18','occupancy'=>'occupant_occupied','debtor'=>false,'rent_amount'=>22000,
             'owner'=>$this->o('Accelerate Property Ltd','property@accelerate.co.za','+27 31 561 1018','ZA-CORP-8018','10 Umhlanga Ridge Blvd, Umhlanga'),
             'organizations'=>[$this->t('Shoprite SA','admin@shoprite2.co.za','+27 31 560 4035','ZA-CORP-9035','2022-08-01','2024-07-31','2024-07-25',17000,'Left in good standing'),
                         $this->t('Pick n Pay SA','ops@pnp2.co.za','+27 31 560 4036','ZA-CORP-9036','2024-08-01','2025-03-31','2025-03-22',20000,'Lease expired'),
                         $this->tc('Altron','umhlanga@altron.co.za','+27 31 560 3018','ZA-CORP-8118','2025-04-01','2027-03-31',22000)]],
            // Vacant (2)
            ['number'=>'URO-19','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Indluplace Properties','property@indluplace.co.za','+27 31 561 1019','ZA-CORP-8019','10 Umhlanga Ridge Blvd, Umhlanga')],
            ['number'=>'URO-20','occupancy'=>'vacant','debtor'=>false,'owner'=>$this->o('Stor-Age Property REIT','property@storage.co.za','+27 31 561 1020','ZA-CORP-8020','10 Umhlanga Ridge Blvd, Umhlanga')],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  COMPLIANCE TEMPLATES                                                */
    /* ------------------------------------------------------------------ */

    private function seedComplianceTemplates(): void
    {
        $this->command?->info('Seeding compliance templates...');

        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            $this->seedComplianceTemplatesForOrganization($organization);
        }
    }

    private function seedComplianceTemplatesForOrganization(Organization $organization): void
    {
        // Skip if templates already exist for this organization
        if (ComplianceTemplate::where('organization_id', $organization->id)->exists()) {
            return;
        }

        // ── Botswana Sectional Title ────────────────────────────────
        $this->createComplianceTemplate($organization, [
            'name'        => 'Sectional Title — Botswana',
            'description' => 'Standard annual compliance checklist for body corporate schemes in Botswana under the Sectional Titles Act.',
            'country'     => 'BW',
            'is_default'  => true,
            'is_system'   => true,
            'items'       => [
                ['name' => 'Annual General Meeting (AGM) held', 'category' => 'Governance', 'description' => 'AGM must be held within 4 months of financial year-end. All owners must be notified at least 14 days in advance.', 'priority' => 'critical', 'default_month_due' => 4, 'sort_order' => 1],
                ['name' => 'AGM minutes prepared and distributed', 'category' => 'Governance', 'description' => 'Minutes must be circulated to all owners within 30 days of the AGM.', 'priority' => 'high', 'default_month_due' => 5, 'sort_order' => 2],
                ['name' => 'Trustees elected / confirmed', 'category' => 'Governance', 'description' => 'Board of trustees must be elected or re-confirmed at the AGM. Minimum 2 trustees required.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 3],
                ['name' => 'Trustee meeting schedule set for year', 'category' => 'Governance', 'description' => 'Set regular meeting dates for the board of trustees (quarterly recommended).', 'priority' => 'medium', 'default_month_due' => 5, 'sort_order' => 4],
                ['name' => 'Management rules reviewed', 'category' => 'Governance', 'description' => 'Review and update management and conduct rules as needed. Any amendments require special resolution at AGM.', 'priority' => 'low', 'default_month_due' => 3, 'sort_order' => 5],
                ['name' => 'Annual budget prepared and approved', 'category' => 'Financial', 'description' => 'The annual budget must be prepared and presented for approval at the AGM. Budget should cover all estimated operational expenses.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 6],
                ['name' => 'Levy schedule issued to owners', 'category' => 'Financial', 'description' => 'Once budget is approved, issue updated levy schedule showing each unit\'s monthly contribution.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 7],
                ['name' => 'Audited financial statements prepared', 'category' => 'Financial', 'description' => 'Annual financial statements must be audited by a registered accountant and presented at the AGM.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 8],
                ['name' => 'Reserve fund / maintenance fund reviewed', 'category' => 'Financial', 'description' => 'Review the adequacy of the reserve fund for major maintenance and capital expenditure. 10-year maintenance plan recommended.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 9],
                ['name' => 'Arrears recovery plan in place', 'category' => 'Financial', 'description' => 'Review outstanding levies and ensure a formal arrears recovery process is being followed for delinquent owners.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 10],
                ['name' => 'Building insurance renewed', 'category' => 'Insurance', 'description' => 'Building insurance policy must be renewed annually. Coverage must include the full replacement value of the common property.', 'priority' => 'critical', 'default_month_due' => 1, 'sort_order' => 11],
                ['name' => 'Insurance valuation updated', 'category' => 'Insurance', 'description' => 'Obtain a professional replacement cost valuation at least every 3 years. Review annually for adequacy.', 'priority' => 'medium', 'default_month_due' => 1, 'sort_order' => 12],
                ['name' => 'Fidelity guarantee cover confirmed', 'category' => 'Insurance', 'description' => 'Confirm that fidelity guarantee insurance is in place to protect the body corporate against fraud or misappropriation.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 13],
                ['name' => 'Managing agent agreement reviewed', 'category' => 'Legal', 'description' => 'Review the managing agent service agreement. Confirm scope of services, fees, and performance KPIs.', 'priority' => 'medium', 'default_month_due' => 11, 'sort_order' => 14],
                ['name' => 'Registered owner records updated', 'category' => 'Legal', 'description' => 'Verify that the body corporate\'s register of owners is current and matches Deeds Office records.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 15],
                ['name' => 'Tax compliance confirmed (BURS)', 'category' => 'Legal', 'description' => 'Ensure the body corporate is compliant with BURS (Botswana Unified Revenue Service) requirements for tax filing.', 'priority' => 'high', 'default_month_due' => 9, 'sort_order' => 16],
                ['name' => 'Building condition inspection completed', 'category' => 'Maintenance', 'description' => 'Conduct an annual walk-through inspection of common property areas, noting maintenance issues and safety hazards.', 'priority' => 'medium', 'default_month_due' => 2, 'sort_order' => 17],
                ['name' => 'Fire safety equipment serviced', 'category' => 'Maintenance', 'description' => 'Service all fire extinguishers, hose reels, and fire alarm systems. Ensure compliance with fire safety regulations.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 18],
                ['name' => '10-year maintenance plan reviewed', 'category' => 'Maintenance', 'description' => 'Review and update the long-term maintenance plan covering roof, plumbing, electrical, elevators, and structural elements.', 'priority' => 'medium', 'default_month_due' => 3, 'sort_order' => 19],
            ],
        ]);

        // ── South Africa Sectional Title ────────────────────────────
        $this->createComplianceTemplate($organization, [
            'name'        => 'Sectional Title — South Africa',
            'description' => 'Standard annual compliance checklist for body corporate schemes in South Africa under the Sectional Titles Schemes Management Act (STSMA) and Community Schemes Ombud Service Act.',
            'country'     => 'ZA',
            'is_default'  => true,
            'is_system'   => true,
            'items'       => [
                ['name' => 'Annual General Meeting (AGM) held', 'category' => 'Governance', 'description' => 'AGM must be held within 4 months of financial year-end per STSMA. All owners must receive 14 days written notice.', 'priority' => 'critical', 'default_month_due' => 4, 'sort_order' => 1],
                ['name' => 'AGM minutes prepared and distributed', 'category' => 'Governance', 'description' => 'Minutes must be circulated to all owners. Keep on record for inspection per STSMA requirements.', 'priority' => 'high', 'default_month_due' => 5, 'sort_order' => 2],
                ['name' => 'Trustees elected / confirmed', 'category' => 'Governance', 'description' => 'Trustees must be elected at AGM. Minimum of 2 trustees. Trustees serve until next AGM unless removed by special resolution.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 3],
                ['name' => 'Trustee meeting schedule set for year', 'category' => 'Governance', 'description' => 'Trustees must meet at least once per quarter. Set the annual calendar after AGM.', 'priority' => 'medium', 'default_month_due' => 5, 'sort_order' => 4],
                ['name' => 'Conduct rules reviewed and updated', 'category' => 'Governance', 'description' => 'Review prescribed conduct rules (PMR 1 Annexure 2) and any body corporate specific rules. Updates require trustee resolution.', 'priority' => 'low', 'default_month_due' => 6, 'sort_order' => 5],
                ['name' => 'Annual budget prepared and approved', 'category' => 'Financial', 'description' => 'Budget must be approved at AGM per STSMA s3(1)(b). Must include administrative and reserve fund contributions.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 6],
                ['name' => 'Levy schedule issued to owners', 'category' => 'Financial', 'description' => 'Issue levy invoices per approved budget. Levies are proportional to participation quota per STSMA.', 'priority' => 'high', 'default_month_due' => 4, 'sort_order' => 7],
                ['name' => 'Audited financial statements prepared', 'category' => 'Financial', 'description' => 'Annual financial statements must be independently audited and presented at AGM as required by STSMA.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 8],
                ['name' => 'Reserve fund adequacy reviewed', 'category' => 'Financial', 'description' => 'STSMA requires a reserve fund. Review the 10-year maintenance plan to ensure contributions are adequate. PMR 24(4) requires minimum balance.', 'priority' => 'critical', 'default_month_due' => 3, 'sort_order' => 9],
                ['name' => 'Arrears recovery plan in place', 'category' => 'Financial', 'description' => 'Follow the body corporate arrears collection policy. Issue letters of demand, then hand over to attorneys if unresolved per arrears protocol.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 10],
                ['name' => 'Building insurance renewed', 'category' => 'Insurance', 'description' => 'STSMA s3(1)(h) mandates insurance for replacement value of buildings and common property against fire, storm, earthquake, etc.', 'priority' => 'critical', 'default_month_due' => 1, 'sort_order' => 11],
                ['name' => 'Insurance valuation updated', 'category' => 'Insurance', 'description' => 'Professional replacement cost valuation required every 3 years. Must reflect current building costs.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 12],
                ['name' => 'Fidelity guarantee cover confirmed', 'category' => 'Insurance', 'description' => 'PMR 2(1)(a) requires fidelity guarantee insurance covering persons who handle body corporate funds.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 13],
                ['name' => 'Public liability insurance confirmed', 'category' => 'Insurance', 'description' => 'Ensure adequate public liability cover is in place for common property areas.', 'priority' => 'high', 'default_month_due' => 1, 'sort_order' => 14],
                ['name' => 'CSOS levy return filed', 'category' => 'Legal', 'description' => 'File the annual Community Schemes Ombud Service (CSOS) levy return. The body corporate must be registered with CSOS.', 'priority' => 'critical', 'default_month_due' => 5, 'sort_order' => 15],
                ['name' => 'CSOS levy paid', 'category' => 'Legal', 'description' => 'Pay the CSOS levy as prescribed. Non-payment can result in penalties and sanctions.', 'priority' => 'critical', 'default_month_due' => 6, 'sort_order' => 16],
                ['name' => 'Managing agent agreement reviewed', 'category' => 'Legal', 'description' => 'Review the managing agent agreement. STSMA s7 requires trustees to oversee the managing agent.', 'priority' => 'medium', 'default_month_due' => 11, 'sort_order' => 17],
                ['name' => 'Registered owner records updated', 'category' => 'Legal', 'description' => 'Verify owner register against the Deeds Office. PMR 3(1)(d) requires the body corporate to keep an up-to-date register.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 18],
                ['name' => 'Tax compliance confirmed (SARS)', 'category' => 'Legal', 'description' => 'Ensure the body corporate is registered with SARS and compliant with income tax obligations (if applicable) and VAT if turnover exceeds thresholds.', 'priority' => 'high', 'default_month_due' => 9, 'sort_order' => 19],
                ['name' => 'Building condition inspection completed', 'category' => 'Maintenance', 'description' => 'Conduct annual inspection of all common property areas. Document findings and prioritise repairs.', 'priority' => 'medium', 'default_month_due' => 2, 'sort_order' => 20],
                ['name' => 'Fire safety equipment serviced', 'category' => 'Maintenance', 'description' => 'Service fire extinguishers, hose reels, alarms per SANS 10400-T. Keep certificates on file.', 'priority' => 'high', 'default_month_due' => 6, 'sort_order' => 21],
                ['name' => '10-year maintenance, repair and replacement plan reviewed', 'category' => 'Maintenance', 'description' => 'PMR 22(3) requires a 10-year plan. Review and update annually, adjusting reserve fund contributions accordingly.', 'priority' => 'high', 'default_month_due' => 3, 'sort_order' => 22],
                ['name' => 'Electrical compliance certificate (COC) valid', 'category' => 'Maintenance', 'description' => 'Ensure electrical COC for common property is valid. Required for insurance claims and sale of units.', 'priority' => 'medium', 'default_month_due' => 6, 'sort_order' => 23],
            ],
        ]);

        // ── Generic / Custom Template ───────────────────────────────
        $this->createComplianceTemplate($organization, [
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

    private function createComplianceTemplate(Organization $organization, array $data): void
    {
        $template = ComplianceTemplate::create([
            'name'            => $data['name'],
            'description'     => $data['description'],
            'country'         => $data['country'],
            'is_default'      => $data['is_default'],
            'is_system'       => $data['is_system'],
            'organization_id' => $organization->id,
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
