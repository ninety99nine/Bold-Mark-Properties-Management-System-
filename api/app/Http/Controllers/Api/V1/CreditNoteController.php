<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreditNote\CreateCreditNoteRequest;
use App\Http\Requests\CreditNote\DeleteCreditNoteRequest;
use App\Http\Requests\CreditNote\ShowCreditableInvoicesRequest;
use App\Http\Requests\CreditNote\ShowCreditNoteRequest;
use App\Http\Requests\CreditNote\ShowCreditNotesRequest;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\CreditNoteResources;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreditNoteController extends Controller
{
    protected CreditNoteService $service;

    public function __construct(CreditNoteService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of credit notes for the organization.
     *
     * @param ShowCreditNotesRequest $request
     * @return CreditNoteResources
     */
    public function showCreditNotes(ShowCreditNotesRequest $request): CreditNoteResources
    {
        return $this->service->showCreditNotes($request->validated());
    }

    /**
     * Return a customer's last 20 invoices with their creditable line items.
     *
     * @param ShowCreditableInvoicesRequest $request
     * @return JsonResponse
     */
    public function showCreditableInvoices(ShowCreditableInvoicesRequest $request): JsonResponse
    {
        return response()->json($this->service->showCreditableInvoices($request->validated()));
    }

    /**
     * Create a WeConnectU-style multi-line credit note.
     *
     * @param CreateCreditNoteRequest $request
     * @return JsonResponse
     */
    public function createCreditNote(CreateCreditNoteRequest $request): JsonResponse
    {
        return response()->json($this->service->createCreditNote($request->validated()), 201);
    }

    /**
     * Return a single credit note.
     *
     * @param ShowCreditNoteRequest $request
     * @param CreditNote            $creditNote
     * @return CreditNoteResource
     */
    public function showCreditNote(ShowCreditNoteRequest $request, CreditNote $creditNote): CreditNoteResource
    {
        return $this->service->showCreditNote($creditNote);
    }

    /**
     * Stream a branded PDF for the credit note.
     *
     * @param ShowCreditNoteRequest $request
     * @param CreditNote            $creditNote
     * @return Response
     */
    public function downloadPdf(ShowCreditNoteRequest $request, CreditNote $creditNote): Response
    {
        return $this->service->downloadPdf($creditNote);
    }

    /**
     * Delete a credit note.
     *
     * @param DeleteCreditNoteRequest $request
     * @param CreditNote              $creditNote
     * @return JsonResponse
     */
    public function deleteCreditNote(DeleteCreditNoteRequest $request, CreditNote $creditNote): JsonResponse
    {
        return response()->json($this->service->deleteCreditNote($creditNote));
    }
}
