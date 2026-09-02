<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Jobs\SendPaymentReminderEmail;
use App\Models\Community;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentReminders extends Command
{
    protected $signature = 'billing:send-payment-reminders
                            {--dry-run : Preview reminders that would be sent without dispatching}
                            {--community= : Run for a specific community ID only}';

    protected $description = 'Send payment reminders for unpaid invoices past their due date, per each community\'s reminder settings.';

    public function handle(): int
    {
        $today    = Carbon::today(config('app.timezone', 'Africa/Johannesburg'));
        $isDryRun = (bool) $this->option('dry-run');
        $onlyId   = $this->option('community');

        $this->info(sprintf(
            '[billing:send-payment-reminders] %s%s',
            $today->toDateString(),
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        // Only communities with a configured reminder threshold and billing active.
        $query = Community::active()
            ->where('billing_paused', false)
            ->whereNotNull('payment_reminder_days');

        if ($onlyId) {
            $query->where('id', $onlyId);
        }

        $communities = $query->get();

        if ($communities->isEmpty()) {
            $this->info('No communities have payment reminders configured.');
            return self::SUCCESS;
        }

        $totalReminders = 0;
        $errors         = 0;

        foreach ($communities as $community) {
            try {
                $sent = $this->processCommunity($community, $today, $isDryRun);
                $totalReminders += $sent;
            } catch (Throwable $e) {
                $errors++;
                $this->error("  ✗ [{$community->name}] {$e->getMessage()}");
                Log::error("billing:send-payment-reminders community={$community->id} error={$e->getMessage()}", [
                    'community_id' => $community->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Reminders queued: {$totalReminders} | Errors: {$errors}");

        Log::info('billing:send-payment-reminders completed', [
            'date'             => $today->toDateString(),
            'dry_run'          => $isDryRun,
            'communities'          => $communities->count(),
            'reminders_queued' => $totalReminders,
            'errors'           => $errors,
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processCommunity(Community $community, Carbon $today, bool $isDryRun): int
    {
        // Threshold date: invoices due on or before this date are eligible.
        $thresholdDate = $today->copy()->subDays($community->payment_reminder_days);

        // Find unpaid invoices for this community:
        //   - due date <= threshold (i.e. overdue by at least payment_reminder_days)
        //   - no reminder sent yet
        //   - not paid / cancelled
        $invoices = Invoice::whereHas(
            'unit',
            fn ($q) => $q->where('community_id', $community->id)
        )
        ->whereIn('status', [
            InvoiceStatus::UNPAID,
            InvoiceStatus::OVERDUE,
            InvoiceStatus::PARTIALLY_PAID,
        ])
        ->where('due_date', '<=', $thresholdDate->toDateString())
        ->whereNull('reminder_sent_at')
        ->get();

        if ($invoices->isEmpty()) {
            $this->line("  → [{$community->name}] no invoices due for reminders");
            return 0;
        }

        $this->line("  → [{$community->name}] {$invoices->count()} reminder(s) to send (>{$community->payment_reminder_days} days overdue)");

        if ($isDryRun) {
            foreach ($invoices as $invoice) {
                $this->line("    [DRY RUN] would remind: {$invoice->invoice_number} (due {$invoice->due_date->toDateString()})");
            }
            return $invoices->count();
        }

        $queued = 0;
        foreach ($invoices as $invoice) {
            SendPaymentReminderEmail::dispatch($invoice->id)->onQueue('emails');
            $queued++;
            $this->line("    queued reminder: {$invoice->invoice_number}");
        }

        return $queued;
    }
}
