<?php

namespace App\Notifications;

use App\Models\Estate;
use Illuminate\Notifications\Notification;

class BillingRunCompleted extends Notification
{
    public function __construct(
        public readonly Estate $estate,
        public readonly int $invoiceCount,
        public readonly string $billingPeriod,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'estate_id'      => $this->estate->id,
            'estate_name'    => $this->estate->name,
            'invoice_count'  => $this->invoiceCount,
            'billing_period' => $this->billingPeriod,
            'message'        => "{$this->invoiceCount} invoices generated for {$this->estate->name} ({$this->billingPeriod})",
        ];
    }
}
