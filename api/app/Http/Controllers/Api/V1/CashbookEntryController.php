<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashbookEntry;
use App\Services\CashbookEntryService;
use App\Http\Resources\CashbookEntryResource;
use App\Http\Resources\CashbookEntryResources;
use App\Http\Requests\CashbookEntry\ShowCashbookEntriesRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookSummaryRequest;
use App\Http\Requests\CashbookEntry\CreateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\AutoAllocateCashbookEntriesRequest;
use App\Http\Requests\CashbookEntry\ShowCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\UpdateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\AllocateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeallocateCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeleteCashbookEntryRequest;
use App\Http\Requests\CashbookEntry\DeleteCashbookEntriesRequest;

class CashbookEntryController extends Controller
{
    protected CashbookEntryService $service;

    public function __construct(CashbookEntryService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of cashbook entries for the authenticated tenant.
     *
     * @param ShowCashbookEntriesRequest $request
     * @return CashbookEntryResources
     */
    public function showCashbookEntries(ShowCashbookEntriesRequest $request): CashbookEntryResources
    {
        return $this->service->showCashbookEntries($request->validated());
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
     * @return array
     */
    public function createCashbookEntry(CreateCashbookEntryRequest $request): array
    {
        return $this->service->createCashbookEntry($request->validated());
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
        return $this->service->deleteCashbookEntries($request->input('cashbook_entry_ids', []));
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
