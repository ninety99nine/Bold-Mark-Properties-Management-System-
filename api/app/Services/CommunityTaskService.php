<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Http\Resources\UnitTaskResource;
use App\Http\Resources\UnitTaskUpdateResource;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitTask;
use App\Models\UnitTaskUpdate;
use Illuminate\Support\Facades\Auth;

/**
 * Community-level Tasks (WeConnectU parity). Tasks belong to a community and may
 * optionally reference a unit; the tab (Active / Overdue / Complete) is derived
 * from status + due date.
 */
class CommunityTaskService
{
    private const PER_PAGE = 10;

    /**
     * Paginated task list for a community, filtered by tab + search, with tab counts.
     */
    public function list(Community $community, array $filters): array
    {
        $tab    = $filters['tab'] ?? 'active';
        $search = trim((string) ($filters['search'] ?? ''));
        $sort   = $filters['sort'] ?? 'desc';
        $page   = max(1, (int) ($filters['page'] ?? 1));

        $base = UnitTask::where('community_id', $community->id);

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('assignee_name', 'like', "%{$search}%");
            });
        }

        $counts = [
            'active'   => (clone $base)->tap(fn ($q) => $this->scopeTab($q, 'active'))->count(),
            'overdue'  => (clone $base)->tap(fn ($q) => $this->scopeTab($q, 'overdue'))->count(),
            'complete' => (clone $base)->tap(fn ($q) => $this->scopeTab($q, 'complete'))->count(),
        ];

        $query = (clone $base)->with(['community', 'unit'])->withCount('updates');
        $this->scopeTab($query, $tab);

        $query->orderBy('due_date', $sort === 'asc' ? 'asc' : 'desc')
              ->orderBy('created_at', 'desc');

        $paginator = $query->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'data'   => UnitTaskResource::collection($paginator->items()),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
            'counts' => $counts,
        ];
    }

    /**
     * Apply the tab filter (active | overdue | complete) to a query.
     */
    private function scopeTab($query, string $tab): void
    {
        $complete = TaskStatus::COMPLETE->value;
        $today    = now()->startOfDay()->toDateString();

        match ($tab) {
            'complete' => $query->where('status', $complete),
            'overdue'  => $query->where('status', '!=', $complete)
                                 ->whereNotNull('due_date')
                                 ->whereDate('due_date', '<', $today),
            default    => $query->where('status', '!=', $complete)
                                 ->where(function ($q) use ($today) {
                                     $q->whereNull('due_date')->orWhereDate('due_date', '>=', $today);
                                 }),
        };
    }

    public function show(Community $community, UnitTask $task): UnitTaskResource
    {
        return new UnitTaskResource($task->load(['community', 'unit', 'updates'])->loadCount('updates'));
    }

    public function create(Community $community, array $data, array $files = []): array
    {
        $user   = Auth::user();
        $unitId = $data['unit_id'] ?? null;
        $unit   = $unitId ? Unit::where('community_id', $community->id)->find($unitId) : null;

        $task = UnitTask::create(array_merge($this->attributes($data, $files), [
            'code'            => $this->generateCode($community),
            'unit_id'         => $unit?->id,
            'area'            => $data['area'] ?? ($unit ? 'Unit No ' . $unit->unit_number : 'Not Applicable'),
            'community_id'    => $community->id,
            'organization_id' => $community->organization_id,
            'status'          => $data['status'] ?? TaskStatus::IN_PROGRESS->value,
            'created_by_name' => $user?->name ?? 'System',
            'user_id'         => $user?->id,
        ]));

        return [
            'data'    => $this->show($community, $task->fresh()),
            'message' => 'Task added.',
        ];
    }

    public function update(Community $community, UnitTask $task, array $data, array $files = []): array
    {
        $user     = Auth::user();
        $previous = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;

        $task->update($this->attributes($data, $files));

        $new = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;

        if (array_key_exists('status', $data) && $new !== $previous) {
            UnitTaskUpdate::create([
                'unit_task_id'    => $task->id,
                'organization_id' => $task->organization_id,
                'event'           => 'Status changed to: ' . TaskStatus::from($new)->label(),
                'created_by_name' => $user?->name ?? 'System',
                'user_id'         => $user?->id,
            ]);
        }

        return [
            'data'    => $this->show($community, $task->fresh()),
            'message' => 'Task updated.',
        ];
    }

    public function delete(Community $community, UnitTask $task): array
    {
        $task->delete();

        return ['message' => 'Task removed.'];
    }

    public function addUpdate(Community $community, UnitTask $task, array $data, array $files = []): array
    {
        $user = Auth::user();

        $attrs = [
            'unit_task_id'    => $task->id,
            'organization_id' => $task->organization_id,
            'feedback'        => $data['feedback'] ?? null,
            'notify'          => $data['notify'] ?? null,
            'created_by_name' => $user?->name ?? 'System',
            'user_id'         => $user?->id,
        ];

        if (! empty($files)) {
            $attrs['attachment_names'] = array_map(fn ($f) => $f->getClientOriginalName(), $files);
        }

        $update = UnitTaskUpdate::create($attrs);

        return ['data' => new UnitTaskUpdateResource($update), 'message' => 'Feedback added.'];
    }

    private function attributes(array $data, array $files): array
    {
        $attrs = collect($data)->only([
            'title', 'description', 'category', 'task_type', 'area', 'recurring_type',
            'assignee_name', 'assignee_user_id', 'status', 'internal', 'due_date',
            'contacts', 'supplier_names',
        ])->toArray();

        foreach (['contacts', 'supplier_names'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key])) {
                $attrs[$key] = array_values(array_filter($attrs[$key], fn ($v) => filled($v)));
            }
        }

        if (! empty($files)) {
            $attrs['attachment_names'] = array_map(fn ($f) => $f->getClientOriginalName(), $files);
        }

        return $attrs;
    }

    private function generateCode(Community $community): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $community->name ?? 'TSK'), 0, 3));
        $prefix = str_pad($prefix ?: 'TSK', 3, 'X');
        $sequence = UnitTask::where('community_id', $community->id)->count() + 1;

        return $prefix . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
