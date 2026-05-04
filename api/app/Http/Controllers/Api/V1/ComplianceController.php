<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\ComplianceTemplate;
use App\Services\ComplianceService;
use App\Http\Resources\ComplianceChecklistResource;
use App\Http\Resources\ComplianceChecklistResources;
use App\Http\Resources\ComplianceTemplateResource;
use App\Http\Resources\ComplianceTemplateResources;
use App\Http\Requests\Compliance\ShowComplianceChecklistsRequest;
use App\Http\Requests\Compliance\CreateComplianceChecklistRequest;
use App\Http\Requests\Compliance\UpdateComplianceChecklistRequest;
use App\Http\Requests\Compliance\DeleteComplianceChecklistRequest;
use App\Http\Requests\Compliance\CreateComplianceChecklistItemRequest;
use App\Http\Requests\Compliance\UpdateComplianceChecklistItemRequest;
use App\Http\Requests\Compliance\ShowComplianceTemplatesRequest;
use App\Http\Requests\Compliance\CreateComplianceTemplateRequest;
use App\Http\Requests\Compliance\UpdateComplianceTemplateRequest;
use App\Http\Requests\Compliance\DeleteComplianceTemplateRequest;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    protected ComplianceService $service;

    public function __construct(ComplianceService $service)
    {
        $this->service = $service;
    }

    // ─── Checklists ─────────────────────────────────────────────────────

    /**
     * Return a paginated list of compliance checklists.
     */
    public function showChecklists(ShowComplianceChecklistsRequest $request): ComplianceChecklistResources
    {
        return $this->service->showChecklists($request->validated());
    }

    /**
     * Show a single compliance checklist with all items.
     */
    public function showChecklist(Request $request, ComplianceChecklist $complianceChecklist): ComplianceChecklistResource
    {
        return $this->service->showChecklist($complianceChecklist);
    }

    /**
     * Create a new compliance checklist.
     */
    public function createChecklist(CreateComplianceChecklistRequest $request): array
    {
        return $this->service->createChecklist($request->validated());
    }

    /**
     * Update an existing compliance checklist.
     */
    public function updateChecklist(UpdateComplianceChecklistRequest $request, ComplianceChecklist $complianceChecklist): array
    {
        return $this->service->updateChecklist($complianceChecklist, $request->validated());
    }

    /**
     * Delete a compliance checklist.
     */
    public function deleteChecklist(DeleteComplianceChecklistRequest $request, ComplianceChecklist $complianceChecklist): array
    {
        return $this->service->deleteChecklist($complianceChecklist);
    }

    // ─── Checklist Items ────────────────────────────────────────────────

    /**
     * Add an item to a compliance checklist.
     */
    public function createChecklistItem(CreateComplianceChecklistItemRequest $request, ComplianceChecklist $complianceChecklist): array
    {
        return $this->service->createChecklistItem($complianceChecklist, $request->validated());
    }

    /**
     * Update a checklist item.
     */
    public function updateChecklistItem(UpdateComplianceChecklistItemRequest $request, ComplianceChecklist $complianceChecklist, ComplianceChecklistItem $complianceChecklistItem): array
    {
        return $this->service->updateChecklistItem($complianceChecklistItem, $request->validated());
    }

    /**
     * Upload one or more attachments for a checklist item.
     */
    public function uploadAttachments(Request $request, ComplianceChecklist $complianceChecklist, ComplianceChecklistItem $complianceChecklistItem): array
    {
        $request->validate([
            'attachments'   => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
            'evidence'      => ['sometimes', 'file', 'max:10240'],
        ]);

        return $this->service->uploadAttachments($complianceChecklistItem);
    }

    /**
     * Download a specific attachment.
     */
    public function downloadAttachment(Request $request, ComplianceChecklist $complianceChecklist, ComplianceChecklistItem $complianceChecklistItem, ComplianceItemAttachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->downloadAttachment($attachment);
    }

    /**
     * Delete a specific attachment.
     */
    public function deleteAttachment(Request $request, ComplianceChecklist $complianceChecklist, ComplianceChecklistItem $complianceChecklistItem, ComplianceItemAttachment $attachment): array
    {
        return $this->service->deleteAttachment($attachment);
    }

    /**
     * Delete a checklist item.
     */
    public function deleteChecklistItem(Request $request, ComplianceChecklist $complianceChecklist, ComplianceChecklistItem $complianceChecklistItem): array
    {
        return $this->service->deleteChecklistItem($complianceChecklistItem);
    }

    // ─── Templates ──────────────────────────────────────────────────────

    /**
     * Return a paginated list of compliance templates.
     */
    public function showTemplates(ShowComplianceTemplatesRequest $request): ComplianceTemplateResources
    {
        return $this->service->showTemplates($request->validated());
    }

    /**
     * Show a single compliance template.
     */
    public function showTemplate(Request $request, ComplianceTemplate $complianceTemplate): ComplianceTemplateResource
    {
        return $this->service->showTemplate($complianceTemplate);
    }

    /**
     * Create a new compliance template.
     */
    public function createTemplate(CreateComplianceTemplateRequest $request): array
    {
        return $this->service->createTemplate($request->validated());
    }

    /**
     * Update a compliance template.
     */
    public function updateTemplate(UpdateComplianceTemplateRequest $request, ComplianceTemplate $complianceTemplate): array
    {
        return $this->service->updateTemplate($complianceTemplate, $request->validated());
    }

    /**
     * Delete a compliance template.
     */
    public function deleteTemplate(DeleteComplianceTemplateRequest $request, ComplianceTemplate $complianceTemplate): array
    {
        return $this->service->deleteTemplate($complianceTemplate);
    }

    // ─── Portfolio Dashboard ────────────────────────────────────────────

    /**
     * Return portfolio-wide compliance summary.
     */
    public function portfolioSummary(Request $request): array
    {
        return $this->service->portfolioSummary($request->only(['country', 'financial_year_label']));
    }
}
