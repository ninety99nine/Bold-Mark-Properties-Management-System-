<?php

namespace Database\Factories;

use App\Enums\ComplianceItemPriority;
use App\Enums\ComplianceItemStatus;
use App\Models\ComplianceChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceChecklistItem>
 */
class ComplianceChecklistItemFactory extends Factory
{
    protected $model = ComplianceChecklistItem::class;

    public function definition(): array
    {
        return [
            'compliance_checklist_id' => null,
            'organization_id'         => null,
            'name'                    => fake()->words(3, true),
            'category'                => fake()->randomElement(['legal', 'financial', 'safety', 'environmental']),
            'description'             => null,
            'priority'                => ComplianceItemPriority::MEDIUM->value,
            'status'                  => ComplianceItemStatus::PENDING->value,
            'due_date'                => null,
            'sort_order'              => 0,
            'is_recurring'            => true,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => ComplianceItemStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status'   => ComplianceItemStatus::OVERDUE->value,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
    }
}
