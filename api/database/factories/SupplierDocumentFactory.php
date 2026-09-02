<?php

namespace Database\Factories;

use App\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierDocument>
 */
class SupplierDocumentFactory extends Factory
{
    protected $model = SupplierDocument::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'supplier_id'     => null,
            'name'            => fake()->words(2, true) . '.pdf',
            'path'            => 'suppliers/documents/' . fake()->uuid() . '.pdf',
        ];
    }
}
