<?php

namespace App\Jobs;

use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCreditNoteEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public int $timeout = 30;

    public function __construct(public readonly string $creditNoteId) {}

    public function middleware(): array
    {
        return [new RateLimited('resend-emails')];
    }

    public function handle(CreditNoteService $service): void
    {
        $creditNote = CreditNote::find($this->creditNoteId);

        if (!$creditNote) {
            Log::warning("SendCreditNoteEmail: credit note {$this->creditNoteId} not found, skipping.");
            return;
        }

        $service->sendCreditNote($creditNote);

        Log::info("SendCreditNoteEmail: sent credit note {$creditNote->credit_note_number} ({$this->creditNoteId})");
    }

    public function failed(Throwable $e): void
    {
        Log::error("SendCreditNoteEmail: all retries exhausted for credit note {$this->creditNoteId} — {$e->getMessage()}");
    }
}
