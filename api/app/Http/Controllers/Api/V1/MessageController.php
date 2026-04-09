<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MessageController extends Controller
{
    /**
     * Send an email message to a recipient via Resend.
     *
     * @param SendMessageRequest $request
     * @return JsonResponse
     */
    public function send(SendMessageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            Mail::mailer('resend')
                ->raw($validated['body'], function ($message) use ($validated) {
                    $message
                        ->to($validated['recipient_email'], $validated['recipient_name'])
                        ->subject($validated['subject']);
                });

            return response()->json(['message' => 'Email sent successfully.']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to send email. Please try again.'], 500);
        }
    }
}
