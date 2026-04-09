<?php

namespace App\Http\Requests\TableView;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'context'          => ['required', 'string', 'in:units,invoices,cashbook,age-analysis,users'],
            'name'             => ['required', 'string', 'max:100'],
            'date_range'       => ['nullable', 'string', 'in:today,this_week,this_month,this_year,custom,all_time'],
            'date_range_start' => ['nullable', 'date', 'required_if:date_range,custom'],
            'date_range_end'   => ['nullable', 'date', 'required_if:date_range,custom', 'after_or_equal:date_range_start'],
            'filters'          => ['nullable', 'array'],
            'sort_field'       => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_]+$/'],
            'sort_direction'   => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
