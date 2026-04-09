<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResources;
use App\Http\Requests\Invoice\ShowInvoicesRequest;
use App\Http\Requests\Invoice\ShowInvoiceSummaryRequest;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\RunBillingRequest;
use App\Http\Requests\Invoice\CreateAdhocBillingRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\ResendInvoiceRequest;
use App\Http\Requests\Invoice\DeleteInvoiceRequest;
use App\Http\Requests\Invoice\DeleteInvoicesRequest;
use App\Http\Requests\Invoice\ShowDeletedInvoicesRequest;
use App\Http\Requests\Invoice\RestoreInvoiceRequest;
use App\Http\Requests\Invoice\ForceDeleteInvoiceRequest;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    protected InvoiceService $service;

    public function __construct(InvoiceService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of invoices for the authenticated tenant.
     *
     * @param ShowInvoicesRequest $request
     * @return InvoiceResources
     */
    public function showInvoices(ShowInvoicesRequest $request): InvoiceResources
    {
        return $this->service->showInvoices($request->validated());
    }

    /**
     * Export invoices as a file download (CSV, Excel, or PDF).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportInvoices(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportInvoices($request->all());
    }

    /**
     * Return aggregate invoice summary statistics.
     *
     * @param ShowInvoicesSummaryRequest $request
     * @return array
     */
    public function showInvoicesSummary(ShowInvoiceSummaryRequest $request): array
    {
        return $this->service->showInvoicesSummary($request->validated());
    }

    /**
     * Create a single invoice manually.
     *
     * @param CreateInvoiceRequest $request
     * @return array
     */
    public function createInvoice(CreateInvoiceRequest $request): array
    {
        return $this->service->createInvoice($request->validated());
    }

    /**
     * Run the billing engine for an estate and billing period.
     * Pass dry_run=true to preview without creating records.
     *
     * @param RunBillingRequest $request
     * @return array
     */
    public function runBilling(RunBillingRequest $request): array
    {
        return $this->service->runBilling($request->validated());
    }

    /**
     * Create ad-hoc invoices for a non-recurring charge type across selected units.
     *
     * @param CreateAdhocBillingRequest $request
     * @return array
     */
    public function createAdhocBilling(CreateAdhocBillingRequest $request): array
    {
        return $this->service->createAdhocBilling($request->validated());
    }

    /**
     * Bulk delete invoices.
     *
     * @param DeleteInvoicesRequest $request
     * @return array
     */
    public function deleteInvoices(DeleteInvoicesRequest $request): array
    {
        return $this->service->deleteInvoices($request->input('invoice_ids', []));
    }

    /**
     * Return a single invoice.
     *
     * @param ShowInvoiceRequest $request
     * @param Invoice            $invoice
     * @return InvoiceResource
     */
    public function showInvoice(ShowInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        return $this->service->showInvoice($invoice);
    }

    /**
     * Update an invoice.
     *
     * @param UpdateInvoiceRequest $request
     * @param Invoice              $invoice
     * @return array
     */
    public function updateInvoice(UpdateInvoiceRequest $request, Invoice $invoice): array
    {
        return $this->service->updateInvoice($invoice, $request->validated());
    }

    /**
     * Resend an invoice email to the recipient.
     *
     * @param ResendInvoiceRequest $request
     * @param Invoice              $invoice
     * @return array
     */
    public function resendInvoice(ResendInvoiceRequest $request, Invoice $invoice): array
    {
        return $this->service->resendInvoice($invoice);
    }

    /**
     * Stream a branded PDF download for the invoice.
     *
     * @param ShowInvoiceRequest $request
     * @param Invoice            $invoice
     * @return Response
     */
    public function downloadPdf(ShowInvoiceRequest $request, Invoice $invoice): Response
    {
        return $this->service->downloadPdf($invoice);
    }

    /**
     * Delete a single invoice (soft delete).
     *
     * @param DeleteInvoiceRequest $request
     * @param Invoice              $invoice
     * @return array
     */
    public function deleteInvoice(DeleteInvoiceRequest $request, Invoice $invoice): array
    {
        return $this->service->deleteInvoice($invoice);
    }

    /**
     * Return a paginated list of soft-deleted invoices (trash).
     *
     * @param ShowDeletedInvoicesRequest $request
     * @return InvoiceResources
     */
    public function showDeletedInvoices(ShowDeletedInvoicesRequest $request): InvoiceResources
    {
        return $this->service->showDeletedInvoices($request->validated());
    }

    /**
     * Restore a soft-deleted invoice.
     *
     * @param RestoreInvoiceRequest $request
     * @param Invoice               $invoice  Resolved via withTrashed binding ({deletedInvoice})
     * @return array
     */
    public function restoreInvoice(RestoreInvoiceRequest $request, Invoice $invoice): array
    {
        return $this->service->restoreInvoice($invoice);
    }

    /**
     * Permanently delete a soft-deleted invoice.
     *
     * @param ForceDeleteInvoiceRequest $request
     * @param Invoice                   $invoice  Resolved via withTrashed binding ({deletedInvoice})
     * @return array
     */
    public function forceDeleteInvoice(ForceDeleteInvoiceRequest $request, Invoice $invoice): array
    {
        return $this->service->forceDeleteInvoice($invoice);
    }
}
