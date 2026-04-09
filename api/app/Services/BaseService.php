<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseService
{
    /**
     * The current Eloquent builder being used.
     *
     * @var Builder|null
     */
    protected ?Builder $query = null;

    /**
     * The current HTTP request.
     *
     * @var Request
     */
    protected Request $request;

    /**
     * Default number of rows per page.
     *
     * @var int
     */
    protected int $defaultPerPage = 15;

    /**
     * BaseService constructor.
     */
    public function __construct()
    {
        $this->request = request();
    }

    /**
     * Set the Eloquent query builder to work with.
     * Returns $this for fluent chaining.
     *
     * @param Builder $query
     * @return static
     */
    public function setQuery(Builder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the current query builder (after any modifications).
     *
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * The column to apply date-range filtering on.
     * Child services can override this to target a different column
     * (e.g. CashbookEntryService uses 'date' instead of 'created_at').
     *
     * @var string
     */
    protected string $dateRangeColumn = 'created_at';

    /**
     * Apply the date-range filter from the current HTTP request.
     *
     * Reads: _date_range, _date_range_start, _date_range_end
     * Delegates to applyDateRange() with $this->dateRangeColumn.
     * Returns $this for fluent chaining.
     *
     * @return static
     */
    public function applyDateRangeFromRequest(): static
    {
        $this->query = $this->applyDateRange(
            $this->query,
            $this->request->input('_date_range'),
            $this->request->input('_date_range_start'),
            $this->request->input('_date_range_end'),
            $this->dateRangeColumn
        );

        return $this;
    }

    /**
     * Apply search filtering using the `_search` request parameter.
     * Delegates to the model's named `search` scope if a term is present.
     * Returns $this for fluent chaining.
     *
     * @return static
     */
    public function applySearchOnQuery(): static
    {
        $searchTerm = $this->request->input('_search');

        // Support both old-style scopeSearch() and new #[Scope] attribute search() methods
        if ($searchTerm && (method_exists($this->query->getModel(), 'scopeSearch') || method_exists($this->query->getModel(), 'search'))) {
            $this->query->search($searchTerm);
        }

        return $this;
    }

    /**
     * Apply sort ordering using the `_sort` request parameter.
     * Format: "field:asc" or "field:desc".
     * Only allows sorting on whitelisted columns to prevent injection.
     * Returns $this for fluent chaining.
     *
     * @return static
     */
    public function applySortOnQuery(): static
    {
        $sort = $this->request->input('_sort');

        if ($sort) {
            $parts     = explode(':', $sort, 2);
            $column    = $parts[0] ?? null;
            $direction = isset($parts[1]) && strtolower($parts[1]) === 'desc' ? 'desc' : 'asc';

            if ($column) {
                // Sanitise: only allow alphanumeric + underscores to prevent SQL injection
                $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

                if ($column) {
                    $this->query->orderBy($column, $direction);
                }
            }
        }

        return $this;
    }

    /**
     * Apply a date-range filter on the query.
     *
     * Ranges:
     *   today       → whereDate(col, today())
     *   this_week   → whereBetween(col, [startOfWeek, endOfWeek])
     *   this_month  → whereBetween(col, [startOfMonth, endOfMonth])
     *   this_year   → whereBetween(col, [startOfYear, endOfYear])
     *   custom      → whereBetween(col, [$dateRangeStart, $dateRangeEnd])
     *   all_time / null → no filter
     *
     * @param Builder     $query
     * @param string|null $dateRange        Preset key (today, this_week, etc.)
     * @param string|null $dateRangeStart   Start date for "custom" range (Y-m-d)
     * @param string|null $dateRangeEnd     End date for "custom" range (Y-m-d)
     * @param string      $column           Column to filter on (default: created_at)
     * @return Builder
     */
    public function applyDateRange(
        Builder $query,
        ?string $dateRange,
        ?string $dateRangeStart = null,
        ?string $dateRangeEnd = null,
        string $column = 'created_at'
    ): Builder {
        if (! $dateRange || $dateRange === 'all_time') {
            return $query;
        }

        return match ($dateRange) {
            'today' => $query->whereDate($column, today()),

            'this_week' => $query->whereBetween($column, [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]),

            'this_month' => $query->whereBetween($column, [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]),

            'this_year' => $query->whereBetween($column, [
                now()->startOfYear(),
                now()->endOfYear(),
            ]),

            'custom' => (function () use ($query, $column, $dateRangeStart, $dateRangeEnd): Builder {
                if ($dateRangeStart && $dateRangeEnd) {
                    $query->whereBetween($column, [$dateRangeStart, $dateRangeEnd]);
                } elseif ($dateRangeStart) {
                    $query->where($column, '>=', $dateRangeStart);
                } elseif ($dateRangeEnd) {
                    $query->where($column, '<=', $dateRangeEnd);
                }

                return $query;
            })(),

            default => $query,
        };
    }

    /**
     * Paginate the current query and return a ResourceCollection.
     *
     * Reads `_per_page` from the request (falls back to $defaultPerPage).
     * Applies search and sort automatically before paginating.
     *
     * @return ResourceCollection
     */
    public function getOutput(): ResourceCollection
    {
        $this->applyDateRangeFromRequest();
        $this->applySearchOnQuery();
        $this->applySortOnQuery();

        $perPage = (int) $this->request->input('_per_page', $this->defaultPerPage);

        if ($perPage < 1) {
            $perPage = $this->defaultPerPage;
        }

        $paginated = $this->query->paginate($perPage)->withQueryString();

        $collectionClass = $this->resolveResourceCollectionClass();

        return new $collectionClass($paginated);
    }

    /**
     * Wrap a single model in its singular JsonResource.
     *
     * @param Model $model
     * @return JsonResource
     */
    public function showResource(Model $model): JsonResource
    {
        $resourceClass = $this->resolveResourceClass();

        return new $resourceClass($model);
    }

    /**
     * Return a "created" response array wrapping the singular resource.
     *
     * @param Model $model
     * @return array{data: JsonResource, message: string}
     */
    public function showCreatedResource(Model $model): array
    {
        return [
            'data'    => $this->showResource($model),
            'message' => 'Created successfully',
        ];
    }

    /**
     * Return an "updated" response array wrapping the singular resource.
     *
     * @param Model $model
     * @return array{data: JsonResource, message: string}
     */
    public function showUpdatedResource(Model $model): array
    {
        return [
            'data'    => $this->showResource($model),
            'message' => 'Updated successfully',
        ];
    }

    // -------------------------------------------------------------------------
    // Export helpers
    // -------------------------------------------------------------------------

    /**
     * Build a file download response in CSV, Excel, or PDF format.
     *
     * @param array  $rows      Two-dimensional array of data rows
     * @param array  $headings  Column heading labels
     * @param string $filename  Base filename (without extension)
     * @param string $format    'csv' | 'xlsx' | 'pdf'
     * @param string $pdfTitle  Title shown at the top of PDF exports
     * @param array  $pdfMeta   Optional key→value pairs shown below the PDF title
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function buildFileResponse(
        array  $rows,
        array  $headings,
        string $filename,
        string $format,
        string $pdfTitle = 'Export',
        array  $pdfMeta  = []
    ): \Symfony\Component\HttpFoundation\Response {
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows, $headings) {
                $output = fopen('php://output', 'w');
                fputcsv($output, $headings);
                foreach ($rows as $row) {
                    fputcsv($output, array_values($row));
                }
                fclose($output);
            }, $filename . '.csv', [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            ]);
        }

        if ($format === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SimpleExport($rows, $headings),
                $filename . '.xlsx'
            );
        }

        if ($format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.table', [
                'title'    => $pdfTitle,
                'headings' => $headings,
                'rows'     => $rows,
                'meta'     => $pdfMeta,
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        abort(400, 'Invalid export format. Supported: csv, xlsx, pdf');
    }

    /**
     * Resolve the export record limit from the _limit parameter.
     *
     * @param string|int $limit
     * @return int
     */
    protected function resolveExportLimit(string|int $limit): int
    {
        if ($limit === 'current') return 15;
        $n = (int) $limit;
        return $n > 0 ? min($n, 5000) : 15;
    }

    // -------------------------------------------------------------------------
    // Resource class resolution (convention-based)
    // -------------------------------------------------------------------------

    /**
     * Resolve the singular JsonResource class from the child service class name.
     *
     * Convention:
     *   EstateService      → App\Http\Resources\EstateResource
     *   UnitTenantService  → App\Http\Resources\UnitTenantResource
     *
     * Child classes may override `$resourceClass` to bypass the convention.
     *
     * @return class-string<JsonResource>
     *
     * @throws \RuntimeException if the resolved class does not exist
     */
    protected function resolveResourceClass(): string
    {
        if (property_exists($this, 'resourceClass') && $this->resourceClass) {
            return $this->resourceClass;
        }

        $basename = class_basename(static::class);
        // Strip trailing "Service" → e.g. "EstateService" → "Estate"
        $name  = preg_replace('/Service$/', '', $basename);
        $class = "App\\Http\\Resources\\{$name}Resource";

        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Resource class [{$class}] not found. Either create it or set a \$resourceClass property on " . static::class
            );
        }

        return $class;
    }

    /**
     * Resolve the ResourceCollection class from the child service class name.
     *
     * Convention:
     *   EstateService      → App\Http\Resources\EstateResources  (plural)
     *   UnitTenantService  → App\Http\Resources\UnitTenantResources
     *
     * Child classes may override `$resourceCollectionClass` to bypass the convention.
     *
     * @return class-string<ResourceCollection>
     *
     * @throws \RuntimeException if the resolved class does not exist
     */
    protected function resolveResourceCollectionClass(): string
    {
        if (property_exists($this, 'resourceCollectionClass') && $this->resourceCollectionClass) {
            return $this->resourceCollectionClass;
        }

        $basename = class_basename(static::class);
        $name     = preg_replace('/Service$/', '', $basename);
        $class    = "App\\Http\\Resources\\{$name}Resources";

        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Resource collection class [{$class}] not found. Either create it or set a \$resourceCollectionClass property on " . static::class
            );
        }

        return $class;
    }
}
