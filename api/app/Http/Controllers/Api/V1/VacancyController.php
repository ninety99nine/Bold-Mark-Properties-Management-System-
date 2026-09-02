<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\VacancyService;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    protected VacancyService $service;

    public function __construct(VacancyService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of vacant units across all communities,
     * plus summary stats for the vacancies page.
     */
    public function index(Request $request): array
    {
        return $this->service->showVacancies($request->all());
    }
}
