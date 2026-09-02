<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageTemplate\UpdateMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResources;
use App\Models\MessageTemplate;
use App\Services\MessageTemplateService;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    protected MessageTemplateService $service;

    public function __construct(MessageTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * List the organization's message templates (seeded from defaults on first load).
     *
     * @param Request $request
     * @return MessageTemplateResources
     */
    public function showMessageTemplates(Request $request): MessageTemplateResources
    {
        return $this->service->showTemplates($request->user()->organization);
    }

    /**
     * Update a single message template's subject/body/terms.
     *
     * @param UpdateMessageTemplateRequest $request
     * @param MessageTemplate $messageTemplate
     * @return array
     */
    public function updateMessageTemplate(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): array
    {
        $this->authoriseOwnership($request, $messageTemplate);

        return $this->service->updateTemplate($messageTemplate, $request->validated());
    }

    /**
     * Restore a message template to its canonical default content.
     *
     * @param Request $request
     * @param MessageTemplate $messageTemplate
     * @return array
     */
    public function resetMessageTemplate(Request $request, MessageTemplate $messageTemplate): array
    {
        $this->authoriseOwnership($request, $messageTemplate);

        return $this->service->resetTemplate($messageTemplate);
    }

    /**
     * Ensure the template belongs to the authenticated user's organization.
     *
     * @param Request $request
     * @param MessageTemplate $messageTemplate
     * @return void
     */
    private function authoriseOwnership(Request $request, MessageTemplate $messageTemplate): void
    {
        abort_unless(
            $messageTemplate->organization_id === $request->user()->organization_id,
            403
        );
    }
}
