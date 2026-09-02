<?php

namespace Database\Factories;

use App\Models\ComplianceItemAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceItemAttachment>
 */
class ComplianceItemAttachmentFactory extends Factory
{
    protected $model = ComplianceItemAttachment::class;

    public function definition(): array
    {
        return [
            'compliance_checklist_item_id' => null,
            'uploaded_by_id'               => null,
            'file_path'                    => 'compliance-evidence/test/' . Str::uuid() . '.pdf',
            'file_name'                    => 'document.pdf',
            'file_size'                    => 1024,
            'mime_type'                    => 'application/pdf',
        ];
    }
}
