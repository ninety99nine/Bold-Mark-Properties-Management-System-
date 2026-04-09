<?php

namespace App\Http\Requests\TableView;

use App\Models\TableView;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTableViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TableView $view */
        $view = $this->route('tableView');

        // Only the owner of the view may update it
        return $view && $view->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'required', 'string', 'max:100'],
            'date_range'       => ['nullable', 'string', 'in:today,this_week,this_month,this_year,custom,all_time'],
            'date_range_start' => ['nullable', 'date'],
            'date_range_end'   => ['nullable', 'date', 'after_or_equal:date_range_start'],
            'filters'          => ['nullable', 'array'],
            'sort_field'       => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_]+$/'],
            'sort_direction'   => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
