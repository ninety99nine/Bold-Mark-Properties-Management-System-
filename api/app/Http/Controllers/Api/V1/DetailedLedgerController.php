<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ShowDetailedLedgerRequest;
use App\Models\Community;
use App\Models\LedgerReportBatch;
use App\Services\DetailedLedgerService;
use Illuminate\Http\Request;

class DetailedLedgerController extends Controller
{
    protected DetailedLedgerService $service;

    public function __construct(DetailedLedgerService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the WeConnectU Detailed Customer Ledger — one transaction ledger
     * per selected customer.
     *
     * @param ShowDetailedLedgerRequest $request
     * @param Community $community
     * @return array
     */
    public function run(ShowDetailedLedgerRequest $request, Community $community): array
    {
        return $this->service->run($community, $request->validated());
    }

    /**
     * Download the Detailed Customer Ledger as a WeConnectU-faithful Excel workbook.
     *
     * @param ShowDetailedLedgerRequest $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(ShowDetailedLedgerRequest $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->export($community, $request->validated());
    }

    /**
     * Request an emailed report — logs a Recent Email Reports row.
     *
     * @param ShowDetailedLedgerRequest $request
     * @param Community $community
     * @return array
     */
    public function emailReport(ShowDetailedLedgerRequest $request, Community $community): array
    {
        return $this->service->requestEmailReport($community, $request->validated());
    }

    /**
     * List the community's recent ledger report requests.
     *
     * @param Request $request
     * @param Community $community
     * @return array
     */
    public function reports(Request $request, Community $community): array
    {
        abort_unless($request->user()->can('viewCustomers', $community), 403);

        return ['data' => $this->service->listReports($community)];
    }

    /**
     * Re-download a stored report's Excel workbook.
     *
     * @param Request $request
     * @param Community $community
     * @param LedgerReportBatch $ledgerReport
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadReport(Request $request, Community $community, LedgerReportBatch $ledgerReport): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->user()->can('viewCustomers', $community), 403);
        abort_unless($ledgerReport->community_id === $community->id, 404);

        return $this->service->downloadReport($community, $ledgerReport);
    }
}
