<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\SupplierService;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierResources;
use App\Http\Requests\Supplier\ShowSuppliersRequest;
use App\Http\Requests\Supplier\ShowSupplierRequest;
use App\Http\Requests\Supplier\CreateSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Requests\Supplier\DeleteSupplierRequest;
use App\Http\Requests\Supplier\DeleteSuppliersRequest;
use App\Http\Requests\Supplier\SupplierOptionsRequest;
use App\Http\Requests\Supplier\ExportSuppliersRequest;
use App\Http\Requests\Supplier\DownloadSupplierTemplateRequest;
use App\Http\Requests\Supplier\ImportSuppliersRequest;
use App\Http\Requests\Supplier\ShowSupplierDocumentsRequest;
use App\Http\Requests\Supplier\UploadSupplierDocumentRequest;
use App\Http\Requests\Supplier\DeleteSupplierDocumentRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierController extends Controller
{
    /**
     * @var SupplierService
     */
    protected $service;

    /**
     * SupplierController constructor.
     *
     * @param SupplierService $service
     */
    public function __construct(SupplierService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of suppliers for the authenticated user's organization.
     *
     * @param ShowSuppliersRequest $request
     * @return SupplierResources|array
     */
    public function showSuppliers(ShowSuppliersRequest $request): SupplierResources|array
    {
        return $this->service->showSuppliers($request->validated());
    }

    /**
     * Return the option sets for the supplier form and groups tab.
     *
     * @param SupplierOptionsRequest $request
     * @return array
     */
    public function supplierOptions(SupplierOptionsRequest $request): array
    {
        return $this->service->supplierOptions($request->input('community_id'));
    }

    /**
     * Export the filtered supplier list as an xlsx download.
     *
     * @param ExportSuppliersRequest $request
     * @return BinaryFileResponse
     */
    public function exportSuppliers(ExportSuppliersRequest $request): BinaryFileResponse
    {
        return $this->service->exportSuppliers($request->validated());
    }

    /**
     * Download the blank supplier-upload template.
     *
     * @param DownloadSupplierTemplateRequest $request
     * @return BinaryFileResponse
     */
    public function downloadUploadTemplate(DownloadSupplierTemplateRequest $request): BinaryFileResponse
    {
        return $this->service->downloadUploadTemplate();
    }

    /**
     * Import suppliers from an uploaded spreadsheet.
     *
     * @param ImportSuppliersRequest $request
     * @return array
     */
    public function importSuppliers(ImportSuppliersRequest $request): array
    {
        return $this->service->importSuppliers($request->file('file'), $request->input('community_id'));
    }

    /**
     * Create a supplier.
     *
     * @param CreateSupplierRequest $request
     * @return array
     */
    public function createSupplier(CreateSupplierRequest $request): array
    {
        return $this->service->createSupplier($request->validated());
    }

    /**
     * Bulk delete suppliers.
     *
     * @param DeleteSuppliersRequest $request
     * @return array
     */
    public function deleteSuppliers(DeleteSuppliersRequest $request): array
    {
        return $this->service->deleteSuppliers($request->input('supplier_ids', []));
    }

    /**
     * Return a single supplier.
     *
     * @param ShowSupplierRequest $request
     * @param Supplier $supplier
     * @return SupplierResource
     */
    public function showSupplier(ShowSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        return $this->service->showSupplier($supplier);
    }

    /**
     * Update a supplier's details.
     *
     * @param UpdateSupplierRequest $request
     * @param Supplier $supplier
     * @return array
     */
    public function updateSupplier(UpdateSupplierRequest $request, Supplier $supplier): array
    {
        return $this->service->updateSupplier($supplier, $request->validated());
    }

    /**
     * Return the documents attached to a supplier.
     *
     * @param ShowSupplierDocumentsRequest $request
     * @param Supplier $supplier
     * @return array
     */
    public function showDocuments(ShowSupplierDocumentsRequest $request, Supplier $supplier): array
    {
        return $this->service->showDocuments($supplier);
    }

    /**
     * Upload a document to a supplier.
     *
     * @param UploadSupplierDocumentRequest $request
     * @param Supplier $supplier
     * @return array
     */
    public function uploadDocument(UploadSupplierDocumentRequest $request, Supplier $supplier): array
    {
        return $this->service->uploadDocument($supplier, [
            'name' => $request->input('name'),
            'file' => $request->file('file'),
        ]);
    }

    /**
     * Delete a supplier document.
     *
     * @param DeleteSupplierDocumentRequest $request
     * @param Supplier $supplier
     * @param SupplierDocument $supplierDocument
     * @return array
     */
    public function deleteDocument(DeleteSupplierDocumentRequest $request, Supplier $supplier, SupplierDocument $supplierDocument): array
    {
        return $this->service->deleteDocument($supplierDocument);
    }

    /**
     * Delete a single supplier.
     *
     * @param DeleteSupplierRequest $request
     * @param Supplier $supplier
     * @return array
     */
    public function deleteSupplier(DeleteSupplierRequest $request, Supplier $supplier): array
    {
        return $this->service->deleteSupplier($supplier);
    }
}
