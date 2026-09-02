<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\CreditNoticeItemRequest;
use App\Http\Requests\Community\RunNoticesRequest;
use App\Http\Requests\Community\ShowAgeAnalysisRequest;
use App\Http\Requests\Community\ShowNoticesRequest;
use App\Models\Community;
use App\Models\NoticeBatch;
use App\Models\NoticeBatchItem;
use App\Services\NoticeService;

class NoticeController extends Controller
{
    protected NoticeService $service;

    public function __construct(NoticeService $service)
    {
        $this->service = $service;
    }

    /**
     * Preview the automatic-notice run grouped by notice level (Legal Notices).
     *
     * @param ShowAgeAnalysisRequest $request
     * @param Community $community
     * @return array
     */
    public function previewNotices(ShowAgeAnalysisRequest $request, Community $community): array
    {
        return $this->service->previewNotices($community, $request->validated());
    }

    /**
     * Run automatic notices for the community's overdue customers.
     *
     * @param RunNoticesRequest $request
     * @param Community $community
     * @return array
     */
    public function runNotices(RunNoticesRequest $request, Community $community): array
    {
        return $this->service->runNotices($community, $request->validated());
    }

    /**
     * Show the most recent notice batch ("View last notice batch").
     *
     * @param ShowNoticesRequest $request
     * @param Community $community
     * @return array
     */
    public function showLastBatch(ShowNoticesRequest $request, Community $community): array
    {
        return $this->service->showLastBatch($community);
    }

    /**
     * Show a specific notice batch.
     *
     * @param ShowNoticesRequest $request
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @return array
     */
    public function showBatch(ShowNoticesRequest $request, Community $community, NoticeBatch $noticeBatch): array
    {
        return $this->service->showBatch($community, $noticeBatch);
    }

    /**
     * Download the letter PDF for a single batch item.
     *
     * @param ShowNoticesRequest $request
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @param NoticeBatchItem $noticeBatchItem
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadItem(ShowNoticesRequest $request, Community $community, NoticeBatch $noticeBatch, NoticeBatchItem $noticeBatchItem): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->downloadItem($community, $noticeBatch, $noticeBatchItem);
    }

    /**
     * Create a credit note for a notice charge (WeConnectU "[CREDIT]" action).
     *
     * @param CreditNoticeItemRequest $request
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @param NoticeBatchItem $noticeBatchItem
     * @return array
     */
    public function creditItem(CreditNoticeItemRequest $request, Community $community, NoticeBatch $noticeBatch, NoticeBatchItem $noticeBatchItem): array
    {
        return $this->service->creditNoticeItem($community, $noticeBatch, $noticeBatchItem, $request->validated());
    }
}
