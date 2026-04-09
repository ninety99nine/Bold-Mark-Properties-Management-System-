<?php

namespace App\Services;

use App\Models\TableView;
use App\Http\Resources\TableViewResource;
use App\Http\Resources\TableViewResources;
use Illuminate\Support\Facades\Auth;

class TableViewService extends BaseService
{
    /**
     * Return all saved views for the authenticated user in the given context.
     *
     * Views are scoped to the user — no one else can see them.
     * No pagination needed here; a user is unlikely to have > 50 saved views
     * in any single context, so we return all as a simple collection.
     */
    public function indexTableViews(string $context): TableViewResources
    {
        $views = TableView::where('user_id', Auth::id())
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('context', $context)
            ->orderBy('created_at', 'asc')
            ->get();

        return new TableViewResources($views);
    }

    /**
     * Create a new saved view for the authenticated user.
     */
    public function createTableView(array $data): array
    {
        $user = Auth::user();

        $view = TableView::create(array_merge($data, [
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
        ]));

        return $this->showCreatedResource($view);
    }

    /**
     * Update an existing saved view.
     * Only the owner of the view may update it (enforced in the FormRequest).
     */
    public function updateTableView(TableView $view, array $data): array
    {
        $view->update($data);

        return $this->showUpdatedResource($view);
    }

    /**
     * Delete a saved view.
     * Only the owner of the view may delete it (enforced in the FormRequest).
     */
    public function deleteTableView(TableView $view): array
    {
        $deleted = $view->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'View deleted' : 'View delete unsuccessful',
        ];
    }
}
