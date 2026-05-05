<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceEmailEvent>
 */
class InvoiceEmailEventFactory extends Factory
{
    protected $model = InvoiceEmailEvent::class;

    public function definition(): array
    {
        return [
            'invoice_id'      => Invoice::factory(),
            'organization_id' => null,
            'event_type'      => 'sent',
            'email'           => fake()->safeEmail(),
            'resend_email_id' => 'resend-' . fake()->uuid(),
            'occurred_at'     => now(),
            'metadata'        => null,
        ];
    }
}
