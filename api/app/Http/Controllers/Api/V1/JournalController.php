<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\CreateJournalBatchRequest;
use App\Http\Requests\Journal\DeleteJournalBatchRequest;
use App\Http\Requests\Journal\ShowJournalBatchesRequest;
use App\Http\Requests\Journal\ShowJournalBatchRequest;
use App\Http\Requests\Journal\UpdateJournalBatchRequest;
use App\Http\Requests\Journal\UploadJournalBatchRequest;
use App\Http\Resources\JournalBatchResource;
use App\Models\JournalBatch;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class JournalController extends Controller
{
    protected JournalService $service;

    public function __construct(JournalService $service)
    {
        $this->service = $service;
    }

    /**
     * Paginated list of journal batches for a community.
     *
     * @param ShowJournalBatchesRequest $request
     * @return ResourceCollection
     */
    public function showJournalBatches(ShowJournalBatchesRequest $request): ResourceCollection
    {
        return $this->service->showJournalBatches($request->validated());
    }

    /**
     * Create a balanced manual journal batch.
     *
     * @param CreateJournalBatchRequest $request
     * @return JsonResponse
     */
    public function createJournalBatch(CreateJournalBatchRequest $request): JsonResponse
    {
        return response()->json($this->service->createJournalBatch($request->validated()), 201);
    }

    /**
     * Import a journal batch from an uploaded spreadsheet.
     *
     * @param UploadJournalBatchRequest $request
     * @return JsonResponse
     */
    public function uploadJournalBatch(UploadJournalBatchRequest $request): JsonResponse
    {
        return response()->json($this->service->uploadJournalBatch($request->validated()), 201);
    }

    /**
     * Show a single journal batch with its lines.
     *
     * @param ShowJournalBatchRequest $request
     * @param JournalBatch            $journalBatch
     * @return JournalBatchResource
     */
    public function showJournalBatch(ShowJournalBatchRequest $request, JournalBatch $journalBatch): JournalBatchResource
    {
        return $this->service->showJournalBatch($journalBatch);
    }

    /**
     * Update (edit) a journal batch.
     *
     * @param UpdateJournalBatchRequest $request
     * @param JournalBatch              $journalBatch
     * @return JsonResponse
     */
    public function updateJournalBatch(UpdateJournalBatchRequest $request, JournalBatch $journalBatch): JsonResponse
    {
        return response()->json($this->service->updateJournalBatch($journalBatch, $request->validated()));
    }

    /**
     * Delete a journal batch.
     *
     * @param DeleteJournalBatchRequest $request
     * @param JournalBatch              $journalBatch
     * @return JsonResponse
     */
    public function deleteJournalBatch(DeleteJournalBatchRequest $request, JournalBatch $journalBatch): JsonResponse
    {
        return response()->json($this->service->deleteJournalBatch($journalBatch));
    }

    /**
     * Download the blank batch-upload template.
     *
     * @return Response
     */
    public function downloadTemplate(): Response
    {
        return $this->service->downloadTemplate();
    }

    /**
     * Download the batch list as Excel.
     *
     * @param ShowJournalBatchesRequest $request
     * @return Response
     */
    public function downloadBatchesExcel(ShowJournalBatchesRequest $request): Response
    {
        return $this->service->downloadBatchesExcel($request->validated());
    }

    /**
     * Download a single batch as Excel (per-row download icon).
     *
     * @param ShowJournalBatchRequest $request
     * @param JournalBatch            $journalBatch
     * @return Response
     */
    public function downloadBatch(ShowJournalBatchRequest $request, JournalBatch $journalBatch): Response
    {
        return $this->service->downloadBatch($journalBatch);
    }
}
