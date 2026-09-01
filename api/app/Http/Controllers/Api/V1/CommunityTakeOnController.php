<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\BudgetImportService;
use App\Services\CommunityService;
use App\Services\CommunityTakeonService;
use App\Services\OwnerSheetImportService;
use App\Http\Requests\Community\ImportBudgetRequest;
use App\Http\Requests\Community\ImportOwnerSheetRequest;
use App\Http\Requests\Community\MarkTakeOnItemRequest;
use App\Http\Requests\Community\SubmitTakeOnRequest;
use Illuminate\Http\Request;

class CommunityTakeOnController extends Controller
{
    /**
     * @var OwnerSheetImportService
     */
    protected OwnerSheetImportService $ownerSheetService;

    /**
     * @var BudgetImportService
     */
    protected BudgetImportService $budgetService;

    /**
     * @var CommunityService
     */
    protected CommunityService $communityService;

    /**
     * @var CommunityTakeonService
     */
    protected CommunityTakeonService $takeonService;

    /**
     * CommunityTakeOnController constructor.
     *
     * @param OwnerSheetImportService $ownerSheetService
     * @param BudgetImportService $budgetService
     * @param CommunityService $communityService
     * @param CommunityTakeonService $takeonService
     */
    public function __construct(
        OwnerSheetImportService $ownerSheetService,
        BudgetImportService $budgetService,
        CommunityService $communityService,
        CommunityTakeonService $takeonService,
    ) {
        $this->ownerSheetService = $ownerSheetService;
        $this->budgetService     = $budgetService;
        $this->communityService  = $communityService;
        $this->takeonService     = $takeonService;
    }

    /**
     * Return the take-on step status map (uploaded / not-applicable / pending).
     *
     * @param Request $request
     * @param Community $community
     * @return array
     */
    public function takeOnStatus(Request $request, Community $community): array
    {
        return $this->takeonService->status($community);
    }

    /**
     * Mark a take-on step as "Not Applicable".
     *
     * @param MarkTakeOnItemRequest $request
     * @param Community $community
     * @param string $key
     * @return array
     */
    public function markNotApplicable(MarkTakeOnItemRequest $request, Community $community, string $key): array
    {
        return $this->takeonService->markNotApplicable($community, $key);
    }

    /**
     * Download the stored file for a specific take-on history entry.
     *
     * @param Request $request
     * @param Community $community
     * @param \App\Models\CommunityTakeonItem $item
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTakeOnFile(Request $request, Community $community, \App\Models\CommunityTakeonItem $item): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->takeonService->downloadItem($community, $item);
    }

    /**
     * Download the WeConnectU owner-sheet (customer) template.
     *
     * @param Request $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadOwnerSheetTemplate(Request $request, Community $community): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->ownerSheetService->downloadTemplate($community);
    }

    /**
     * Parse an uploaded owner sheet and return a preview + validation.
     *
     * @param ImportOwnerSheetRequest $request
     * @param Community $community
     * @return array
     */
    public function parseOwnerSheet(ImportOwnerSheetRequest $request, Community $community): array
    {
        return $this->ownerSheetService->parse($community, $request->file('file'));
    }

    /**
     * Import an uploaded owner sheet into units + owners.
     *
     * @param ImportOwnerSheetRequest $request
     * @param Community $community
     * @return array
     */
    public function importOwnerSheet(ImportOwnerSheetRequest $request, Community $community): array
    {
        return $this->ownerSheetService->import($community, $request->file('file'));
    }

    /**
     * Download the WeConnectU budget template (seeded with the chart of accounts).
     *
     * @param Request $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadBudgetTemplate(Request $request, Community $community): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->budgetService->downloadTemplate($community);
    }

    /**
     * Parse an uploaded budget file and return a preview + validation.
     *
     * @param ImportBudgetRequest $request
     * @param Community $community
     * @return array
     */
    public function parseBudget(ImportBudgetRequest $request, Community $community): array
    {
        return $this->budgetService->parse($community, $request->file('file'));
    }

    /**
     * Import an uploaded budget into per-ledger monthly figures.
     *
     * @param ImportBudgetRequest $request
     * @param Community $community
     * @return array
     */
    public function importBudget(ImportBudgetRequest $request, Community $community): array
    {
        return $this->budgetService->import($community, $request->file('file'));
    }

    /**
     * Submit the community's take-on (transition Take-on → Active).
     *
     * @param SubmitTakeOnRequest $request
     * @param Community $community
     * @return array
     */
    public function submitTakeOn(SubmitTakeOnRequest $request, Community $community): array
    {
        return $this->communityService->submitTakeOn($community);
    }
}
