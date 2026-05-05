<?php

namespace App\Console\Commands;

use App\Jobs\SendInvoiceEmail;
use App\Models\Estate;
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

    protected $description = 'Auto-generate invoices for estates whose billing day falls today, then queue invoice emails.';

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

        $estates = $this->estatesDueToday($today, $onlyId);

        if ($estates->isEmpty()) {
            $this->info('No estates due for billing today.');
            return self::SUCCESS;
        }

        $this->info("Found {$estates->count()} estate(s) due for billing.");

        $totalInvoices = 0;
        $totalEmails   = 0;
        $errors        = 0;

        foreach ($estates as $estate) {
            try {
                [$invoices, $emails] = $this->procesEstate($estate, $today, $isDryRun);
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

    private function procesEstate(Estate $estate, Carbon $today, bool $isDryRun): array
    {
        $billingPeriod = $today->format('Y-m');
        $actor         = $this->actorForOrg($estate->organization_id);

        if (!$actor) {
            throw new \RuntimeException("No active admin user found for organization {$estate->organization_id}.");
        }

        $this->line("  → [{$estate->name}] billing period {$billingPeriod}");

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

    private function estatesDueToday(Carbon $today, ?string $onlyId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Estate::active()->with('organization');

        if ($onlyId) {
            return $query->where('id', $onlyId)->get();
        }

        // Collect all active estates and filter in PHP.
        // billing_day=31 fires on the last day of months with fewer days.
        return $query->get()->filter(function (Estate $estate) use ($today): bool {
            $billingDay  = (int) $estate->billing_day;
            $daysInMonth = $today->daysInMonth;

            // Normal match OR overflow (e.g. billing_day=31 in a 30-day month → fires on day 30)
            return $billingDay === $today->day
                || ($billingDay > $daysInMonth && $today->day === $daysInMonth);
        });
    }

    private function actorForOrg(string $organizationId): ?User
    {
        return User::where('organization_id', $organizationId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'company-admin')->where('guard_name', 'api'))
            ->first();
    }
}
