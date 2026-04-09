<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InvoiceEmailEvent;
use Illuminate\Http\Request;

class ResendWebhookController extends Controller
{
    /**
     * Handle incoming Resend email webhook events.
     *
     * Resend posts events to this endpoint when an email is delivered or opened.
     * We find the matching sent event by resend_email_id and record the new status.
     *
     * @param Request $request
     * @return array
     */
    public function handle(Request $request): array
    {
        $type = $request->input('type');
        $data = $request->input('data', []);

        $resendEmailId = $data['email_id'] ?? null;

        if (!$resendEmailId) {
            return ['message' => 'No email ID in payload'];
        }

        $eventType = match ($type) {
            'email.delivered' => 'delivered',
            'email.opened'    => 'opened',
            default           => null,
        };

        if (!$eventType) {
            return ['message' => 'Unhandled event type: ' . $type];
        }

        // Find the original sent event to get invoice and tenant context
        $sentEvent = InvoiceEmailEvent::where('resend_email_id', $resendEmailId)
            ->where('event_type', 'sent')
            ->first();

        if (!$sentEvent) {
            return ['message' => 'No matching sent event found for email ID: ' . $resendEmailId];
        }

        // Prevent duplicate events (e.g. Resend may fire opened multiple times)
        $exists = InvoiceEmailEvent::where('invoice_id', $sentEvent->invoice_id)
            ->where('event_type', $eventType)
            ->where('resend_email_id', $resendEmailId)
            ->exists();

        if (!$exists) {
            InvoiceEmailEvent::create([
                'invoice_id'      => $sentEvent->invoice_id,
                'tenant_id'       => $sentEvent->tenant_id,
                'event_type'      => $eventType,
                'email'           => $sentEvent->email,
                'resend_email_id' => $resendEmailId,
                'occurred_at'     => now(),
                'metadata'        => $data,
            ]);
        }

        return ['message' => 'Webhook processed'];
    }
}
