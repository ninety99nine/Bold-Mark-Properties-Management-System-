<?php

namespace App\Console\Commands;

use App\Jobs\SendInvoiceEmail;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScheduledBilling extends Command
{
    protected $signature = 'billing:run-scheduled
                            {--dry-run : Preview invoices that would be created without saving}
                            {--estate= : Run for a specific estate ID only}';

    protected $description = 'Auto-generate invoices for estates due today or missed this month, then queue invoice emails.';

    public function __construct(private readonly InvoiceService $invoiceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today    = Carbon::today(config('app.timezone', 'Africa/Johannesburg'));
        $isDryRun = (bool) $this->option('dry-run');
        $onlyId   = $this->option('estate');

        $this->info(sprintf(
            '[billing:run-scheduled] %s%s',
            $today->toDateString(),
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        $estates = $this->estatesDueForBilling($today, $onlyId);

        if ($estates->isEmpty()) {
            $this->info('No estates due for billing today.');
            return self::SUCCESS;
        }

        // Separate on-time from catch-up so the log is clear.
        $onTime  = $estates->filter(fn ($e) => $this->effectiveBillingDay($e, $today) === $today->day);
        $catchUp = $estates->filter(fn ($e) => $this->effectiveBillingDay($e, $today) !== $today->day);

        $this->info("Found {$estates->count()} estate(s) due for billing "
            . "(on-time: {$onTime->count()}, catch-up: {$catchUp->count()}).");

        $totalInvoices = 0;
        $totalEmails   = 0;
        $errors        = 0;

        foreach ($estates as $estate) {
            $isCatchUp = $this->effectiveBillingDay($estate, $today) !== $today->day;
            try {
                [$invoices, $emails] = $this->processEstate($estate, $today, $isDryRun, $isCatchUp);
                $totalInvoices += $invoices;
                $totalEmails   += $emails;
            } catch (Throwable $e) {
                $errors++;
                $this->error("  ✗ [{$estate->name}] {$e->getMessage()}");
                Log::error("billing:run-scheduled estate={$estate->id} error={$e->getMessage()}", [
                    'estate_id' => $estate->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Invoices created: {$totalInvoices} | Emails queued: {$totalEmails} | Errors: {$errors}");

        Log::info('billing:run-scheduled completed', [
            'date'           => $today->toDateString(),
            'dry_run'        => $isDryRun,
            'estates'        => $estates->count(),
            'invoices'       => $totalInvoices,
            'emails_queued'  => $totalEmails,
            'errors'         => $errors,
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processEstate(Estate $estate, Carbon $today, bool $isDryRun, bool $isCatchUp = false): array
    {
        $billingPeriod = $today->format('Y-m');
        $actor         = $this->actorForOrg($estate->organization_id);

        if (!$actor) {
            throw new \RuntimeException("No active admin user found for organization {$estate->organization_id}.");
        }

        $tag = $isCatchUp ? '[CATCH-UP]' : '[ON-TIME]';
        $this->line("  → {$tag} [{$estate->name}] billing period {$billingPeriod}");

        $result = $this->invoiceService->runBillingForEstate(
            $estate,
            $billingPeriod,
            $isDryRun,
            $actor
        );

        $created = $result['created'];
        $this->line("    invoices created: {$created}");

        if ($isDryRun || $created === 0) {
            return [$created, 0];
        }

        // Dispatch one queued job per invoice so Horizon handles retries individually.
        $emailsQueued = 0;
        foreach ($result['created_ids'] as $invoiceId) {
            SendInvoiceEmail::dispatch($invoiceId)->onQueue('emails');
            $emailsQueued++;
        }

        $this->line("    emails queued:    {$emailsQueued}");

        return [$created, $emailsQueued];
    }

    /**
     * Return estates that need billing today:
     *   1. On-time  — billing_day == today (normal case).
     *   2. Catch-up — billing_day already passed this month but no invoices
     *                 were ever created for this period (system was down).
     *
     * The duplicate check inside runBillingForEstate guarantees idempotency,
     * so running catch-up on an estate that already billed is always safe.
     */
    private function estatesDueForBilling(Carbon $today, ?string $onlyId): \Illuminate\Support\Collection
    {
        $query = Estate::active()->with('organization');

        if ($onlyId) {
            return $query->where('id', $onlyId)->get();
        }

        $billingPeriodDate = $today->copy()->startOfMonth()->format('Y-m-d');

        return $query->get()->filter(function (Estate $estate) use ($today, $billingPeriodDate): bool {
            $effective = $this->effectiveBillingDay($estate, $today);

            // Billing day hasn't arrived yet this month — skip entirely.
            if ($effective > $today->day) {
                return false;
            }

            // On-time: billing day is exactly today — always run.
            if ($effective === $today->day) {
                return true;
            }

            // Catch-up: billing day already passed — only run if the period
            // has no invoices yet (i.e. the system missed the scheduled run).
            return !Invoice::whereHas(
                'unit',
                fn ($q) => $q->where('estate_id', $estate->id)
            )->where('billing_period', $billingPeriodDate)->exists();
        });
    }

    /**
     * Clamp billing_day to the actual number of days in the current month.
     * e.g. billing_day=31 → 30 in April, 28/29 in February.
     */
    private function effectiveBillingDay(Estate $estate, Carbon $today): int
    {
        return min((int) $estate->billing_day, $today->daysInMonth);
    }

    private function actorForOrg(string $organizationId): ?User
    {
        return User::where('organization_id', $organizationId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'company-admin')->where('guard_name', 'api'))
            ->first();
    }
}
