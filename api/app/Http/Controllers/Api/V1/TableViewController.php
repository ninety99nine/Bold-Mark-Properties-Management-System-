<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableView;
use App\Services\TableViewService;
use App\Http\Resources\TableViewResources;
use App\Http\Requests\TableView\IndexTableViewsRequest;
use App\Http\Requests\TableView\CreateTableViewRequest;
use App\Http\Requests\TableView\UpdateTableViewRequest;
use App\Http\Requests\TableView\DeleteTableViewRequest;

class TableViewController extends Controller
{
    protected TableViewService $service;

    public function __construct(TableViewService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all saved views for the authenticated user in the given context.
     * Requires ?context=units (or invoices, cashbook, age-analysis, users).
     */
    public function index(IndexTableViewsRequest $request): TableViewResources
    {
        return $this->service->indexTableViews($request->validated()['context']);
    }

    /**
     * Create a new saved view.
     */
    public function store(CreateTableViewRequest $request): array
    {
        return $this->service->createTableView($request->validated());
    }

    /**
     * Update an existing saved view.
     */
    public function update(UpdateTableViewRequest $request, TableView $tableView): array
    {
        return $this->service->updateTableView($tableView, $request->validated());
    }

    /**
     * Delete a saved view.
     */
    public function destroy(DeleteTableViewRequest $request, TableView $tableView): array
    {
        return $this->service->deleteTableView($tableView);
    }
}
