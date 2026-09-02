<?php

namespace App\Enums;

enum TakeonItemStatus: string
{
    // No file uploaded and not marked not-applicable yet.
    case PENDING = 'pending';

    // A file has been uploaded (and its data imported).
    case UPLOADED = 'uploaded';

    // The managing agent has declared this step does not apply.
    case NOT_APPLICABLE = 'not_applicable';

    /**
     * All enum values as a plain array (for validation / migrations).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
