<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ShowActualVsBudgetRequest;
use App\Http\Requests\Reports\ShowCashMovementRequest;
use App\Http\Requests\Reports\ShowGeneralLedgerRequest;
use App\Http\Requests\Reports\ShowIncomeStatementRequest;
use App\Http\Requests\Reports\ShowTrialBalanceRequest;
use App\Http\Requests\Reports\ShowVat201Request;
use App\Models\Community;
use App\Services\GlReportService;
use Symfony\Component\HttpFoundation\Response;

class GlReportController extends Controller
{
    public function __construct(private readonly GlReportService $service)
    {
    }

    /**
     * Trial Balance — each account's net movement in its natural column;
     * total debits equal total credits.
     *
     * @param ShowTrialBalanceRequest $request
     * @param Community $community
     * @return array|Response
     */
    public function trialBalance(ShowTrialBalanceRequest $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('trial-balance', $community, $data);
        }

        return $this->service->trialBalance($community, $data);
    }

    /**
     * Trial Balance Excel download.
     *
     * @param ShowTrialBalanceRequest $request
     * @param Community $community
     * @return Response
     */
    public function trialBalanceExport(ShowTrialBalanceRequest $request, Community $community): Response
    {
        return $this->service->export('trial-balance', $community, $request->validated());
    }

    /**
     * Detailed General Ledger — per account opening, transactions with a running
     * balance, and closing.
     *
     * @param ShowGeneralLedgerRequest $request
     * @param Community $community
     * @return array|Response
     */
    public function generalLedger(ShowGeneralLedgerRequest $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('general-ledger', $community, $data);
        }

        return $this->service->generalLedger($community, $data);
    }

    /**
     * Detailed General Ledger Excel download.
     *
     * @param ShowGeneralLedgerRequest $request
     * @param Community $community
     * @return Response
     */
    public function generalLedgerExport(ShowGeneralLedgerRequest $request, Community $community): Response
    {
        return $this->service->export('general-ledger', $community, $request->validated());
    }

    /**
     * Income Statement — income vs expense groups, subtotals and Net Surplus.
     *
     * @param ShowIncomeStatementRequest $request
     * @param Community $community
     * @return array|Response
     */
    public function incomeStatement(ShowIncomeStatementRequest $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('income-statement', $community, $data);
        }

        return $this->service->incomeStatement($community, $data);
    }

    /**
     * Income Statement Excel download.
     *
     * @param ShowIncomeStatementRequest $request
     * @param Community $community
     * @return Response
     */
    public function incomeStatementExport(ShowIncomeStatementRequest $request, Community $community): Response
    {
        return $this->service->export('income-statement', $community, $request->validated());
    }

    /**
     * Actual vs Budget (and Reserve Fund vs Reserve Fund Budget via fund=reserve).
     *
     * @param ShowActualVsBudgetRequest $request
     * @param Community $community
     * @return array|Response
     */
    public function actualVsBudget(ShowActualVsBudgetRequest $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('actual-vs-budget', $community, $data);
        }

        return $this->service->actualVsBudget($community, $data);
    }

    /**
     * Actual vs Budget Excel download.
     *
     * @param ShowActualVsBudgetRequest $request
     * @param Community $community
     * @return Response
     */
    public function actualVsBudgetExport(ShowActualVsBudgetRequest $request, Community $community): Response
    {
        return $this->service->export('actual-vs-budget', $community, $request->validated());
    }

    /**
     * VAT 201 — output vs input VAT summary from the VAT Control account.
     *
     * @param ShowVat201Request $request
     * @param Community $community
     * @return array|Response
     */
    public function vat201(ShowVat201Request $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('vat-201', $community, $data);
        }

        return $this->service->vat201($community, $data);
    }

    /**
     * VAT 201 Excel download.
     *
     * @param ShowVat201Request $request
     * @param Community $community
     * @return Response
     */
    public function vat201Export(ShowVat201Request $request, Community $community): Response
    {
        return $this->service->export('vat-201', $community, $request->validated());
    }

    /**
     * Basic Cash Movement — per bank/cashbook opening, receipts, payments, closing.
     *
     * @param ShowCashMovementRequest $request
     * @param Community $community
     * @return array|Response
     */
    public function cashMovement(ShowCashMovementRequest $request, Community $community): array|Response
    {
        $data = $request->validated();

        if ($request->boolean('export')) {
            return $this->service->export('cash-movement', $community, $data);
        }

        return $this->service->cashMovement($community, $data);
    }

    /**
     * Basic Cash Movement Excel download.
     *
     * @param ShowCashMovementRequest $request
     * @param Community $community
     * @return Response
     */
    public function cashMovementExport(ShowCashMovementRequest $request, Community $community): Response
    {
        return $this->service->export('cash-movement', $community, $request->validated());
    }
}
