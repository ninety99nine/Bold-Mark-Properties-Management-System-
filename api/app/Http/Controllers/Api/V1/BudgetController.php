<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\BudgetService;
use App\Http\Requests\Budget\ShowBudgetsRequest;
use App\Http\Requests\Budget\UpsertBudgetsRequest;
use App\Http\Requests\Budget\LockBudgetRequest;
use App\Http\Requests\Budget\ImportBudgetRequest;
use App\Http\Requests\Budget\TemplateBudgetRequest;
use Symfony\Component\HttpFoundation\Response;

class BudgetController extends Controller
{
    protected BudgetService $service;

    /**
     * BudgetController constructor.
     *
     * @param BudgetService $service
     */
    public function __construct(BudgetService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the budget grid for a community, fund and year.
     *
     * @param ShowBudgetsRequest $request
     * @param Community $community
     * @return array
     */
    public function showBudgets(ShowBudgetsRequest $request, Community $community): array
    {
        return $this->service->showBudgets($community, $request->validated());
    }

    /**
     * Upsert budget rows for a community, fund and year.
     *
     * @param UpsertBudgetsRequest $request
     * @param Community $community
     * @return array
     */
    public function upsertBudgets(UpsertBudgetsRequest $request, Community $community): array
    {
        return $this->service->upsertBudgets($community, $request->validated());
    }

    /**
     * Lock a budget period (fund + year).
     *
     * @param LockBudgetRequest $request
     * @param Community $community
     * @return array
     */
    public function lockBudget(LockBudgetRequest $request, Community $community): array
    {
        return $this->service->lockBudget($community, $request->validated());
    }

    /**
     * Re-open a budget period for editing.
     *
     * @param LockBudgetRequest $request
     * @param Community $community
     * @return array
     */
    public function unlockBudget(LockBudgetRequest $request, Community $community): array
    {
        return $this->service->unlockBudget($community, $request->validated());
    }

    /**
     * Download the budget template spreadsheet.
     *
     * @param TemplateBudgetRequest $request
     * @param Community $community
     * @return Response
     */
    public function downloadTemplate(TemplateBudgetRequest $request, Community $community): Response
    {
        return $this->service->downloadTemplate($community, $request->validated());
    }

    /**
     * Import a filled budget spreadsheet.
     *
     * @param ImportBudgetRequest $request
     * @param Community $community
     * @return array
     */
    public function importBudget(ImportBudgetRequest $request, Community $community): array
    {
        return $this->service->importBudget($community, $request->validated(), $request->file('file'));
    }
}
