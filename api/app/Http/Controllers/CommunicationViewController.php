<?php

namespace App\Http\Controllers;

use App\Models\CommunicationRecipient;
use App\Services\CommunicationService;
use Illuminate\Contracts\View\View;

class CommunicationViewController extends Controller
{
    protected CommunicationService $service;

    public function __construct(CommunicationService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the standalone email-view page for a sent communication recipient
     * (WeConnectU download-mail-new.php parity). Public but guarded by the
     * unguessable per-recipient view_token.
     *
     * @param string $token
     * @return View
     */
    public function show(string $token): View
    {
        $recipient = CommunicationRecipient::where('view_token', $token)
            ->with(['communication.community', 'communication.organization'])
            ->firstOrFail();

        $communication = $recipient->communication;

        $renderedBody = $this->service->wrapWithBranding(
            $communication->body ?? '',
            $communication->community,
            $communication->organization
        );

        return view('communications.download-view', [
            'recipient'     => $recipient,
            'communication' => $communication,
            'renderedBody'  => $renderedBody,
        ]);
    }
}
