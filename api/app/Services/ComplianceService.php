<?php

namespace App\Services;

use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\ComplianceTemplate;
use App\Models\ComplianceTemplateItem;
use App\Models\Estate;
use App\Http\Resources\ComplianceChecklistResource;
use App\Http\Resources\ComplianceChecklistResources;
use App\Http\Resources\ComplianceChecklistItemResource;
use App\Http\Resources\ComplianceTemplateResource;
use App\Http\Resources\ComplianceTemplateResources;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

class ComplianceService extends BaseService
{
    protected string $resourceClass = ComplianceChecklistResource::class;
    protected string $resourceCollectionClass = ComplianceChecklistResources::class;

    // ─── Checklists ─────────────────────────────────────────────────────

    /**
     * Return a paginated list of compliance checklists.
     */
    public function showChecklists(array $data): ComplianceChecklistResources
    {
        $tenantId = auth()->user()->organization_id;

        $query = ComplianceChecklist::query()
            ->where('organization_id', $tenantId)
            ->withCount(['items', 'completedItems', 'overdueItems'])
            ->with(['estate:id,name,country,type']);

        // Filter by estate
        if (! empty($data['estate_id'])) {
            $query->where('estate_id', $data['estate_id']);
        }

        // Filter by country (through estate)
        if (! empty($data['country'])) {
            $query->whereHas('estate', function ($q) use ($data) {
                $q->where('country', $data['country']);
            });
        }

        // Filter by financial year
        if (! empty($data['financial_year_label'])) {
            $query->where('financial_year_label', $data['financial_year_label']);
        }

        $this->setQuery($query);

        return $this->getOutput();
    }

    /**
     * Show a single compliance checklist with all items.
     */
    public function showChecklist(ComplianceChecklist $checklist): ComplianceChecklistResource
    {
        $checklist->load([
            'estate:id,name,address,country,type',
            'createdBy:id,name,email',
            'items' => fn ($q) => $q->orderBy('category')->orderBy('sort_order'),
            'items.assignedTo:id,name,email',
            'items.completedBy:id,name,email',
            'items.attachments.uploadedBy:id,name',
        ]);
        $checklist->loadCount(['items', 'completedItems', 'overdueItems']);

        // Compute progress
        $totalItems = $checklist->items_count;
        $completedItems = $checklist->completed_items_count;
        $checklist->progress_percentage = $totalItems > 0
            ? round(($completedItems / $totalItems) * 100)
            : 0;

        // Status summary by category
        $statusSummary = [];
        foreach ($checklist->items->groupBy('category') as $category => $items) {
            $statusSummary[$category] = [
                'total'     => $items->count(),
                'completed' => $items->where('status.value', 'completed')->count(),
                'overdue'   => $items->where('status.value', 'overdue')->count(),
                'pending'   => $items->where('status.value', 'pending')->count(),
                'in_progress' => $items->where('status.value', 'in_progress')->count(),
                'waived'    => $items->where('status.value', 'waived')->count(),
            ];
        }
        $checklist->status_summary = $statusSummary;

        return new ComplianceChecklistResource($checklist);
    }

    /**
     * Create a new compliance checklist, optionally from a template.
     */
    public function createChecklist(array $data): array
    {
        $user = auth()->user();

        try {
            $checklist = ComplianceChecklist::create([
                'financial_year_label' => $data['financial_year_label'],
                'financial_year_start' => $data['financial_year_start'],
                'financial_year_end'   => $data['financial_year_end'],
                'notes'                => $data['notes'] ?? null,
                'organization_id'            => $user->organization_id,
                'estate_id'            => $data['estate_id'],
                'created_by_id'        => $user->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A compliance checklist already exists for this estate and financial year period. Please choose a different financial year.');
        }

        // If a template was provided, generate items from it
        if (! empty($data['template_id'])) {
            $this->generateItemsFromTemplate($checklist, $data['template_id']);
        }

        $checklist->load(['estate:id,name,country,type', 'items']);
        $checklist->loadCount(['items', 'completedItems', 'overdueItems']);

        return $this->showCreatedResource($checklist);
    }

    /**
     * Update an existing compliance checklist.
     */
    public function updateChecklist(ComplianceChecklist $checklist, array $data): array
    {
        $checklist->update($data);
        $checklist->load(['estate:id,name,country,type']);

        return $this->showUpdatedResource($checklist);
    }

    /**
     * Delete a compliance checklist and all its items.
     */
    public function deleteChecklist(ComplianceChecklist $checklist): array
    {
        $checklist->delete();

        return ['message' => 'Compliance checklist deleted successfully.'];
    }

    // ─── Checklist Items ────────────────────────────────────────────────

    /**
     * Add an item to a compliance checklist.
     */
    public function createChecklistItem(ComplianceChecklist $checklist, array $data): array
    {
        $user = auth()->user();

        // Auto-assign sort order if not provided
        if (! isset($data['sort_order'])) {
            $maxSort = $checklist->items()
                ->where('category', $data['category'])
                ->max('sort_order');
            $data['sort_order'] = ($maxSort ?? 0) + 1;
        }

        $item = ComplianceChecklistItem::create([
            'name'                    => $data['name'],
            'category'                => $data['category'],
            'description'             => $data['description'] ?? null,
            'priority'                => $data['priority'],
            'due_date'                => $data['due_date'] ?? null,
            'sort_order'              => $data['sort_order'],
            'is_recurring'            => $data['is_recurring'] ?? true,
            'assigned_to_id'          => $data['assigned_to_id'] ?? null,
            'compliance_checklist_id' => $checklist->id,
            'organization_id'               => $user->organization_id,
        ]);

        $item->load(['assignedTo:id,name,email']);

        return [
            'data'    => new ComplianceChecklistItemResource($item),
            'message' => 'Compliance item added successfully.',
        ];
    }

    /**
     * Update a checklist item (including status transitions).
     */
    public function updateChecklistItem(ComplianceChecklistItem $item, array $data): array
    {
        $user = auth()->user();

        // Handle status transition to completed
        if (isset($data['status']) && $data['status'] === 'completed' && $item->status->value !== 'completed') {
            $data['completed_at']    = now();
            $data['completed_by_id'] = $user->id;
        }

        // Handle status transition away from completed
        if (isset($data['status']) && $data['status'] !== 'completed' && $item->status->value === 'completed') {
            $data['completed_at']    = null;
            $data['completed_by_id'] = null;
        }

        $item->update($data);
        $item->load(['assignedTo:id,name,email', 'completedBy:id,name,email']);

        return [
            'data'    => new ComplianceChecklistItemResource($item),
            'message' => 'Compliance item updated successfully.',
        ];
    }

    /**
     * Upload one or more attachment files to a checklist item.
     */
    public function uploadAttachments(ComplianceChecklistItem $item): array
    {
        $files = request()->file('attachments', []);

        // Support single file upload via 'evidence' key for backwards compatibility
        if (empty($files) && request()->hasFile('evidence')) {
            $files = [request()->file('evidence')];
        }

        if (empty($files)) {
            abort(422, 'No files provided.');
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $user = auth()->user();

        foreach ($files as $file) {
            $path = $file->store('compliance-evidence/' . $item->compliance_checklist_id, 'local');

            ComplianceItemAttachment::create([
                'file_path'                     => $path,
                'file_name'                     => $file->getClientOriginalName(),
                'file_size'                     => $file->getSize(),
                'mime_type'                     => $file->getMimeType(),
                'compliance_checklist_item_id'  => $item->id,
                'uploaded_by_id'                => $user->id,
            ]);
        }

        $item->load(['attachments.uploadedBy:id,name']);

        return [
            'data'    => new ComplianceChecklistItemResource($item),
            'message' => count($files) === 1 ? 'Attachment uploaded successfully.' : count($files) . ' attachments uploaded successfully.',
        ];
    }

    /**
     * Download a specific attachment file.
     */
    public function downloadAttachment(ComplianceItemAttachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * Delete a specific attachment file.
     */
    public function deleteAttachment(ComplianceItemAttachment $attachment): array
    {
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return ['message' => 'Attachment removed successfully.'];
    }

    /**
     * Delete a checklist item and all its attachments.
     */
    public function deleteChecklistItem(ComplianceChecklistItem $item): array
    {
        // Delete legacy single evidence file
        if ($item->evidence_file_path) {
            Storage::disk('local')->delete($item->evidence_file_path);
        }

        // Delete all attachment files
        foreach ($item->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $item->delete();

        return ['message' => 'Compliance item deleted successfully.'];
    }

    // ─── Templates ──────────────────────────────────────────────────────

    /**
     * Return a paginated list of compliance templates.
     */
    public function showTemplates(array $data): ComplianceTemplateResources
    {
        $tenantId = auth()->user()->organization_id;

        $query = ComplianceTemplate::query()
            ->where('organization_id', $tenantId)
            ->withCount('items')
            ->with('items');

        if (! empty($data['country'])) {
            $query->where(function ($q) use ($data) {
                $q->where('country', $data['country'])
                  ->orWhereNull('country');
            });
        }

        $query->orderByDesc('is_default')->orderBy('name');

        $this->setQuery($query);
        $this->resourceCollectionClass = ComplianceTemplateResources::class;

        return $this->getOutput();
    }

    /**
     * Show a single template with all items.
     */
    public function showTemplate(ComplianceTemplate $template): ComplianceTemplateResource
    {
        $template->load('items');
        $template->loadCount('items');

        return new ComplianceTemplateResource($template);
    }

    /**
     * Create a new compliance template with optional items.
     */
    public function createTemplate(array $data): array
    {
        $user = auth()->user();

        $template = ComplianceTemplate::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'country'     => $data['country'] ?? null,
            'is_default'  => $data['is_default'] ?? false,
            'organization_id'   => $user->organization_id,
        ]);

        // Create items if provided
        if (! empty($data['items'])) {
            foreach ($data['items'] as $index => $itemData) {
                ComplianceTemplateItem::create([
                    'name'                   => $itemData['name'],
                    'category'               => $itemData['category'],
                    'description'            => $itemData['description'] ?? null,
                    'priority'               => $itemData['priority'],
                    'default_month_due'      => $itemData['default_month_due'] ?? null,
                    'sort_order'             => $itemData['sort_order'] ?? $index,
                    'is_recurring'           => $itemData['is_recurring'] ?? true,
                    'compliance_template_id' => $template->id,
                ]);
            }
        }

        $template->load('items');
        $template->loadCount('items');

        return [
            'data'    => new ComplianceTemplateResource($template),
            'message' => 'Compliance template created successfully.',
        ];
    }

    /**
     * Update a compliance template and optionally replace its items.
     */
    public function updateTemplate(ComplianceTemplate $template, array $data): array
    {
        $template->update(collect($data)->only(['name', 'description', 'country', 'is_default'])->toArray());

        // Replace items if provided
        if (isset($data['items'])) {
            $template->items()->delete();

            foreach ($data['items'] as $index => $itemData) {
                ComplianceTemplateItem::create([
                    'name'                   => $itemData['name'],
                    'category'               => $itemData['category'],
                    'description'            => $itemData['description'] ?? null,
                    'priority'               => $itemData['priority'],
                    'default_month_due'      => $itemData['default_month_due'] ?? null,
                    'sort_order'             => $itemData['sort_order'] ?? $index,
                    'is_recurring'           => $itemData['is_recurring'] ?? true,
                    'compliance_template_id' => $template->id,
                ]);
            }
        }

        $template->load('items');
        $template->loadCount('items');

        return [
            'data'    => new ComplianceTemplateResource($template),
            'message' => 'Compliance template updated successfully.',
        ];
    }

    /**
     * Delete a compliance template and all its items.
     */
    public function deleteTemplate(ComplianceTemplate $template): array
    {
        $template->delete();

        return ['message' => 'Compliance template deleted successfully.'];
    }

    // ─── Portfolio Dashboard ────────────────────────────────────────────

    /**
     * Return portfolio-wide compliance summary for the dashboard.
     */
    public function portfolioSummary(array $data): array
    {
        $tenantId = auth()->user()->organization_id;

        $query = ComplianceChecklist::query()
            ->where('organization_id', $tenantId)
            ->withCount(['items', 'completedItems', 'overdueItems'])
            ->with(['estate:id,name,address,country,type']);

        // Filter by country
        if (! empty($data['country'])) {
            $query->whereHas('estate', fn ($q) => $q->where('country', $data['country']));
        }

        // Filter by financial year
        if (! empty($data['financial_year_label'])) {
            $query->where('financial_year_label', $data['financial_year_label']);
        }

        $checklists = $query->get();

        // Build per-estate summary
        $estatesSummary = $checklists->map(function ($checklist) {
            $total = $checklist->items_count;
            $completed = $checklist->completed_items_count;
            $overdue = $checklist->overdue_items_count;
            $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'checklist_id'     => $checklist->id,
                'estate_id'        => $checklist->estate_id,
                'estate_name'      => $checklist->estate->name ?? 'Unknown',
                'estate_country'   => $checklist->estate->country ?? null,
                'estate_type'      => $checklist->estate->type?->value ?? null,
                'financial_year'   => $checklist->financial_year_label,
                'total_items'      => $total,
                'completed_items'  => $completed,
                'overdue_items'    => $overdue,
                'pending_items'    => $total - $completed - $overdue,
                'progress'         => $progress,
                'status'           => $this->resolveComplianceStatus($progress, $overdue),
            ];
        })->sortBy('progress')->values();

        // Aggregate portfolio metrics
        $totalEstates = $estatesSummary->count();
        $totalItems = $estatesSummary->sum('total_items');
        $totalCompleted = $estatesSummary->sum('completed_items');
        $totalOverdue = $estatesSummary->sum('overdue_items');
        $fullyCompliant = $estatesSummary->where('progress', 100)->count();
        $portfolioProgress = $totalItems > 0 ? round(($totalCompleted / $totalItems) * 100) : 0;

        // Available financial years for filter
        $financialYears = ComplianceChecklist::where('organization_id', $tenantId)
            ->select('financial_year_label', 'financial_year_start')
            ->distinct()
            ->orderByDesc('financial_year_start')
            ->pluck('financial_year_label')
            ->toArray();

        return [
            'summary' => [
                'total_estates'      => $totalEstates,
                'fully_compliant'    => $fullyCompliant,
                'partially_compliant' => $estatesSummary->where('progress', '>', 0)->where('progress', '<', 100)->count(),
                'not_started'        => $estatesSummary->where('progress', 0)->count(),
                'total_items'        => $totalItems,
                'total_completed'    => $totalCompleted,
                'total_overdue'      => $totalOverdue,
                'portfolio_progress' => $portfolioProgress,
            ],
            'estates'         => $estatesSummary,
            'financial_years' => $financialYears,
        ];
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Generate checklist items from a template.
     */
    protected function generateItemsFromTemplate(ComplianceChecklist $checklist, string $templateId): void
    {
        $template = ComplianceTemplate::with('items')->findOrFail($templateId);

        foreach ($template->items as $templateItem) {
            // Calculate due date based on financial year start + default_month_due
            $dueDate = null;
            if ($templateItem->default_month_due) {
                $dueDate = $checklist->financial_year_start
                    ->copy()
                    ->addMonths($templateItem->default_month_due - 1)
                    ->endOfMonth();

                // Ensure due date doesn't exceed financial year end
                if ($dueDate->gt($checklist->financial_year_end)) {
                    $dueDate = $checklist->financial_year_end->copy();
                }
            }

            ComplianceChecklistItem::create([
                'name'                    => $templateItem->name,
                'category'                => $templateItem->category,
                'description'             => $templateItem->description,
                'priority'                => $templateItem->priority->value,
                'due_date'                => $dueDate,
                'sort_order'              => $templateItem->sort_order,
                'is_recurring'            => $templateItem->is_recurring,
                'compliance_checklist_id' => $checklist->id,
                'organization_id'               => $checklist->organization_id,
            ]);
        }
    }

    /**
     * Resolve a colour status label from progress and overdue count.
     */
    protected function resolveComplianceStatus(int $progress, int $overdue): string
    {
        if ($progress === 100) return 'compliant';
        if ($overdue > 0)      return 'at_risk';
        if ($progress > 0)     return 'in_progress';
        return 'not_started';
    }
}
