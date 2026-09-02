<?php

namespace App\Notifications;

use App\Models\Community;
use Illuminate\Notifications\Notification;

class BillingRunCompleted extends Notification
{
    public function __construct(
        public readonly Community $community,
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
            'community_id'      => $this->community->id,
            'community_name'    => $this->community->name,
            'invoice_count'  => $this->invoiceCount,
            'billing_period' => $this->billingPeriod,
            'message'        => "{$this->invoiceCount} invoices generated for {$this->community->name} ({$this->billingPeriod})",
        ];
    }
}
