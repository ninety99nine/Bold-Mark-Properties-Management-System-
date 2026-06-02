<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Jobs\SendPaymentReminderEmail;
use App\Models\Estate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentReminders extends Command
{
    protected $signature = 'billing:send-payment-reminders
                            {--dry-run : Preview reminders that would be sent without dispatching}
                            {--estate= : Run for a specific estate ID only}';

    protected $description = 'Send payment reminders for unpaid invoices past their due date, per each estate\'s reminder settings.';

    public function handle(): int
    {
        $today    = Carbon::today(config('app.timezone', 'Africa/Johannesburg'));
        $isDryRun = (bool) $this->option('dry-run');
        $onlyId   = $this->option('estate');

        $this->info(sprintf(
            '[billing:send-payment-reminders] %s%s',
            $today->toDateString(),
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        // Only estates with a configured reminder threshold and billing active.
        $query = Estate::active()
            ->where('billing_paused', false)
            ->whereNotNull('payment_reminder_days');

        if ($onlyId) {
            $query->where('id', $onlyId);
        }

        $estates = $query->get();

        if ($estates->isEmpty()) {
            $this->info('No estates have payment reminders configured.');
            return self::SUCCESS;
        }

        $totalReminders = 0;
        $errors         = 0;

        foreach ($estates as $estate) {
            try {
                $sent = $this->processEstate($estate, $today, $isDryRun);
                $totalReminders += $sent;
            } catch (Throwable $e) {
                $errors++;
                $this->error("  ✗ [{$estate->name}] {$e->getMessage()}");
                Log::error("billing:send-payment-reminders estate={$estate->id} error={$e->getMessage()}", [
                    'estate_id' => $estate->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Reminders queued: {$totalReminders} | Errors: {$errors}");

        Log::info('billing:send-payment-reminders completed', [
            'date'             => $today->toDateString(),
            'dry_run'          => $isDryRun,
            'estates'          => $estates->count(),
            'reminders_queued' => $totalReminders,
            'errors'           => $errors,
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processEstate(Estate $estate, Carbon $today, bool $isDryRun): int
    {
        // Threshold date: invoices due on or before this date are eligible.
        $thresholdDate = $today->copy()->subDays($estate->payment_reminder_days);

        // Find unpaid invoices for this estate:
        //   - due date <= threshold (i.e. overdue by at least payment_reminder_days)
        //   - no reminder sent yet
        //   - not paid / cancelled
        $invoices = Invoice::whereHas(
            'unit',
            fn ($q) => $q->where('estate_id', $estate->id)
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
            $this->line("  → [{$estate->name}] no invoices due for reminders");
            return 0;
        }

        $this->line("  → [{$estate->name}] {$invoices->count()} reminder(s) to send (>{$estate->payment_reminder_days} days overdue)");

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
