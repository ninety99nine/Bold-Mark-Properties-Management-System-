<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\UnitTask;
use App\Services\CommunityTaskService;
use Illuminate\Http\Request;

class CommunityTaskController extends Controller
{
    public function __construct(private CommunityTaskService $service)
    {
    }

    public function index(Request $request, Community $community): array
    {
        $data = $request->validate([
            'tab'    => ['nullable', 'in:active,overdue,complete'],
            'search' => ['nullable', 'string', 'max:200'],
            'sort'   => ['nullable', 'in:asc,desc'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->service->list($community, $data);
    }

    public function show(Request $request, Community $community, UnitTask $task): array
    {
        return ['data' => $this->service->show($community, $task)];
    }

    public function store(Request $request, Community $community): array
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'category'      => ['nullable', 'string', 'max:120'],
            'area'          => ['nullable', 'string', 'max:120'],
            'unit_id'       => ['nullable', 'string'],
            'assignee_name' => ['nullable', 'string', 'max:120'],
            'due_date'      => ['nullable', 'date'],
            'status'        => ['nullable', 'in:' . implode(',', TaskStatus::values())],
            'contacts'      => ['nullable', 'array'],
        ]);

        return $this->service->create($community, $data, $request->file('attachments') ?? []);
    }

    public function update(Request $request, Community $community, UnitTask $task): array
    {
        $data = $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'category'      => ['nullable', 'string', 'max:120'],
            'area'          => ['nullable', 'string', 'max:120'],
            'assignee_name' => ['nullable', 'string', 'max:120'],
            'due_date'      => ['nullable', 'date'],
            'status'        => ['sometimes', 'in:' . implode(',', TaskStatus::values())],
            'contacts'      => ['nullable', 'array'],
        ]);

        return $this->service->update($community, $task, $data, $request->file('attachments') ?? []);
    }

    public function destroy(Request $request, Community $community, UnitTask $task): array
    {
        return $this->service->delete($community, $task);
    }

    public function addUpdate(Request $request, Community $community, UnitTask $task): array
    {
        $data = $request->validate([
            'feedback' => ['required', 'string'],
            'notify'   => ['nullable', 'string', 'max:120'],
        ]);

        return $this->service->addUpdate($community, $task, $data, $request->file('attachments') ?? []);
    }
}
