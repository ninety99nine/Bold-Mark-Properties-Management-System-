<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Services\CashbookEntryService;
use App\Http\Resources\CashbookEntryResource;
use App\Http\Resources\CashbookEntryResources;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\CashbookEntry\ShowCashbookEntriesRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookTransactionsRequest;
use App\Http\Requests\CashbookEntry\CreateManualTransactionsRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookSummaryRequest;
use App\Http\Requests\CashbookEntry\CreateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\AutoAllocateCashbookEntriesRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\UpdateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\AllocateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeallocateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeleteCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeleteCashbookEntriesRequest;
use App\Http\Requests\CashbookEntry\AllocateEntryRequest;
use App\Http\Requests\CashbookEntry\DeallocateEntryRequest;
use App\Http\Requests\CashbookEntry\SplitCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\UploadSplitRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookStatusRequest;
use App\Http\Requests\CashbookEntry\ShowLedgerOptionsRequest;
use App\Http\Requests\CashbookEntry\CustomerSearchRequest;
use App\Http\Requests\CashbookEntry\RunRulesRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CashbookEntryController extends Controller
{
    protected CashbookEntryService $service;

    public function __construct(CashbookEntryService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of cashbook entries for the authenticated occupant.
     *
     * @param ShowCashbookEntriesRequest $request
     * @return CashbookEntryResources
     */
    public function showCashbookEntries(ShowCashbookEntriesRequest $request): CashbookEntryResources
    {
        return $this->service->showCashbookEntries($request->validated());
    }

    /**
     * WeConnectU Cashbook — running-balance transaction list for a single bank
     * account within a community, with Opening / Closing balances.
     *
     * @param ShowCashbookTransactionsRequest $request
     * @param Community                       $community
     * @return array
     */
    public function showCashbookTransactions(ShowCashbookTransactionsRequest $request, Community $community): array
    {
        return $this->service->showCashbookTransactions($community, $request->validated());
    }

    /**
     * WeConnectU Cashbook — bulk-create manual transaction lines for a bank account.
     *
     * @param CreateManualTransactionsRequest $request
     * @param Community                       $community
     * @return JsonResponse
     */
    public function createManualTransactions(CreateManualTransactionsRequest $request, Community $community): JsonResponse
    {
        return response()->json($this->service->createManualTransactions($community, $request->validated()), 201);
    }

    /**
     * WeConnectU Cashbook — per-bank-account allocation status for a community.
     *
     * @param ShowCashbookStatusRequest $request
     * @param Community                 $community
     * @return array
     */
    public function cashbookStatus(ShowCashbookStatusRequest $request, Community $community): array
    {
        return $this->service->cashbookStatus($community);
    }

    /**
     * WeConnectU Cashbook — general / reserve-fund ledger + VAT-type options.
     *
     * @param ShowLedgerOptionsRequest $request
     * @param Community                $community
     * @return array
     */
    public function ledgerOptions(ShowLedgerOptionsRequest $request, Community $community): array
    {
        return $this->service->ledgerOptions($community);
    }

    /**
     * WeConnectU Cashbook — type-ahead customer search within a community.
     *
     * @param CustomerSearchRequest $request
     * @param Community             $community
     * @return array
     */
    public function customerSearch(CustomerSearchRequest $request, Community $community): array
    {
        return $this->service->customerSearch($community, (string) $request->input('q', ''));
    }

    /**
     * WeConnectU Cashbook — run allocation rules across a community's cashbooks.
     *
     * @param RunRulesRequest $request
     * @param Community       $community
     * @return array
     */
    public function runRules(RunRulesRequest $request, Community $community): array
    {
        $allocated = $this->service->runRules($community, $request->input('bank_account_id'));

        return [
            'allocated' => $allocated,
            'message'   => $allocated . ' transaction' . ($allocated === 1 ? '' : 's') . ' allocated',
        ];
    }

    /**
     * WeConnectU Cashbook — download the blank split-allocation upload template.
     *
     * @return BinaryFileResponse
     */
    public function downloadSplitTemplate(): BinaryFileResponse
    {
        return $this->service->downloadSplitTemplate();
    }

    /**
     * WeConnectU Cashbook — allocate a single bank line to an account.
     *
     * @param AllocateEntryRequest $request
     * @param Community            $community
     * @param CashbookEntry        $cashbookEntry
     * @return array
     */
    public function allocateEntry(AllocateEntryRequest $request, Community $community, CashbookEntry $cashbookEntry): array
    {
        $entry = $this->service->allocateEntry($cashbookEntry, $request->validated());

        return [
            'message' => 'Transaction allocated',
            'data'    => new CashbookEntryResource($entry),
        ];
    }

    /**
     * WeConnectU Cashbook — remove the allocation from a single bank line.
     *
     * @param DeallocateEntryRequest $request
     * @param Community              $community
     * @param CashbookEntry          $cashbookEntry
     * @return array
     */
    public function deallocateEntry(DeallocateEntryRequest $request, Community $community, CashbookEntry $cashbookEntry): array
    {
        $this->service->deallocateEntry($cashbookEntry);

        return ['message' => 'Allocation removed'];
    }

    /**
     * WeConnectU Cashbook — split a single bank line across several accounts.
     *
     * @param SplitCashbookEntryRequest $request
     * @param Community                 $community
     * @param CashbookEntry             $cashbookEntry
     * @return array
     */
    public function splitEntry(SplitCashbookEntryRequest $request, Community $community, CashbookEntry $cashbookEntry): array
    {
        $data  = $request->validated();
        $entry = $this->service->splitEntry(
            $cashbookEntry,
            $data['lines'],
            (bool) ($data['save_template'] ?? false),
            $data['template_name'] ?? null
        );

        return [
            'message' => 'Transaction split',
            'data'    => new CashbookEntryResource($entry),
        ];
    }

    /**
     * WeConnectU Cashbook — parse a split-allocation spreadsheet for the modal.
     *
     * @param UploadSplitRequest $request
     * @param Community          $community
     * @param CashbookEntry      $cashbookEntry
     * @return array
     */
    public function uploadSplit(UploadSplitRequest $request, Community $community, CashbookEntry $cashbookEntry): array
    {
        return [
            'lines' => $this->service->parseSplitUpload($cashbookEntry, $request->file('file')),
        ];
    }

    /**
     * Export cashbook entries as a file download (CSV, Excel, or PDF).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportCashbookEntries(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportCashbookEntries($request->all());
    }

    /**
     * Return aggregate cashbook summary statistics.
     *
     * @param ShowCashbookSummaryRequest $request
     * @return array
     */
    public function showCashbookSummary(ShowCashbookSummaryRequest $request): array
    {
        return $this->service->showCashbookSummary($request->validated());
    }

    /**
     * Create a new cashbook entry.
     *
     * @param CreateCashbookEntryRequest $request
     * @return JsonResponse
     */
    public function createCashbookEntry(CreateCashbookEntryRequest $request): JsonResponse
    {
        return response()->json($this->service->createCashbookEntry($request->validated()), 201);
    }

    /**
     * Attempt automatic allocation of unallocated credit entries to outstanding invoices.
     *
     * @param AutoAllocateCashbookEntriesRequest $request
     * @return array
     */
    public function autoAllocateCashbookEntries(AutoAllocateCashbookEntriesRequest $request): array
    {
        return $this->service->autoAllocateCashbookEntries($request->validated());
    }

    /**
     * Bulk delete cashbook entries.
     *
     * @param DeleteCashbookEntriesRequest $request
     * @return array
     */
    public function deleteCashbookEntries(DeleteCashbookEntriesRequest $request): array
    {
        return $this->service->deleteCashbookEntries($request->input('entry_ids', []));
    }

    /**
     * Return a single cashbook entry.
     *
     * @param ShowCashbookEntryRequest $request
     * @param CashbookEntry            $cashbookEntry
     * @return CashbookEntryResource
     */
    public function showCashbookEntry(ShowCashbookEntryRequest $request, CashbookEntry $cashbookEntry): CashbookEntryResource
    {
        return $this->service->showCashbookEntry($cashbookEntry);
    }

    /**
     * Update a cashbook entry's editable fields.
     *
     * @param UpdateCashbookEntryRequest $request
     * @param CashbookEntry              $cashbookEntry
     * @return array
     */
    public function updateCashbookEntry(UpdateCashbookEntryRequest $request, CashbookEntry $cashbookEntry): array
    {
        return $this->service->updateCashbookEntry($cashbookEntry, $request->validated());
    }

    /**
     * Allocate a cashbook entry to an invoice.
     * Handles exact match, partial payment, and overpayment/split scenarios.
     *
     * @param AllocateCashbookEntryRequest $request
     * @param CashbookEntry                $cashbookEntry
     * @return array
     */
    public function allocateCashbookEntry(AllocateCashbookEntryRequest $request, CashbookEntry $cashbookEntry): array
    {
        return $this->service->allocateCashbookEntry($cashbookEntry, $request->validated());
    }

    /**
     * Deallocate a cashbook entry from its invoice.
     *
     * @param DeallocateCashbookEntryRequest $request
     * @param CashbookEntry                  $cashbookEntry
     * @return array
     */
    public function deallocateCashbookEntry(DeallocateCashbookEntryRequest $request, CashbookEntry $cashbookEntry): array
    {
        return $this->service->deallocateCashbookEntry($cashbookEntry, $request->validated());
    }

    /**
     * Delete a single cashbook entry.
     *
     * @param DeleteCashbookEntryRequest $request
     * @param CashbookEntry              $cashbookEntry
     * @return array
     */
    public function deleteCashbookEntry(DeleteCashbookEntryRequest $request, CashbookEntry $cashbookEntry): array
    {
        return $this->service->deleteCashbookEntry($cashbookEntry);
    }

    /**
     * Upload or replace the proof of payment file for a cashbook entry.
     *
     * @param \Illuminate\Http\Request $request
     * @param CashbookEntry            $cashbookEntry
     * @return CashbookEntryResource
     */
    public function uploadProofOfPayment(\Illuminate\Http\Request $request, CashbookEntry $cashbookEntry): CashbookEntryResource
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        return $this->service->uploadProofOfPayment($cashbookEntry, $request->file('file'));
    }

    /**
     * Stream the proof of payment file as a forced download.
     *
     * @param \Illuminate\Http\Request $request
     * @param CashbookEntry            $cashbookEntry
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadProofOfPayment(\Illuminate\Http\Request $request, CashbookEntry $cashbookEntry): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->downloadProofOfPayment($cashbookEntry);
    }

    /**
     * Delete the proof of payment file from a cashbook entry.
     *
     * @param \Illuminate\Http\Request $request
     * @param CashbookEntry            $cashbookEntry
     * @return array
     */
    public function deleteProofOfPayment(\Illuminate\Http\Request $request, CashbookEntry $cashbookEntry): array
    {
        return $this->service->deleteProofOfPayment($cashbookEntry);
    }
}
