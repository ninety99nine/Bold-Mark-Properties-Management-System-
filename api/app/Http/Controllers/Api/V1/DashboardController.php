<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\CountryHelper;
use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Models\Organization;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all summary data needed to render the dashboard.
     *
     * @param Request $request
     * @return array
     */
    public function getDashboardSummary(Request $request): array
    {
        return $this->service->getDashboardSummary($request->input('country'));
    }

    /**
     * Return the list of countries that have estates, plus supported options.
     *
     * @param Request $request
     * @return array
     */
    public function getCountries(Request $request): array
    {
        $tenantId = Auth::user()->organization_id;

        $countryCodes = Estate::where('organization_id', $tenantId)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->toArray();

        $countries = [];
        foreach ($countryCodes as $code) {
            $info = CountryHelper::get($code);
            if ($info) {
                $countries[] = array_merge(['code' => $code], $info);
            }
        }

        // Also include the tenant's default country
        $tenant = Organization::find($tenantId);
        $defaultCountry = $tenant->country;

        return [
            'countries'       => $countries,
            'default_country' => $defaultCountry,
            'supported'       => CountryHelper::options(),
        ];
    }
}
