<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public int $timeout = 30;

    public function __construct(public readonly string $invoiceId) {}

    public function middleware(): array
    {
        return [new RateLimited('resend-emails')];
    }

    public function handle(InvoiceService $service): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (!$invoice) {
            Log::warning("SendInvoiceEmail: invoice {$this->invoiceId} not found, skipping.");
            return;
        }

        $service->resendInvoice($invoice);

        Log::info("SendInvoiceEmail: sent invoice {$invoice->invoice_number} ({$this->invoiceId})");
    }

    public function failed(Throwable $e): void
    {
        Log::error("SendInvoiceEmail: all retries exhausted for invoice {$this->invoiceId} — {$e->getMessage()}");

        $invoice = Invoice::find($this->invoiceId);

        if (!$invoice) {
            return;
        }

        $invoice->update(['email_failed_at' => now()]);

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'event_type'      => 'delivery_failed',
            'email'           => null,
            'resend_email_id' => null,
            'occurred_at'     => now(),
            'metadata'        => ['reason' => $e->getMessage()],
        ]);
    }
}
