<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierInvoice\CreateSupplierInvoiceRequest;
use App\Http\Requests\SupplierInvoice\DeleteSupplierInvoiceRequest;
use App\Http\Requests\SupplierInvoice\DownloadSupplierInvoiceRequest;
use App\Http\Requests\SupplierInvoice\ShowSupplierInvoiceRequest;
use App\Http\Requests\SupplierInvoice\ShowSupplierInvoicesRequest;
use App\Http\Requests\SupplierInvoice\UpdateSupplierInvoiceRequest;
use App\Http\Resources\SupplierInvoiceResource;
use App\Models\Community;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SupplierInvoiceController extends Controller
{
    /**
     * @param SupplierInvoiceService $service
     */
    public function __construct(protected SupplierInvoiceService $service)
    {
    }

    /**
     * Return a paginated list of a community's supplier invoices (GRVs).
     *
     * @param ShowSupplierInvoicesRequest $request
     * @param Community $community
     * @return ResourceCollection
     */
    public function showSupplierInvoices(ShowSupplierInvoicesRequest $request, Community $community): ResourceCollection
    {
        return $this->service->showSupplierInvoices($community, $request->validated());
    }

    /**
     * Return a single supplier invoice.
     *
     * @param ShowSupplierInvoiceRequest $request
     * @param Community $community
     * @param SupplierInvoice $supplierInvoice
     * @return SupplierInvoiceResource
     */
    public function showSupplierInvoice(ShowSupplierInvoiceRequest $request, Community $community, SupplierInvoice $supplierInvoice): SupplierInvoiceResource
    {
        return $this->service->showSupplierInvoice($supplierInvoice);
    }

    /**
     * Create (or draft) a supplier invoice.
     *
     * @param CreateSupplierInvoiceRequest $request
     * @param Community $community
     * @return array
     */
    public function createSupplierInvoice(CreateSupplierInvoiceRequest $request, Community $community): array
    {
        return $this->service->createSupplierInvoice($community, $request->validated());
    }

    /**
     * Update a supplier invoice.
     *
     * @param UpdateSupplierInvoiceRequest $request
     * @param Community $community
     * @param SupplierInvoice $supplierInvoice
     * @return array
     */
    public function updateSupplierInvoice(UpdateSupplierInvoiceRequest $request, Community $community, SupplierInvoice $supplierInvoice): array
    {
        return $this->service->updateSupplierInvoice($supplierInvoice, $request->validated());
    }

    /**
     * Download the WeConnectU "SUPPLIER TAX INVOICE" PDF for a GRV.
     *
     * @param DownloadSupplierInvoiceRequest $request
     * @param Community $community
     * @param SupplierInvoice $supplierInvoice
     * @return Response
     */
    public function downloadPdf(DownloadSupplierInvoiceRequest $request, Community $community, SupplierInvoice $supplierInvoice): Response
    {
        return $this->service->downloadPdf($supplierInvoice);
    }

    /**
     * Delete a supplier invoice.
     *
     * @param DeleteSupplierInvoiceRequest $request
     * @param Community $community
     * @param SupplierInvoice $supplierInvoice
     * @return array
     */
    public function deleteSupplierInvoice(DeleteSupplierInvoiceRequest $request, Community $community, SupplierInvoice $supplierInvoice): array
    {
        return $this->service->deleteSupplierInvoice($supplierInvoice);
    }
}
