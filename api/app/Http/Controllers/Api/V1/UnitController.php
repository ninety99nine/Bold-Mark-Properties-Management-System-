<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Services\UnitService;
use App\Http\Resources\UnitResource;
use App\Http\Resources\UnitResources;
use App\Http\Requests\Unit\ShowUnitsRequest;
use App\Http\Requests\Unit\CreateUnitRequest;
use App\Http\Requests\Unit\ShowUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Requests\Unit\DeleteUnitRequest;
use App\Http\Requests\Unit\DeleteUnitsRequest;
use App\Http\Requests\Unit\BulkImportUnitsRequest;
use App\Http\Requests\Unit\ImportUnitsRequest;
use App\Http\Requests\Unit\ImportOccupantsRequest;
use App\Http\Requests\Unit\ToggleUnitDevelopmentRequest;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    protected UnitService $service;

    public function __construct(UnitService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of units for an community.
     *
     * @param ShowUnitsRequest $request
     * @param Community           $community
     * @return UnitResources
     */
    public function showUnits(ShowUnitsRequest $request, Community $community): UnitResources
    {
        return $this->service->showUnits($community, $request->validated());
    }

    /**
     * Export units for an community as a file download (CSV, Excel, or PDF).
     *
     * @param Request $request
     * @param Community  $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportUnits(Request $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportUnits($community, $request->all());
    }

    /**
     * Export the community's occupant contact list as a file download (WeConnectU parity).
     *
     * @param Request $request
     * @param Community  $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportOccupants(Request $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportOccupants($community, $request->all());
    }

    /**
     * Create a new unit within an community.
     *
     * @param CreateUnitRequest $request
     * @param Community            $community
     * @return array
     */
    public function createUnit(CreateUnitRequest $request, Community $community): array
    {
        return $this->service->createUnit($community, $request->validated());
    }

    /**
     * Bulk delete units within an community.
     *
     * @param DeleteUnitsRequest $request
     * @param Community             $community
     * @return array
     */
    public function deleteUnits(DeleteUnitsRequest $request, Community $community): array
    {
        return $this->service->deleteUnits($community, $request->input('unit_ids', []));
    }

    /**
     * Download the bulk import template file (CSV or XLSX).
     *
     * @param Request $request
     * @param Community  $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadImportTemplate(Request $request, Community $community)
    {
        $format = in_array($request->query('format'), ['csv', 'xlsx']) ? $request->query('format') : 'csv';

        return $this->service->downloadImportTemplate($community, $format);
    }

    /**
     * Parse an uploaded file and return columns + rows for the column-mapping step.
     *
     * @param BulkImportUnitsRequest $request
     * @param Community                 $community
     * @return array
     */
    public function parseImportFile(BulkImportUnitsRequest $request, Community $community): array
    {
        return $this->service->parseImportFile($community, $request->file('file'));
    }

    /**
     * Bulk import pre-validated mapped rows.
     *
     * @param ImportUnitsRequest $request
     * @param Community             $community
     * @return array
     */
    public function bulkImportUnits(ImportUnitsRequest $request, Community $community): array
    {
        return $this->service->bulkImportUnits($community, $request->input('rows', []));
    }

    /**
     * Download the occupants-import template file (CSV or XLSX).
     *
     * @param Request $request
     * @param Community  $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadOccupantsTemplate(Request $request, Community $community)
    {
        $format = in_array($request->query('format'), ['csv', 'xlsx']) ? $request->query('format') : 'csv';

        return $this->service->downloadOccupantsTemplate($community, $format);
    }

    /**
     * Parse an uploaded occupants file and return columns + rows for column-mapping.
     *
     * @param BulkImportUnitsRequest $request
     * @param Community                 $community
     * @return array
     */
    public function parseOccupantsImportFile(BulkImportUnitsRequest $request, Community $community): array
    {
        return $this->service->parseOccupantsImportFile($community, $request->file('file'));
    }

    /**
     * Import pre-mapped occupant rows, matching each to an existing unit.
     *
     * @param ImportOccupantsRequest $request
     * @param Community                 $community
     * @return array
     */
    public function importOccupants(ImportOccupantsRequest $request, Community $community): array
    {
        return $this->service->importOccupants($community, $request->input('rows', []));
    }

    /**
     * Return a single unit within an community.
     *
     * @param ShowUnitRequest $request
     * @param Community          $community
     * @param Unit            $unit
     * @return UnitResource
     */
    public function showUnit(ShowUnitRequest $request, Community $community, Unit $unit): UnitResource
    {
        return $this->service->showUnit($community, $unit);
    }

    /**
     * Update a unit within an community.
     *
     * @param UpdateUnitRequest $request
     * @param Community            $community
     * @param Unit              $unit
     * @return array
     */
    public function updateUnit(UpdateUnitRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->updateUnit($community, $unit, $request->validated());
    }

    /**
     * Toggle a unit's development status (WeConnectU "Set as development unit").
     *
     * @param Request $request
     * @param Community  $community
     * @param Unit    $unit
     * @return array
     */
    public function toggleDevelopment(ToggleUnitDevelopmentRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->toggleDevelopment($community, $unit);
    }

    /**
     * Add an additional owner to a unit (WeConnectU "Add Owner").
     */
    public function addOwner(\App\Http\Requests\Unit\CreateUnitOwnerRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->addOwner($community, $unit, $request->validated());
    }

    /**
     * Update a single owner record.
     */
    public function updateOwnerRecord(\App\Http\Requests\Unit\UpdateUnitOwnerRequest $request, Community $community, Unit $unit, \App\Models\Owner $owner): array
    {
        abort_unless($owner->unit_id === $unit->id, 404);

        return $this->service->updateOwnerRecord($community, $unit, $owner, $request->validated());
    }

    /**
     * Delete an owner record.
     */
    public function deleteOwner(\App\Http\Requests\Unit\DeleteUnitOwnerRequest $request, Community $community, Unit $unit, \App\Models\Owner $owner): array
    {
        abort_unless($owner->unit_id === $unit->id, 404);

        return $this->service->deleteOwner($community, $unit, $owner);
    }

    /**
     * Add a collection note to a unit's finances log.
     */
    public function addCollectionNote(\App\Http\Requests\CollectionNote\CreateCollectionNoteRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->addCollectionNote($community, $unit, $request->input('note'));
    }

    /**
     * List a unit's collection notes (newest first).
     */
    public function showCollectionNotes(Community $community, Unit $unit): array
    {
        return $this->service->showCollectionNotes($community, $unit);
    }

    /**
     * Download a unit's customer statement (xlsx / pdf / csv).
     */
    public function downloadStatement(Request $request, Community $community, Unit $unit): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->downloadStatement($community, $unit, $request->all());
    }

    /**
     * E-mail a unit's customer statement to the owner.
     */
    public function emailStatement(Request $request, Community $community, Unit $unit): array
    {
        return $this->service->emailStatement($community, $unit, $request->all());
    }

    /**
     * List a unit's communication (e-mail) log.
     */
    public function showCommunications(Request $request, Community $community, Unit $unit)
    {
        return $this->service->showCommunications($community, $unit);
    }

    /**
     * Compose + send a unit e-mail (logged to the communication log).
     */
    public function sendCommunication(\App\Http\Requests\Communication\SendUnitCommunicationRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->sendCommunication($community, $unit, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * Re-send a logged communication.
     */
    public function resendCommunication(Request $request, Community $community, Unit $unit, \App\Models\UnitCommunication $communication): array
    {
        abort_unless($communication->unit_id === $unit->id, 404);

        return $this->service->resendCommunication($community, $unit, $communication);
    }

    /**
     * Download a communication as a PDF.
     */
    public function downloadCommunication(Request $request, Community $community, Unit $unit, \App\Models\UnitCommunication $communication): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($communication->unit_id === $unit->id, 404);

        return $this->service->downloadCommunication($community, $unit, $communication);
    }

    /**
     * List a unit's offences.
     */
    public function showOffences(Request $request, Community $community, Unit $unit)
    {
        return $this->service->showOffences($community, $unit);
    }

    /**
     * Create an offence for a unit.
     */
    public function createOffence(\App\Http\Requests\Offence\CreateOffenceRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->createOffence($community, $unit, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * Update an offence.
     */
    public function updateOffence(\App\Http\Requests\Offence\UpdateOffenceRequest $request, Community $community, Unit $unit, \App\Models\UnitOffence $offence): array
    {
        abort_unless($offence->unit_id === $unit->id, 404);

        return $this->service->updateOffence($community, $unit, $offence, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * Delete an offence.
     */
    public function deleteOffence(\App\Http\Requests\Offence\DeleteOffenceRequest $request, Community $community, Unit $unit, \App\Models\UnitOffence $offence): array
    {
        abort_unless($offence->unit_id === $unit->id, 404);

        return $this->service->deleteOffence($community, $unit, $offence);
    }

    /**
     * List a unit's tasks.
     */
    public function showTasks(Request $request, Community $community, Unit $unit)
    {
        return $this->service->showTasks($community, $unit);
    }

    /**
     * Create a task for a unit.
     */
    public function createTask(\App\Http\Requests\Task\CreateTaskRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->createTask($community, $unit, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * Update a task.
     */
    public function updateTask(\App\Http\Requests\Task\UpdateTaskRequest $request, Community $community, Unit $unit, \App\Models\UnitTask $task): array
    {
        abort_unless($task->unit_id === $unit->id, 404);

        return $this->service->updateTask($community, $unit, $task, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * Delete a task.
     */
    public function deleteTask(\App\Http\Requests\Task\DeleteTaskRequest $request, Community $community, Unit $unit, \App\Models\UnitTask $task): array
    {
        abort_unless($task->unit_id === $unit->id, 404);

        return $this->service->deleteTask($community, $unit, $task);
    }

    /**
     * Add a feedback/update entry to a task.
     */
    public function addTaskUpdate(\App\Http\Requests\Task\AddTaskUpdateRequest $request, Community $community, Unit $unit, \App\Models\UnitTask $task): array
    {
        abort_unless($task->unit_id === $unit->id, 404);

        return $this->service->addTaskUpdate($community, $unit, $task, $request->validated(), $request->file('attachments') ?? []);
    }

    /**
     * List a unit's uploaded documents.
     */
    public function showDocuments(Request $request, Community $community, Unit $unit)
    {
        return $this->service->showDocuments($community, $unit);
    }

    /**
     * Upload a document for a unit.
     */
    public function uploadDocument(\App\Http\Requests\Document\UploadDocumentRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->uploadDocument($community, $unit, $request->validated(), $request->file('document'));
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(\App\Http\Requests\Document\DeleteDocumentRequest $request, Community $community, Unit $unit, \App\Models\UnitDocument $document): array
    {
        abort_unless($document->unit_id === $unit->id, 404);

        return $this->service->deleteDocument($community, $unit, $document);
    }

    /**
     * Delete a single unit within an community.
     *
     * @param DeleteUnitRequest $request
     * @param Community            $community
     * @param Unit              $unit
     * @return array
     */
    public function deleteUnit(DeleteUnitRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->deleteUnit($community, $unit);
    }

    /**
     * Return paginated activity entries for a unit.
     *
     * @param Request $request
     * @param Community  $community
     * @param Unit    $unit
     * @return array
     */
    public function showUnitActivities(Request $request, Community $community, Unit $unit): array
    {
        return $this->service->showUnitActivities($community, $unit);
    }
}
