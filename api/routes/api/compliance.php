<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ComplianceController;

Route::prefix('compliance')
    ->controller(ComplianceController::class)
    ->middleware(['auth:api'])
    ->group(function () {

        // Portfolio-wide dashboard summary
        Route::get('/portfolio-summary', 'portfolioSummary')->name('compliance.portfolio-summary');

        // ── Checklists ──────────────────────────────────────────────
        Route::prefix('checklists')->group(function () {
            Route::get('/',  'showChecklists')->name('show.compliance-checklists');
            Route::post('/', 'createChecklist')->name('create.compliance-checklist');

            Route::prefix('{complianceChecklist}')->group(function () {
                Route::get('/',    'showChecklist')->name('show.compliance-checklist');
                Route::put('/',    'updateChecklist')->name('update.compliance-checklist');
                Route::delete('/', 'deleteChecklist')->name('delete.compliance-checklist');

                // ── Checklist Items ─────────────────────────────────
                Route::prefix('items')->group(function () {
                    Route::post('/', 'createChecklistItem')->name('create.compliance-checklist-item');

                    Route::prefix('{complianceChecklistItem}')->group(function () {
                        Route::put('/',                'updateChecklistItem')->name('update.compliance-checklist-item');
                        Route::delete('/',             'deleteChecklistItem')->name('delete.compliance-checklist-item');
                        Route::post('/attachments',    'uploadAttachments')->name('upload.compliance-attachments');
                        Route::get('/attachments/{attachment}/download', 'downloadAttachment')->name('download.compliance-attachment');
                        Route::delete('/attachments/{attachment}', 'deleteAttachment')->name('delete.compliance-attachment');
                    });
                });
            });
        });

        // ── Templates ───────────────────────────────────────────────
        Route::prefix('templates')->group(function () {
            Route::get('/',  'showTemplates')->name('show.compliance-templates');
            Route::post('/', 'createTemplate')->name('create.compliance-template');

            Route::prefix('{complianceTemplate}')->group(function () {
                Route::get('/',    'showTemplate')->name('show.compliance-template');
                Route::put('/',    'updateTemplate')->name('update.compliance-template');
                Route::delete('/', 'deleteTemplate')->name('delete.compliance-template');
            });
        });
    });
