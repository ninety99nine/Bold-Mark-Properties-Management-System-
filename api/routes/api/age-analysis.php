<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AgeAnalysisController;
use App\Http\Controllers\Api\V1\NoticeController;

Route::prefix('communities/{community}/age-analysis')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::controller(AgeAnalysisController::class)->group(function () {
            Route::get('/', 'getAgeAnalysis')->name('show.community.age.analysis');
            Route::get('/export', 'exportAgeAnalysis')->name('export.community.age.analysis');
        });

        Route::controller(NoticeController::class)->prefix('notices')->group(function () {
            Route::get('/preview', 'previewNotices')->name('preview.community.notices');
            Route::post('/run', 'runNotices')->name('run.community.notices');
            Route::get('/last-batch', 'showLastBatch')->name('show.community.notices.last-batch');
            Route::get('/{noticeBatch}', 'showBatch')->name('show.community.notice.batch');
            Route::get('/{noticeBatch}/items/{noticeBatchItem}/download', 'downloadItem')->name('download.community.notice.item');
            Route::post('/{noticeBatch}/items/{noticeBatchItem}/credit', 'creditItem')->name('credit.community.notice.item');
        });
    });
