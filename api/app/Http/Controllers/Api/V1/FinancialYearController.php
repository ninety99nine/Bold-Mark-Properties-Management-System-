<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\FinancialYearService;

class FinancialYearController extends Controller
{
    protected FinancialYearService $service;

    public function __construct(FinancialYearService $service)
    {
        $this->service = $service;
    }

    /**
     * List the financial-year / budget-period options for a community.
     *
     * Powers the WeConnectU "Financial Year / Budget Period" selector: the
     * past 3 years, the current year-end, and the next 2 future years, each
     * flagged Setup / Not Setup.
     *
     * @param Community $community
     * @return array
     */
    public function showFinancialYears(Community $community): array
    {
        return $this->service->getFinancialYears($community);
    }
}
