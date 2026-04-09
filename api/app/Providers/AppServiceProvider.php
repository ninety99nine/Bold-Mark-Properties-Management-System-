<?php

namespace App\Providers;

use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitChargeConfig;
use App\Models\UnitTenant;
use App\Models\User;
use App\Policies\CashbookEntryPolicy;
use App\Policies\ChargeTypePolicy;
use App\Policies\EstatePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitChargeConfigPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UnitTenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
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

    /**
     * Register all model policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ChargeType::class, ChargeTypePolicy::class);
        Gate::policy(Estate::class, EstatePolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Owner::class, OwnerPolicy::class);
        Gate::policy(UnitTenant::class, UnitTenantPolicy::class);
        Gate::policy(UnitChargeConfig::class, UnitChargeConfigPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(CashbookEntry::class, CashbookEntryPolicy::class);
    }

    /**
     * Register explicit route model bindings.
     * These ensure route parameters resolve to the correct Eloquent models.
     */
    protected function registerRouteModelBindings(): void
    {
        Route::model('tenant', Tenant::class);
        Route::model('estate', Estate::class);
        Route::model('unit', Unit::class);
        Route::model('owner', Owner::class);
        Route::model('unitTenant', UnitTenant::class);
        Route::model('chargeType', ChargeType::class);
        Route::model('chargeConfig', UnitChargeConfig::class);
        // Resolves {invoice} — includes soft-deleted so the detail page can show deleted invoices
        Route::bind('invoice', function (string $value) {
            return Invoice::withTrashed()->findOrFail($value);
        });

        // Resolves {deletedInvoice} route parameters — includes soft-deleted records
        Route::bind('deletedInvoice', function (string $value) {
            return Invoice::withTrashed()->findOrFail($value);
        });
        Route::model('cashbookEntry', CashbookEntry::class);
        Route::model('user', User::class);
    }
}
