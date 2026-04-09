<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
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
use Illuminate\Http\Request;

class UnitController extends Controller
{
    protected UnitService $service;

    public function __construct(UnitService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of units for an estate.
     *
     * @param ShowUnitsRequest $request
     * @param Estate           $estate
     * @return UnitResources
     */
    public function showUnits(ShowUnitsRequest $request, Estate $estate): UnitResources
    {
        return $this->service->showUnits($estate, $request->validated());
    }

    /**
     * Export units for an estate as a file download (CSV, Excel, or PDF).
     *
     * @param Request $request
     * @param Estate  $estate
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportUnits(Request $request, Estate $estate): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportUnits($estate, $request->all());
    }

    /**
     * Create a new unit within an estate.
     *
     * @param CreateUnitRequest $request
     * @param Estate            $estate
     * @return array
     */
    public function createUnit(CreateUnitRequest $request, Estate $estate): array
    {
        return $this->service->createUnit($estate, $request->validated());
    }

    /**
     * Bulk delete units within an estate.
     *
     * @param DeleteUnitsRequest $request
     * @param Estate             $estate
     * @return array
     */
    public function deleteUnits(DeleteUnitsRequest $request, Estate $estate): array
    {
        return $this->service->deleteUnits($estate, $request->input('unit_ids', []));
    }

    /**
     * Download the bulk import template file (CSV or XLSX).
     *
     * @param Request $request
     * @param Estate  $estate
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadImportTemplate(Request $request, Estate $estate)
    {
        $format = in_array($request->query('format'), ['csv', 'xlsx']) ? $request->query('format') : 'csv';

        return $this->service->downloadImportTemplate($estate, $format);
    }

    /**
     * Parse an uploaded file and return columns + rows for the column-mapping step.
     *
     * @param BulkImportUnitsRequest $request
     * @param Estate                 $estate
     * @return array
     */
    public function parseImportFile(BulkImportUnitsRequest $request, Estate $estate): array
    {
        return $this->service->parseImportFile($estate, $request->file('file'));
    }

    /**
     * Bulk import pre-validated mapped rows.
     *
     * @param ImportUnitsRequest $request
     * @param Estate             $estate
     * @return array
     */
    public function bulkImportUnits(ImportUnitsRequest $request, Estate $estate): array
    {
        return $this->service->bulkImportUnits($estate, $request->input('rows', []));
    }

    /**
     * Return a single unit within an estate.
     *
     * @param ShowUnitRequest $request
     * @param Estate          $estate
     * @param Unit            $unit
     * @return UnitResource
     */
    public function showUnit(ShowUnitRequest $request, Estate $estate, Unit $unit): UnitResource
    {
        return $this->service->showUnit($estate, $unit);
    }

    /**
     * Update a unit within an estate.
     *
     * @param UpdateUnitRequest $request
     * @param Estate            $estate
     * @param Unit              $unit
     * @return array
     */
    public function updateUnit(UpdateUnitRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->updateUnit($estate, $unit, $request->validated());
    }

    /**
     * Delete a single unit within an estate.
     *
     * @param DeleteUnitRequest $request
     * @param Estate            $estate
     * @param Unit              $unit
     * @return array
     */
    public function deleteUnit(DeleteUnitRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->deleteUnit($estate, $unit);
    }

    /**
     * Return paginated activity entries for a unit.
     *
     * @param Request $request
     * @param Estate  $estate
     * @param Unit    $unit
     * @return array
     */
    public function showUnitActivities(Request $request, Estate $estate, Unit $unit): array
    {
        return $this->service->showUnitActivities($estate, $unit);
    }
}
