<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(public readonly string $invoiceId) {}

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
        Log::error("SendInvoiceEmail: failed for invoice {$this->invoiceId} — {$e->getMessage()}");
    }
}
