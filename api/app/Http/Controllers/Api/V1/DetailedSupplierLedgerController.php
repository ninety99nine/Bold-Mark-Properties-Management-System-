<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\ShowDetailedSupplierLedgerRequest;
use App\Models\Community;
use App\Services\DetailedSupplierLedgerService;

class DetailedSupplierLedgerController extends Controller
{
    protected DetailedSupplierLedgerService $service;

    /**
     * DetailedSupplierLedgerController constructor.
     *
     * @param DetailedSupplierLedgerService $service
     */
    public function __construct(DetailedSupplierLedgerService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the WeConnectU Detailed Supplier Ledger — one transaction ledger
     * per selected supplier.
     *
     * @param ShowDetailedSupplierLedgerRequest $request
     * @param Community $community
     * @return array
     */
    public function run(ShowDetailedSupplierLedgerRequest $request, Community $community): array
    {
        return $this->service->run($community, $request->validated());
    }

    /**
     * Download the Detailed Supplier Ledger as a WeConnectU-faithful Excel workbook.
     *
     * @param ShowDetailedSupplierLedgerRequest $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(ShowDetailedSupplierLedgerRequest $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->export($community, $request->validated());
    }
}
