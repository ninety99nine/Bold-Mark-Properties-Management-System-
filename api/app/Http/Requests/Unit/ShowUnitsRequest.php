<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Unit::class, $this->route('estate')]);
    }

    public function rules(): array
    {
        return [
            // Toolbar — search
            '_search'           => ['nullable', 'string', 'max:200'],

            // Toolbar — sort  (field:direction, e.g. "unit_number:asc")
            '_sort'             => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_]+(:(asc|desc))?$/i'],

            // Toolbar — date range
            '_date_range'       => ['nullable', 'string', 'in:today,this_week,this_month,this_year,custom,all_time'],
            '_date_range_start' => ['nullable', 'date'],
            '_date_range_end'   => ['nullable', 'date', 'after_or_equal:_date_range_start'],

            // Toolbar — pagination
            '_per_page'         => ['nullable', 'integer', 'min:1', 'max:200'],

            // Filters
            'occupancy_type'    => ['nullable', 'string', 'in:owner_occupied,tenant_occupied,vacant'],
            'status'            => ['nullable', 'string', 'in:active,suspended,vacated'],
            'balance'           => ['nullable', 'string', 'in:in_arrears,clear'],
        ];
    }
}
