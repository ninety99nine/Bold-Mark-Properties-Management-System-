<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunicationRecipient;
use App\Models\InvoiceEmailEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResendWebhookController extends Controller
{
    public function handle(Request $request): array
    {
        $type = $request->input('type');
        $data = $request->input('data', []);

        $resendEmailId = $data['email_id'] ?? null;

        if (!$resendEmailId) {
            Log::warning('resend_webhook: missing email_id', ['type' => $type, 'payload' => $data]);
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

        // Communication archive: update the matching recipient's delivery status.
        // "opened" maps to "read" (matches the WeConnectU archive Status column).
        $commStatus = $eventType === 'opened' ? 'read' : 'delivered';
        $recipient  = CommunicationRecipient::where('resend_email_id', $resendEmailId)->first();

        if ($recipient) {
            // Never downgrade a "read" back to "delivered".
            if (! ($recipient->status === 'read' && $commStatus === 'delivered')) {
                $recipient->update(['status' => $commStatus]);
            }

            return ['message' => 'Communication webhook processed'];
        }

        // Find the original sent event to get invoice and occupant context
        $sentEvent = InvoiceEmailEvent::where('resend_email_id', $resendEmailId)
            ->where('event_type', 'sent')
            ->first();

        if (!$sentEvent) {
            Log::warning('resend_webhook: no matching sent event', [
                'type'           => $type,
                'resend_email_id' => $resendEmailId,
            ]);
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
                'organization_id' => $sentEvent->organization_id,
                'event_type'      => $eventType,
                'email'           => $sentEvent->email,
                'resend_email_id' => $resendEmailId,
                'occurred_at'     => now(),
                'metadata'        => $data,
            ]);

            Log::info('resend_webhook: recorded', [
                'event_type'     => $eventType,
                'invoice_id'     => $sentEvent->invoice_id,
                'resend_email_id' => $resendEmailId,
            ]);
        }

        return ['message' => 'Webhook processed'];
    }
}
