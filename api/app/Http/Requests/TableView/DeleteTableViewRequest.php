<?php

namespace App\Http\Requests\TableView;

use App\Models\TableView;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTableViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TableView $view */
        $view = $this->route('tableView');

        // Only the owner of the view may delete it
        return $view && $view->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [];
    }
}
