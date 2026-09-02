<?php

namespace App\Providers;

use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Ledger;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceTemplate;
use App\Models\Community;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\RiskRule;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\UnitLedgerConfig;
use App\Models\Occupant;
use App\Models\User;
use App\Policies\BankAccountPolicy;
use App\Policies\CashbookEntryPolicy;
use App\Policies\LedgerPolicy;
use App\Policies\ComplianceChecklistItemPolicy;
use App\Policies\ComplianceChecklistPolicy;
use App\Policies\ComplianceTemplatePolicy;
use App\Policies\CommunityPolicy;
use App\Policies\CustomerGroupPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\RiskRulePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UnitLedgerConfigPolicy;
use App\Policies\UnitPolicy;
use App\Policies\OccupantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRouteModelBindings();
        $this->registerJobRateLimiters();

        // Portable case-insensitive LIKE — works on SQLite, MySQL, and PostgreSQL.
        \Illuminate\Database\Eloquent\Builder::macro('whereLike', function (string $column, string $value) {
            return $this->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($value) . '%']);
        });
        \Illuminate\Database\Eloquent\Builder::macro('orWhereLike', function (string $column, string $value) {
            return $this->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($value) . '%']);
        });
        \Illuminate\Database\Query\Builder::macro('whereLike', function (string $column, string $value) {
            return $this->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($value) . '%']);
        });
        \Illuminate\Database\Query\Builder::macro('orWhereLike', function (string $column, string $value) {
            return $this->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($value) . '%']);
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            return $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Reset your BoldMark PMS password')
                ->view('emails.reset-password', [
                    'name'     => $notifiable->name,
                    'resetUrl' => $resetUrl,
                ]);
        });
    }

    protected function registerJobRateLimiters(): void
    {
        // Global Resend rate limit: 4/sec across all workers and billing runs.
        RateLimiter::for('resend-emails', fn() => Limit::perSecond(4));
    }

    /**
     * Register all model policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Ledger::class, LedgerPolicy::class);
        Gate::policy(Community::class, CommunityPolicy::class);
        Gate::policy(CustomerGroup::class, CustomerGroupPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Owner::class, OwnerPolicy::class);
        Gate::policy(Occupant::class, OccupantPolicy::class);
        Gate::policy(UnitLedgerConfig::class, UnitLedgerConfigPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(\App\Models\CreditNote::class, \App\Policies\CreditNotePolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(CashbookEntry::class, CashbookEntryPolicy::class);
        Gate::policy(RiskRule::class, RiskRulePolicy::class);
        Gate::policy(ComplianceChecklist::class, ComplianceChecklistPolicy::class);
        Gate::policy(ComplianceChecklistItem::class, ComplianceChecklistItemPolicy::class);
        Gate::policy(ComplianceTemplate::class, ComplianceTemplatePolicy::class);
        Gate::policy(\App\Models\JournalBatch::class, \App\Policies\JournalBatchPolicy::class);
        Gate::policy(\App\Models\Supplier::class, \App\Policies\SupplierPolicy::class);
        Gate::policy(\App\Models\SupplierInvoice::class, \App\Policies\SupplierInvoicePolicy::class);
        Gate::policy(\App\Models\SupplierGroup::class, \App\Policies\SupplierGroupPolicy::class);
        Gate::policy(\App\Models\AllocationRule::class, \App\Policies\AllocationRulePolicy::class);
        Gate::policy(\App\Models\SplitTemplate::class, \App\Policies\SplitTemplatePolicy::class);
        Gate::policy(\App\Models\JournalGroup::class, \App\Policies\JournalGroupPolicy::class);
        Gate::policy(\App\Models\CommunityBudget::class, \App\Policies\CommunityBudgetPolicy::class);
    }

    /**
     * Register explicit route model bindings.
     * These ensure route parameters resolve to the correct Eloquent models.
     */
    protected function registerRouteModelBindings(): void
    {
        Route::model('organization', Organization::class);
        Route::model('community', Community::class);
        Route::model('unit', Unit::class);
        Route::model('owner', Owner::class);
        Route::model('customerGroup', CustomerGroup::class);
        Route::model('occupant', Occupant::class);
        Route::model('ledger', Ledger::class);
        Route::model('ledgerConfig', UnitLedgerConfig::class);
        // Resolves {invoice} — includes soft-deleted so the detail page can show deleted invoices
        Route::bind('invoice', function (string $value) {
            return Invoice::withTrashed()->findOrFail($value);
        });

        Route::model('creditNote', \App\Models\CreditNote::class);
        Route::model('journalBatch', \App\Models\JournalBatch::class);

        // Resolves {deletedInvoice} route parameters — includes soft-deleted records
        Route::bind('deletedInvoice', function (string $value) {
            return Invoice::withTrashed()->findOrFail($value);
        });
        Route::model('cashbookEntry', CashbookEntry::class);
        Route::model('riskRule', RiskRule::class);
        Route::model('complianceChecklist', ComplianceChecklist::class);
        Route::model('complianceChecklistItem', ComplianceChecklistItem::class);
        Route::model('complianceTemplate', ComplianceTemplate::class);
        Route::model('user', User::class);
        Route::model('communication', \App\Models\UnitCommunication::class);
        Route::model('offence', \App\Models\UnitOffence::class);
        Route::model('task', \App\Models\UnitTask::class);
        Route::model('note', \App\Models\UnitCollectionNote::class);
        Route::model('document', \App\Models\UnitDocument::class);
        Route::model('member', \App\Models\CommunityMember::class);
        Route::model('communicationLog', \App\Models\Communication::class);
        Route::model('messageTemplate', \App\Models\MessageTemplate::class);
        Route::model('ledgerReport', \App\Models\LedgerReportBatch::class);
        Route::model('bankAccount', BankAccount::class);
        Route::model('supplier', \App\Models\Supplier::class);
        Route::model('supplierInvoice', \App\Models\SupplierInvoice::class);
        Route::model('supplierGroup', \App\Models\SupplierGroup::class);
        Route::model('supplierDocument', \App\Models\SupplierDocument::class);
        Route::model('allocationRule', \App\Models\AllocationRule::class);
        Route::model('splitTemplate', \App\Models\SplitTemplate::class);
        Route::model('journalGroup', \App\Models\JournalGroup::class);
    }
}
